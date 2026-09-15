<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\controllers\PaymentSourcesController;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin;
use craft\commerce\services\PaymentSources;
use craft\elements\User;
use craft\web\Request;
use craftcommercetests\fixtures\CustomerFixture;
use UnitTester;

/**
 * PaymentSourcesControllerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PaymentSourcesControllerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var PaymentSourcesController
     */
    protected PaymentSourcesController $controller;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @var PaymentSources
     */
    private PaymentSources $_originalPaymentSourcesService;

    public function _fixtures(): array
    {
        return [
            'customer' => [
                'class' => CustomerFixture::class,
            ],
        ];
    }

    protected function _before(): void
    {
        parent::_before();

        $this->controller = new PaymentSourcesController('payment-sources', Plugin::getInstance());
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;

        $this->_originalPaymentSourcesService = Plugin::getInstance()->getPaymentSources();
    }

    protected function _after(): void
    {
        Plugin::getInstance()->set('paymentSources', $this->_originalPaymentSourcesService);
        parent::_after();
    }

    private function _makePaymentSource(int $customerId): PaymentSource
    {
        $paymentSource = new PaymentSource();
        $paymentSource->id = 999;
        $paymentSource->customerId = $customerId;
        $paymentSource->gatewayId = 1;
        $paymentSource->token = 'tok_test';
        $paymentSource->description = 'Test payment source';
        $paymentSource->response = '';

        return $paymentSource;
    }

    /**
     * A user with `commerce-editOrders` but not Craft's `editUsers` permission must not be
     * able to delete another customer's payment source.
     */
    public function testDeleteDeniedForNonOwnerWithoutEditUsersPermission(): void
    {
        $this->_assertDeleteDenied(['commerce-manageOrders', 'commerce-editOrders']);
    }

    /**
     * A user with Craft's `editUsers`/`viewUsers` permissions but neither `commerce-editOrders`
     * nor `commerce-deleteOrders` must not be able to delete another customer's payment source.
     */
    public function testDeleteDeniedForNonOwnerWithoutOrderPermission(): void
    {
        $this->_assertDeleteDenied(['viewUsers', 'editUsers', 'commerce-manageOrders']);
    }

    /**
     * A user with `commerce-editOrders` and Craft's `editUsers` permission is allowed to
     * delete another customer's payment source.
     */
    public function testDeleteAllowedForNonOwnerWithEditOrdersAndEditUsersPermissions(): void
    {
        $this->_assertDeleteAllowed(['commerce-manageOrders', 'commerce-editOrders', 'viewUsers', 'editUsers']);
    }

    /**
     * A user with `commerce-deleteOrders` and Craft's `editUsers` permission is allowed to
     * delete another customer's payment source.
     */
    public function testDeleteAllowedForNonOwnerWithDeleteOrdersAndEditUsersPermissions(): void
    {
        $this->_assertDeleteAllowed(['commerce-manageOrders', 'commerce-deleteOrders', 'viewUsers', 'editUsers']);
    }

    private function _assertDeleteAllowed(array $operatorPermissions): void
    {
        /** @var User $owner */
        $owner = $this->tester->grabFixture('customer')->getElement('customer1');
        /** @var User $operator */
        $operator = $this->tester->grabFixture('customer')->getElement('customer2');

        Craft::$app->getUserPermissions()->saveUserPermissions($operator->id, $operatorPermissions);

        $paymentSource = $this->_makePaymentSource($owner->id);

        $paymentSourcesService = $this->make(PaymentSources::class, [
            'getPaymentSourceById' => fn() => $paymentSource,
            'deletePaymentSourceById' => fn() => true,
        ]);
        Plugin::getInstance()->set('paymentSources', $paymentSourcesService);

        Craft::$app->getUser()->setIdentity($operator);

        $this->request->headers->set('Accept', 'application/json');
        $this->request->headers->set('X-Http-Method-Override', 'POST');
        $this->request->setBodyParams(['id' => $paymentSource->id]);

        $response = $this->controller->runAction('delete');

        self::assertNotNull($response);
        self::assertSame(200, $response->statusCode);
        self::assertSame('Payment source deleted.', $response->data['message']);
    }

    private function _assertDeleteDenied(array $operatorPermissions): void
    {
        /** @var User $owner */
        $owner = $this->tester->grabFixture('customer')->getElement('customer1');
        /** @var User $operator */
        $operator = $this->tester->grabFixture('customer')->getElement('customer2');

        Craft::$app->getUserPermissions()->saveUserPermissions($operator->id, $operatorPermissions);

        $paymentSource = $this->_makePaymentSource($owner->id);

        $paymentSourcesService = $this->make(PaymentSources::class, [
            'getPaymentSourceById' => fn() => $paymentSource,
            'deletePaymentSourceById' => function() {
                self::fail('deletePaymentSourceById() should not be called when the operator lacks the full permission set.');
            },
        ]);
        Plugin::getInstance()->set('paymentSources', $paymentSourcesService);

        Craft::$app->getUser()->setIdentity($operator);

        $this->request->headers->set('X-Http-Method-Override', 'POST');
        $this->request->setBodyParams(['id' => $paymentSource->id]);

        $response = $this->controller->runAction('delete');

        self::assertNull($response);
    }
}
