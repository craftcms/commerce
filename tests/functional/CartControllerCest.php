<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace commercetests\functional;

use Craft;
use craft\commerce\controllers\CartController;
use craft\elements\User;
use FunctionalTester;

/**
 * Cart Controller Test
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class CartControllerCest
{
    /**
     * @var string
     */
    public $cpTrigger;

    /**
     * @var
     */
    public $currentUser;

    /**
     * @param FunctionalTester $I
     */
    public function _before(FunctionalTester $I)
    {
        // $this->currentUser = User::find()
        //     ->admin()
        //     ->one();
        //
        // $I->amLoggedInAs($this->currentUser);
        // $this->cpTrigger = Craft::$app->getConfig()->getGeneral()->cpTrigger;
    }

    /**
     * @param \FunctionalTester $I
     */
    public function testActionGetCart(FunctionalTester $I)
    {
        $I->haveHttpHeader('Accept', 'application/json');
        $I->sendGET('?action=commerce/cart/get-cart', []);
    }

    public function testActionUpdateCart(FunctionalTester $I)
    {
        $I->sendPost('?action=commerce/cart/update-cart', []);
        $response = $I->grabResponse();
    }
}
