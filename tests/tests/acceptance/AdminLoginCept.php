<?php 

function admin_login($I)
{
     // if snapshot exists - skipping login
     if ($I->loadSessionSnapshot('login')) return;
     // logging in
     $I->amOnPage('/admin');
     $I->fillField('username', 'admin');
     $I->fillField('password', 'password');
     $I->click('Login');
     // saving snapshot
     $I->saveSessionSnapshot('login');
}

$I = new AcceptanceTester($scenario);

admin_login($I);

$I->waitForElement('#footer', 30); // secs
$I->seeInCurrentUrl('/admin/dashboard');
$I->see('Commerce');