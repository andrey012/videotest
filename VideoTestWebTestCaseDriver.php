<?php
/** 
 * usage: 
 *   public function setUp(){
 *     Yii::import('application.vendor.videotest.VideoTestWebTestCaseDriver');
 *     $this->drivers[0]=VideoTestWebTestCaseDriver::attach($this->drivers[0], $this);
 *     parent::setUp();
 *   }
 *   public function testABC(){
 *     $this->videoInit();
 *     $this->open('');
 *     $this->videoStart('ABC');
 *     $this->videoShowMessage('Test ABC');
 *     ... 
 * configuration:
 *   Yii::app()->params['selenium-video']['fast'] 
 *     set to 1 to skip animation and make test run faster during development
 *     set to 2 to skip all visual effects completely
 *     inside the test you can override this value by using following functions: 
 *       videoSlow() -- equal to setting configuration value to 0
 *       videoFast() -- equal to setting configuration value to 1
 *       videoSkip() -- equal to setting configuration value to 2
 *       videoDefault() -- returns to default setting from configuration
 *       these functions can be configured to be ignored on CI server 
 *       if Yii::app()->params['selenium-video']['ignore-fast-override'] is set to true
 */


class VideoTestWebTestCaseDriverFunctions {
    public $overrideFastMode = false;
    public $testCase;
    const VIDEO_DEFAULT_MESSAGE_POSITION = 'top: 100px; left: 100px; right: 100px; height: 200px;';
    public $videoDefaultMessagePosition = self::VIDEO_DEFAULT_MESSAGE_POSITION;

    /** 
     * Makes sure that element is visible 
     * scrolls up-down if necessary. No horizontal scroll. 
     * @param $element string - same as element in Selenium methods
     */
    public function videoSetVisible($element){
        $x = $this->testCase->getElementPositionLeft($element);
        $y = $this->testCase->getElementPositionTop($element);
        $step = $this->isFastModeOn()?50:15;
        $windowHeight = $this->testCase->getEval('window.innerHeight');
        $height = min($windowHeight * 80 / 100, $this->testCase->getElementHeight($element));
        while ((($y - $height/2) < (($currentYScroll = $this->testCase->getEval('window.document.documentElement.scrollTop')) + 50)) && $currentYScroll){
            if ($this->isFastModeOn()){
                $step = 50;
            } else {
                $diff = ($currentYScroll + 50) - ($y - $height/2);
                if ($diff > 150) {
                    $step = min(500, intval($diff/5));
                } else {
                    $step = 15;
                }
            }
            $this->testCase->runScript("window.scrollBy(0,-".$step.");");
            if (!$this->isFastModeOn()) usleep(10000);
        }
        $lastYScroll = -1;
        while ((($y + $height/2) > (($currentYScroll = $this->testCase->getEval('window.document.documentElement.scrollTop')) + $windowHeight - 50)) && ($currentYScroll != $lastYScroll)){
            if ($this->isFastModeOn()){
                $step = 50;
            } else {
                $diff = ($y + $height/2) - ($currentYScroll + $windowHeight - 50);
                if ($diff > 150) {
                    $step = min(500, intval($diff/5));
                } else {
                    $step = 15;
                }
            }
            $lastYScroll = $currentYScroll;
            $this->testCase->runScript("window.scrollBy(0,".$step.");");
            if (!$this->isFastModeOn()) usleep(10000);
        }

    }

    /** 
     * Draws mouse cursor which moves towards desired control, then invokes mouseOver
     * This method DOES NOT CLICK, this method only animates mouse
     * @param $element string - same as element in Selenium methods
     * @param $nearTheLeftSide mixed
     *   true -> mouse will move to the left side of the element (useful for labels of checkboxes)
     *   array(top,left) - exact coordinates (px) where to move
     *   array(+5, +0) - tweak coordinates - add 5 px to top and leave left as is
     */

    public function videoMouseClick($element, $nearTheLeftSide=false, $highlightCallback=false){
        $this->testCase->runScript('var list = window.document.getElementsByClassName("bftv_hover"); while (list.length) { list[0].className = list[0].className.replace("bftv_hover", ""); } ');
        $this->testCase->videoSetVisible($element);
        if ($this->isSkipModeOn()) return;
        $x = $this->testCase->getElementPositionLeft($element) + ($nearTheLeftSide?rand(5,20):($this->testCase->getElementWidth($element)*rand(20,80)/100));
        $y = ($a=$this->testCase->getElementPositionTop($element)) + ($b=$this->testCase->getElementHeight($element))*($c=rand(20,80)/100) - ($d=$this->testCase->getEval('window.document.documentElement.scrollTop'));
        if (is_array($nearTheLeftSide)){
            if (substr($nearTheLeftSide[1], 0, 1) == '+'){
                $x += substr($nearTheLeftSide[1], 1);
            } elseif (substr($nearTheLeftSide[1], 0, 1) == '-'){
                $x -= substr($nearTheLeftSide[1], 1);
            } else {
                $x = $nearTheLeftSide[1];
            }
            if (substr($nearTheLeftSide[0], 0, 1) == '+'){
                $y += substr($nearTheLeftSide[0], 1);
            } elseif (substr($nearTheLeftSide[0], 0, 1) == '-'){
                $y -= substr($nearTheLeftSide[0], 1);
            } else {
                $y = $nearTheLeftSide[0] - $this->testCase->getEval('window.document.documentElement.scrollTop');
            }
        } 

        $mouseStyle = '	background: url(\'data:image/gif;base64,R0lGODlhGQAdALMAAAAAAP///wD/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAAIALAAAAAAZAB0AAARGUMhJq7046827l8CXAaFoAUFpTmi6skGsmq381jYd766I7zPOD/gZEjtDkhIJzBVlraCwF/WpqsUK9ubk9rxS62tMLpsnEQA7\') no-repeat; position: fixed; z-index: 9999999; width: 25px; height: 29px;';
        $script = array();
        
        if ($this->testCase->getEval('window.document.getElementById("mouse-cursor")?1:0')){
            $screenCenterY = $this->testCase->getElementPositionTop('id=mouse-cursor')+4;
            $screenCenterX = $this->testCase->getElementPositionLeft('id=mouse-cursor')+7;
            $alreadyExists = true;
        } else {
            $windowHeight = $this->testCase->getEval('window.innerHeight');
            $screenCenterY = $windowHeight/2;
            $screenCenterX = 500;
            $alreadyExists = false;
        }
        $hops = 20;
        $opacity = $hops/4;
        $coords = array();
        for ($i = $this->isFastModeOn()?($hops-4):1; $i <= $hops; $i ++){
            $coords[]=array(
                intval($y-($screenCenterY-$y)/$hops+($screenCenterY-$y)/$i-4), 
                intval($x-($screenCenterX-$x)/$hops+($screenCenterX-$x)/$i-7),
                (($i<$opacity)?(intval($i*10/$opacity)/10):1),
                (!$this->isFastModeOn() && ($i < ($hops*3/4)))?40:0,
            );
        }
        if ($alreadyExists){
            $this->testCase->runScript('window.document.getElementById("mouse-cursor").setAttribute("data-done", 0);');
        } else {
            $this->testCase->runScript('var a = window.document.createElement("div"); a.setAttribute("id", "mouse-cursor"); a.setAttribute("style", "top:'.$screenCenterY.'px; left:'.$screenCenterX.'px; opacity: 0; '.$mouseStyle.'"); window.document.getElementsByTagName("body")[0].appendChild(a); ');
        }
        $this->testCase->runScript('(function(coords){ if (0 == coords.length) { window.document.getElementById("mouse-cursor").setAttribute("data-done", 1); } else { var nextItem = coords.shift(); var mouse = window.document.getElementById("mouse-cursor"); mouse.style.left = nextItem[1] + "px"; mouse.style.top = nextItem[0] + "px"; mouse.style.opacity = nextItem[2]; setTimeout(arguments.callee, nextItem[3], coords); }})('.CJSON::encode($coords).');');
        
        $t = time();
        while (time() < ($t + 10)){
            $done = $this->testCase->getEval('window.document.getElementById("mouse-cursor").getAttribute("data-done");');
            if (($done === "1") || ($done === 1)) break;
            usleep(100000);
        }
        $this->testCase->mouseOver($element);
        if ($highlightCallback) call_user_func_array($highlightCallback, array($element));
        if (!$this->isFastModeOn()) usleep(200000);
    }
    /** 
     * just make a pause to let user see what's on the screen
     * @param $millis integer time to sleep in milliseconds, default = 2000 (2 seconds)
     */
    public function videoSleep($millis = 2000){
        if (!$this->isFastModeOn()){
            usleep($millis * 1000);
        }
    }

    /**
     * @param id string id of select element
     * @param value string value to be selected. IMPORTANT: value should be visible initially, scrolling is not implemented yet
     */
    public function videoCustomSelectBoxSelect($id, $value){
        $this->videoMouseClick('xpath=//select[@id="'.$id.'"]/following-sibling::a[1]');
        for ($exceptionTries = 0; $exceptionTries < 10; $exceptionTries++){
            try{
                $this->testCase->runScript($toggle='var e = document.createEvent("HTMLEvents"); e.initEvent("mousedown", false, true); e.which = 1; window.document.getElementById("'.$id.'").nextSibling.dispatchEvent(e); ');

                for ($tries = 200; $tries; $tries--){
                    if ($this->testCase->isElementPresent($q='xpath=//ul[contains(@class, "selectBox-options") and not(contains(@style, "display: none"))]/li/a[@rel="'.$value.'"]')){
                        $this->videoMouseClick($q);
                        break;
                    }
                    usleep(50000);
                }
                $name = $this->testCase->getText($q);
                $this->testCase->select('id='.$id, 'value='.$value);
                $this->testCase->runScript('window.document.getElementById("'.$id.'").nextSibling.children[0].innerHTML='.CJSON::encode($name).';');
                $this->testCase->runScript($toggle);
                return; 
            } catch (Exception $e){
                sleep(1);
            }
        }
    }

    public function videoHideDatePicker(){
        $this->testCase->runScript('var a = window.document.getElementsByClassName("ui-datepicker"); var i; for (i = 0; i < a.length; i++){a[i].style.display="none";}');
    }

    /** 
     * @param $position string a CSS style definition, e.g.: 'top: 100px; left: 100px; right: 100px; height: 200px;'. 
     * IMPORTANT: the ; at the end is mandatory
     */
    public function videoSetDefaultMessagePosition($position = self::VIDEO_DEFAULT_MESSAGE_POSITION){
        $this->videoDefaultMessagePosition = $position;
    }
    public function videoShowImage($files, $durations=5){
        if ($this->isSkipModeOn()) return;
        static $fnNameCounter = 1; 
        if (!is_array($files)) $files=array($files);
        if (!is_array($durations)) $durations=array($durations);

        $zindex = 10000000;
        $style = 'position: fixed; top: 100px; left: 100px; width:100%; height: 100%; display: block; z-index: '.$zindex.';';        

        $imgStyle = 'position: fixed; top: 100px; left: 100px;';
        $this->testCase->runScript('var a = window.document.createElement("div"); a.setAttribute("id", "video-image"); a.setAttribute("style", "'.$style.'"); a.innerHTML='.CJSON::encode('<img id="video-image-img-1" src="" style="'.$imgStyle.'; opacity: 0; z-index: '.$zindex.';"/><img id="video-image-img-2" src="" style="'.$imgStyle.'; opacity: 0; z-index: '.$zindex.';"/>').';  window.document.getElementsByTagName("body")[0].appendChild(a);');

        $step = 1;
        foreach ($files as $file){
            $zindex ++;
            $this->testCase->runScript('var a = window.document.getElementById("video-image-img-'.$step.'"); a.setAttribute("src", '.($file?CJSON::encode('data:image/png;base64,'.base64_encode(file_get_contents($file))):'""').'); var videoImageCounter'.$fnNameCounter.' = 0; function videoImageUpdate'.$fnNameCounter.'(){ a.setAttribute("style", "'.$imgStyle.' opacity: "+(videoImageCounter'.$fnNameCounter.'/100)+"; z-index: '.$zindex.';"); videoImageCounter'.$fnNameCounter.'+='.($this->isFastModeOn()?50:2).'; if (videoImageCounter'.$fnNameCounter.' <= 100) { window.setTimeout(videoImageUpdate'.$fnNameCounter.', 5); } } videoImageUpdate'.$fnNameCounter.'();');

            $step = 3 - $step;
            if (count($durations)){
                $delay = array_shift($durations);
            } else {
                if (!$delay) $delay = 5; 
            }

            if (!$this->isFastModeOn()) sleep($delay);
        }
        $this->testCase->runScript('window.document.getElementById("video-image-img-'.$step.'").setAttribute("style", "opacity: 0;"); var a = window.document.getElementById("video-image-img-'.(3-$step).'"); a.setAttribute("src", '.($file?CJSON::encode('data:image/png;base64,'.base64_encode(file_get_contents($file))):'""').'); var videoImageCounter'.$fnNameCounter.' = 0; function videoImageUpdate'.$fnNameCounter.'(){ a.setAttribute("style", "'.$imgStyle.' opacity: "+((100-videoImageCounter'.$fnNameCounter.')/100)+"; z-index: '.$zindex.';"); videoImageCounter'.$fnNameCounter.'+='.($this->isFastModeOn()?50:2).'; if (videoImageCounter'.$fnNameCounter.' <= 100) { window.setTimeout(videoImageUpdate'.$fnNameCounter.', 5); } } videoImageUpdate'.$fnNameCounter.'();');

        if (!$this->isFastModeOn()) sleep($delay);

        $this->testCase->runScript('var a = window.document.getElementById("video-image"); a.parentNode.removeChild(a);');
    }
    /** 
     * Shows message 
     * @param $text string - the text. Use "\n" to separate lines.
     * @param $position string see videoSetDefaultMessagePosition
     */
    public function videoShowMessage($text, $position=null, $moreMsToWait = 0){
        if ($this->isSkipModeOn()) return;
        if (!$position) $position = $this->videoDefaultMessagePosition;
        $pc = array('');
        $text .= ' ';
        $i=0;
        while (preg_match('/^(.)(.+)$/su', $text, $m)){
            if (in_array($m[1], array("\n", ' '))){
                $pc[]=array(false, $m[1]);
                $pc[]=array();
            } else {
                $pc[max(array_keys($pc))][]=$m[1];
            }
            $text = $m[2];
        }
        $videoMessageStyle = 'position: fixed; '.$position.' display: block; background-color: #fff; padding: 10px; font-size: 20px; z-index: 10000000; border: #bbb solid 10px; border-radius: 20px; overflow: hidden;';

        $blinkingCursor = '<div id="bfwebtestcasevideodriverblinkerdiv">_</div>';
        $this->testCase->runScript('var a = window.document.createElement("div"); a.setAttribute("id", "video-message"); a.setAttribute("style", "'.$videoMessageStyle.'"); a.innerHTML='.CJSON::encode('<style>@keyframes bfwebtestcasevideodriverblinker { 0% { opacity: 1.0; } 50% { opacity: 0.0; } 100% { opacity: 1.0; }} #bfwebtestcasevideodriverblinkerdiv {display: inline-block; width: 10px; margin-right: -10px; animation-name: bfwebtestcasevideodriverblinker; animation-duration: 1s; animation-timing-function: step-end; font-weight: bold; animation-iteration-count: infinite;}</style><table style="position: absolute; border: none; bottom: 2px;"><tr style="border: none;"><td id="video-message-text" style="padding: 2px 20px 2px 2px; font-size: 22px; color: #6a6a6a; font-family: Verdana, Tahoma, Arial, Helvetica, sans-serif; border: none; text-align: left;">'.$blinkingCursor.'</td></tr></table>').';  window.document.getElementsByTagName("body")[0].appendChild(a);');


        $contents = '';
        $codeSequence = array();
        foreach ($pc as $piece){
            if (!is_array($piece) || !count($piece) || !isset($piece[0])) continue;
            if ($piece[0] === false){
                $contents .= ($piece[1] == "\n")?'<br/>':htmlentities($piece[1]);
            } else {
                for ($k = 0; $k <= count($piece); $k++) {
                    $visible = '';
                    $invisible = '';
                    for ($i=0; $i<$k; $i++){
                        $visible.=$piece[$i];
                    }
                    for ($i=$k; $i<count($piece); $i++){
                        $invisible.=$piece[$i];
                    }
                    $lastWord = '<nobr style="font-size: inherit;"><span style="font-size: inherit;">'.htmlentities($visible).'</span>'.$blinkingCursor.'<span style="color: #fff; font-size: inherit;">'.htmlentities($invisible).'</span></nobr>';
                    $lastWordNoBlink = '<nobr style="font-size: inherit;"><span style="font-size: inherit;">'.htmlentities($visible).'</span><span style="color: #fff; font-size: inherit;">'.htmlentities($invisible).'</span></nobr>';
                
                    if ($this->isFastModeOn()) {
                    } else {
                        $codeSequence[] = array($contents.$lastWord, 50);
                    }
                }
                $contents .= $lastWordNoBlink;
            }
        }
        // TBD: this works slowly for large amounts of texts, we'd better optimize repeating $contents numerous times. 
        if ($this->isFastModeOn()) {
            $codeSequence[] = array($contents, 500+$moreMsToWait);
        } else {
            $lastItem = array_pop($codeSequence);
            $lastItem[1] += 2000+$moreMsToWait;
            array_push($codeSequence, $lastItem);
        }
        $this->testCase->runScript('var bftvvideomessage='.CJSON::encode($codeSequence).';');
        $this->testCase->runScript('var bftvvideomessagefunction=function(){ if (0 == bftvvideomessage.length) { var a = window.document.getElementById("video-message"); a.parentNode.removeChild(a); } else { var nextItem = bftvvideomessage.shift(); window.document.getElementById("video-message-text").innerHTML = nextItem[0]; setTimeout(bftvvideomessagefunction, nextItem[1]); }}; bftvvideomessagefunction(); ');
        while ($this->testCase->isElementPresent('id=video-message')) usleep(100000);
    }
    /** 
     * Types text inside specified element 
     * @param $element string - same as element in Selenium methods
     * @param $text string - text to type
     */
    public function videoType($element, $text){
        if ($this->isFastModeOn()){
            $this->testCase->type($element, $text);
            return;
        }
        $msg = '';
        $text .= ' ';
        while (preg_match('/^(.)(.+)$/su', $text, $m)){
            $msg .= $m[1];
            $text = $m[2];
            $this->testCase->type($element, $msg);
            usleep(20000);
        }
    }
    /** 
     * Starts video recording 
     * @param $name string - name of video file
     */
    public function videoStart($name){
        if (!$this->isFastModeOn()){
            exec('selenium-video '.$name.' 2>&1');
        }
    }
    /** 
     * Finishes video
     */
    public function videoStop(){
        if (!$this->isFastModeOn()){
            sleep(5);
            exec('selenium-video stop 2>&1');
        }
    }

    /** 
     * Prepares for video recording -  
     *   1. opens homepage, clears cookies,  maximizes window
     *   2. set ENVIRONMENT=SELENIUM_TEST cookie to let backend know that test configuration should be used
     */
    public function videoInit(){
        $this->testCase->open('');
        $this->testCase->deleteAllVisibleCookies();
        $this->testCase->createCookie('ENVIRONMENT=SELENIUM_TEST', 'path=/');
        $this->testCase->open('');
        $this->testCase->windowMaximize();
    }

    /** 
     * force fast mode -- used when debugging tests to save time
     */
    public function videoFast() {
        $this->overrideFastMode = 1;
    }

    /** 
     * force slow mode -- used when debugging tests to save time
     */
    public function videoSlow() { 
        $this->overrideFastMode = 0;
    }
    
    /** 
     * force skip mode -- used when debugging tests to save even more 
     * time - all visual effects will be turned off
     */
    public function videoSkip(){
        $this->overrideFastMode = 2;
    }

    /** 
     * reset mode to default (defined by config) -- used when debugging tests to save time
     */
    public function videoDefault() {
        $this->overrideFastMode = false;
    }
    
    public function isFastModeOn(){
        if (!isset(Yii::app()->params['selenium-video']['ignore-fast-override']) || !Yii::app()->params['selenium-video']['ignore-fast-override']){
            if ($this->overrideFastMode !== false) {
                return $this->overrideFastMode;
            }
        }
        return (isset(Yii::app()->params['selenium-video']['fast']) && Yii::app()->params['selenium-video']['fast'])?Yii::app()->params['selenium-video']['fast']:false;
    }
    
    public function isSkipModeOn(){
        return ($this->isFastModeOn() === 2);
    }
}

/** 
 * usage - see above
 */
class VideoTestWebTestCaseDriver {
    protected $driver;
    protected $testCase; 
    protected $functions;

    static public function attach($driver, $testCase){
        if (is_a($driver, __CLASS__)) return $driver;
        $class = __CLASS__;
        $copy = new $class;
        $copy->driver = $driver;
        $copy->testCase = $testCase;
        $copy->functions = new VideoTestWebTestCaseDriverFunctions();
        $copy->functions->testCase = $testCase;
        return $copy;
    }

    private $lastCallWasVideoCall;

    public function __get($name){
        return $this->driver->$name;
    }
    public function __set($name, $value){
        $this->driver->$name = $value;
    }
    public function __call($fn, $args){
        if (preg_match('/^video/', $fn)){
            // this is our call
            $result = call_user_func_array(array($this->functions, $fn), $args);
            $this->lastCallWasVideoCall = true;
        } elseif (in_array($fn, array('getVerificationErrors', 'clearVerificationErrors'))){
            if (!$this->lastCallWasVideoCall){
                return $this->driver->$fn();
            } else {
                return array();
            }
        } else {
            $this->lastCallWasVideoCall = false;
            return call_user_func_array(array($this->driver, $fn), $args);
        }
    }

    /** 
     * magic method to save some time when making video tests - it allows you change the code during 
     * test execution (thus keeping browser open) and retry until code works.
     * Function can get any parameters. All parameters will be passed to the test function
     * test function should be declared in the test case class, and have name "t": 
     * e.g. 
     *   function t() {
     * or 
     *   function t($params) {
     * This dotry function will call test function t in an indefinite loop asking you to press Enter 
     * in the test console after each turn. All exceptions will be caught and displayed instead of making test fail.
     * After code works - you can move code from test function t to the main testcase function.
     */
    public function dotry(){
        static $iteration = 0;
        static $preventDistructors = array();
        while (1){
            $d = debug_backtrace();
            $testFile = file_get_contents($d[4]['file']);
            preg_match_all($pattern = '/class\s+([^ ]+)\s+extends\s+/ui', $testFile, $m);
            if (!count($m[0])) throw new Exception($pattern.' not found');
            $testClassName = array();
            foreach ($m[0] as $k=>$v){
                $className = $m[1][$k];
                $newClassName = $className.$iteration;
                $testFile = str_replace($v, 'class '.$newClassName.' extends ', $testFile);
                $classNames[$className] = $newClassName;
                if (is_a($className, 'CWebTestCase')) $testClassName = $newClassName;
            }
            $iteration ++;
            $testFile = preg_replace('/^\<\?(php)?/ui', '', $testFile);
            eval($testFile);
            $test = new $newClassName;
            $preventDistructors[] = $test;
            $reflection = new ReflectionClass($test);
            $property = $reflection->getProperty('drivers');
            $property->setAccessible(true);
            
            $oldReflection = new ReflectionClass($this->testCase);
            $oldProperty = $oldReflection->getProperty('drivers');
            $oldProperty->setAccessible(true);
            $property->setValue($test, $oldProperty->getValue($this->testCase));


            try {
                call_user_func_array(array($test, 't'), func_get_args());
            } catch (Exception $e){
                print_r($e);
            }
            echo PHP_EOL.'press enter...'.PHP_EOL;
            @flush(); @ob_flush();
            fgets(STDIN);

        }        
    }
    /** 
     * just dumps values during test execution. To be used for debugging tests
     */
    public function say($msg){
        print_r($msg);
        echo PHP_EOL;
        if (strlen(print_r($msg, true)) < 100) var_dump($msg);
        flush(); @ob_flush();
    }
}
