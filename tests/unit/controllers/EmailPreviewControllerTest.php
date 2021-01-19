<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\controllers\EmailPreviewController;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\web\Request;
use craftcommercetests\fixtures\EmailsFixture;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;
use yii\web\Response;

/**
 * EmailPreviewControllerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class EmailPreviewControllerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var  EmailPreviewController
     */
    protected $controller;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'emails' => [
                'class' => EmailsFixture::class,
            ],
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _before()
    {
        parent::_before();

        // Mock admin user
        Craft::$app->getUser()->setIdentity(
            Craft::$app->getUsers()->getUserById('1')
        );
        Craft::$app->getUser()->getIdentity()->password = '$2y$13$tAtJfYFSRrnOkIbkruGGEu7TPh0Ixvxq0r.XgWqIgNWuWpxpA7SxK';

        $this->controller = new EmailPreviewController('orders', Plugin::getInstance());
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;
    }

    protected function _after()
    {
        parent::_after();

        // TODO figure out the bug where the record is being removed twice
        $this->tester->mockCraftMethods('projectConfig', ['remove' => function($string) {}]);
    }

    public function testRenderRandomOrder()
    {
        $email = $this->tester->grabFixture('emails')['order-confirmation'];
        Craft::$app->getRequest()->setQueryParams(['emailId' => $email['id']]);

        $response = $this->controller->runAction('render');

        self::assertInstanceOf(Response::class, $response);
        self::assertIsString($response->data);
        self::assertContains('<title>Order Confirmation</title>', $response->data);
        self::assertRegExp('/<h1>Order Confirmation [0-9a-zA-Z]{7}<\/h1>/', $response->data);
    }

    public function testRenderSpecificOrder()
    {
        $email = $this->tester->grabFixture('emails')['order-confirmation'];
        /** @var Order $order */
        $order = $this->tester->grabFixture('orders')['completed-new'];

        Craft::$app->getRequest()->setQueryParams([
            'emailId' => $email['id'],
            'orderNumber' => $order['number'],
        ]);

        $response = $this->controller->runAction('render');

        self::assertInstanceOf(Response::class, $response);
        self::assertIsString($response->data);
        self::assertContains('<title>Order Confirmation</title>', $response->data);
        self::assertContains('<h1>Order Confirmation ' . substr($order['number'], 0, 7) . '</h1>', $response->data);
    }
}