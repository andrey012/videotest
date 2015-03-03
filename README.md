# videotest

This is small extension over Yii CWebTestCase to enable some nice animation features for functional tests.

These features should be useful for recording videos on Continuous Integration servers. These videos can be used as acceptance materials or for teaching users/testers/other developers. 

Some examples on video effects, that can be created using this extension can be found at https://github.com/andrey012/videotestresults

For examples on usage of this extension - see https://github.com/andrey012/videotestsandbox and particularly /protected/tests/functional/SiteTest.php and /protected/tests/WebTestCase.php

# trial and error approach when developing Selenium tests

It is common thing, that creating and maintaining Selenium tests takes quite some time. Especially it becomes critical when tests become longer, and require some prerequisites to be prepared (fixtures, etc). In this case each launch takes 5 seconds to boot up (prepare configuration, launch 2 intances of browser, open homepage), and then some time to run test, which becomes annoying. 

This component provides simple solution for this case - you can "pause" you functional test at any point and proceed with it step by step modifying your code on the fly. 

Here it how it work for example you have a test code: 
```
    $this->open('');
    $this->click('xpath=//a[contains(@href, "login")]');
    $this->assertElementPresent('xpath=//h1[contains(text(), "Login")]');
```

You are sure, that first line is ok, but not sure about others. You put one line between 1st and 2nd lines, so it becomes: 

```
    $this->open('');
    
    $this->doTry(get_defined_vars()); } function t(){
    
    $this->click('xpath=//a[contains(@href, "login")]');
    $this->assertElementPresent('xpath=//h1[contains(text(), "Login")]');
```

You can see, that this new line terminates your test method calling $this->doTry() and starts another function t(){

What happens then: 
* doTry will pass defined vars to t() by replacing function t() with function t($bar, $foo, ...)
* doTry will wrap call to t() with try...catch block to prevent test from failing, and print Exception details to the console
* after t() will complete it will ask you to hit Enter in the console, where you launch phpunit
* after you hit Enter it will reload your testcase source file in order to grab your changed code and call t() in the same manner again

So, ```$this->click('xpath=//a[contains(@href, "login")]');``` will be successful, but next statement - not because you had to use clickAndWait -- you will return your browser to homepage manually, replace ```click``` with ```clickAndWait``` and hit Enter in the console. The process will continue without need to restart browsers and apply fixtures. 

After you decide, that ```$this->clickAndWait('xpath=//a[contains(@href, "login")]');``` is good enough - you can move it to the upper function, so the code will be: 


```
    $this->open('');
    $this->click('xpath=//a[contains(@href, "login")]');
    
    $this->doTry(get_defined_vars()); } function t(){
    
    $this->assertElementPresent('xpath=//h1[contains(text(), "Login")]');
```

And when hitting Enter you will not need to return browser to homepage any more. So, step by step you move your working code from "trial" function to your testcase. Of course it is necessary to finally re-run the test after you are done. 

