<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\controllers\InventoryLocationsController;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\web\Request;
use UnitTester;

/**
 * InventoryLocationsControllerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.7
 */
class InventoryLocationsControllerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var InventoryLocationsController
     */
    protected InventoryLocationsController $controller;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @inheritDoc
     */
    protected function _before(): void
    {
        parent::_before();

        Craft::$app->getUser()->setIdentity(
            Craft::$app->getUsers()->getUserById('1')
        );
        Craft::$app->getUser()->getIdentity()->password = '$2y$13$tAtJfYFSRrnOkIbkruGGEu7TPh0Ixvxq0r.XgWqIgNWuWpxpA7SxK';

        $this->controller = new InventoryLocationsController('inventory-locations', Plugin::getInstance());
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;
    }

    public function testSaveIgnoresPostedAddressId(): void
    {
        $firstLocation = $this->_saveLocation('Test Location One', 'testLocationOne', 'First Street');
        $firstAddressId = $firstLocation->addressId;
        self::assertNotNull($firstAddressId);

        $secondLocation = $this->_saveLocation('Test Location Two', 'testLocationTwo', 'Second Street', $firstAddressId);

        self::assertNotSame($firstAddressId, $secondLocation->addressId);

        $firstAddress = Craft::$app->getElements()->getElementById($firstAddressId, Address::class);
        self::assertSame('First Street', $firstAddress->addressLine1);
    }

    private function _saveLocation(string $name, string $handle, string $addressLine1, ?int $postedAddressId = null): \craft\commerce\models\InventoryLocation
    {
        $this->request->getHeaders()->set('Accept', 'application/json');
        $this->request->getHeaders()->set('X-Http-Method-Override', 'POST');
        $this->request->setBodyParams([
            'inventoryLocationAddress' => [
                'name' => $name,
                'handle' => $handle,
                'id' => $postedAddressId,
                'addressLine1' => $addressLine1,
                'countryCode' => 'US',
            ],
        ]);

        $response = $this->controller->runAction('save');

        self::assertEquals(200, $response->statusCode);

        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocationByHandle($handle);
    }
}
