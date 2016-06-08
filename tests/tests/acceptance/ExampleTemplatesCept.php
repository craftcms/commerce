<?php 

$I = new AcceptanceTester($scenario);

$I->wantTo('Ensure that example templates are working.');
$I->amOnPage('/commerce'); 
$I->see('These are the Craft Commerce example templates');

$I->amOnPage('/commerce/products');
$I->see('Parka with Stripes on Back');
$I->see('Romper for a Red Eye');
$I->see('A New Toga');
$I->click('Add to cart');

$I->seeInCurrentUrl('/commerce/cart');
$I->see('Cart updated.');

$I->click('Checkout');

$I->seeInCurrentUrl('/commerce/checkout');
$I->fillField(['name' => 'email'], 'test@test.com'); 
$I->click('Continue as Guest');

$I->seeInCurrentUrl('/commerce/checkout/addresses');
$I->fillField(['name' => 'shippingAddress[firstName]'], 'Luke'); 
$I->fillField(['name' => 'shippingAddress[lastName]'], 'Holder');
$I->fillField(['name' => 'shippingAddress[businessName]'], 'P&T');
$I->fillField(['name' => 'shippingAddress[businessTaxId]'], '001-001-001');
$I->fillField(['name' => 'shippingAddress[address1]'], '30 The Esplenade');
$I->fillField(['name' => 'shippingAddress[address2]'], 'Peppermint Grove');
$I->fillField(['name' => 'shippingAddress[city]'], 'Perth');
$I->fillField(['name' => 'shippingAddress[zipCode]'], '6011');
$I->fillField(['name' => 'shippingAddress[phone]'], '0435553357');
$I->fillField(['name' => 'shippingAddress[alternativePhone]'], '0437293021');
$I->selectOption('select[name="shippingAddress[countryId]"]', 'Australia');
$I->selectOption('select[name="shippingAddress[stateValue]"]', 'Western Australia');

$I->click('Confirm addresses');
$I->see('Cart updated.');

$I->selectOption('form input[name=shippingMethod]', 'Free Shipping');
$I->click('Select Shipping Method');

$I->see('Cart updated.');

$I->selectOption('form select[name=paymentMethodId]', 'Dummy');
$I->fillField(['name' => 'firstName'], 'Luke'); 
$I->fillField(['name' => 'lastName'], 'Holder');
$I->fillField(['name' => 'number'], '4242424242424242');
$I->fillField(['name' => 'cvv'], '123');
$I->click('Pay');
$I->amOnPage('/commerce/customer/order')


?>