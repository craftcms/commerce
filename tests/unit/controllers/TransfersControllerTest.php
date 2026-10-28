<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\controllers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\controllers\TransfersController;
use craft\commerce\Plugin;
use craft\web\Request;
use UnitTester;
use yii\base\InvalidRouteException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * TransfersControllerTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.7
 */
class TransfersControllerTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var TransfersController
     */
    protected TransfersController $controller;

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

        $this->controller = new TransfersController('transfers', Plugin::getInstance());
        $this->request = Craft::$app->getRequest();
        $this->request->enableCsrfValidation = false;
        $this->request->headers->set('Accept', 'application/json');
        $this->request->headers->set('X-Http-Method-Override', 'POST');
    }

    /**
     * @return void
     * @throws InvalidRouteException
     */
    public function testMarkAsPendingRejectsArrayTransferId(): void
    {
        $this->request->setBodyParams([
            'transferId' => ['id' => 1, 'where' => '1=1'],
        ]);

        $this->expectException(BadRequestHttpException::class);
        $this->controller->runAction('mark-as-pending');
    }

    /**
     * @return void
     * @throws InvalidRouteException
     */
    public function testMarkAsPendingThrowsNotFoundForUnknownTransfer(): void
    {
        $this->request->setBodyParams([
            'transferId' => 999999999,
        ]);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->runAction('mark-as-pending');
    }
}
