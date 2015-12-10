<?php 
$I = new AcceptanceTester($scenario);
$I->wantTo('ensure that example templates homepage exists.');
$I->amOnPage('/commerce'); 
$I->see('These are the Craft Commerce example templates');


$I->wantTo('ensure that I can add to the cart.');
$I->amOnPage('/commerce/products'); 
$I->click('//*[@id="main"]/div[3]/div[2]/form/input[4]');
$I->see('Cart updated.');

?>