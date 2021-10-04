<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\controllers\UserAddressesController;
use craft\commerce\models\Address as AddressModel;
use craft\commerce\Plugin;
use craft\commerce\records\Address;
use craft\web\Request;
use craftcommercetests\fixtures\UserAddressesFixture;
use UnitTester;

/**
 * CustomerAddressesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class UserAddressesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var  UserAddressesController
     */
    protected UserAddressesController $controller;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'user-addresses' => [
                'class' => UserAddressesFixture::class,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->controller = new UserAddressesController('user-addresses', Plugin::getInstance());
        // Mock admin user
        Craft::$app->getUser()->setIdentity(
            Craft::$app->getUsers()->getUserByUsernameOrEmail('customer1@crafttest.com')
        );
        Craft::$app->getUser()->getIdentity()->password = '$2y$13$tAtJfYFSRrnOkIbkruGGEu7TPh0Ixvxq0r.XgWqIgNWuWpxpA7SxK';
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;
    }

    public function testSaveAddress(): void
    {
        $this->request->headers->set('Accept', 'application/json');
        $this->request->headers->set('X-Http-Method-Override', 'POST');

        $address = Plugin::getInstance()->getAddresses()->getAddressById(1002);
        $this->request->setBodyParams([
            'address' => [
                'id' => $address->id,
                'address1' => '1 Apple Park Way'
            ]
        ]);

        $response = $this->controller->runAction('save');

        /** @var Address $savedAddress */
        $savedAddress = Address::find()->where(['id' => 1002])->one();

        self::assertEquals(200, $response->statusCode);
        self::assertArrayHasKey('address', $response->data);
        self::assertInstanceOf(AddressModel::class, $response->data['address']);
        self::assertEquals(1002, $response->data['address']->id);
        self::assertEquals(1002, $savedAddress->id);
        self::assertEquals('1 Apple Park Way', $savedAddress->address1);
    }
}