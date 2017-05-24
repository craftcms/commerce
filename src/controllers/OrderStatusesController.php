<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use yii\web\HttpException;

/**
 * Class Order Status Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class OrderStatusesController extends BaseAdminController
{
    /**
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionIndex(array $variables = [])
    {
        $variables['orderStatuses'] = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();

        $this->renderTemplate('commerce/settings/orderstatuses/index', $variables);
    }


    /**
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['orderStatus'])) {
            if (!empty($variables['id'])) {
                $variables['orderStatus'] = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($variables['id']);
                $variables['orderStatusId'] = $variables['orderStatus'];
                if (!$variables['orderStatus']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['orderStatus'] = new OrderStatus();
            }
        }

        if (!empty($variables['orderStatusId'])) {
            $variables['title'] = $variables['orderStatus']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new order status');
        }

        $emails = Plugin::getInstance()->getEmails()->getAllEmails();
        $variables['emails'] = ArrayHelper::map($emails, 'id', 'name');

        $this->renderTemplate('commerce/settings/orderstatuses/_edit',
            $variables);
    }

    /**
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getParam('orderStatusId');
        $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($id);

        if (!$orderStatus) {
            $orderStatus = new OrderStatus();
        }

        $orderStatus->name = Craft::$app->getRequest()->getParam('name');
        $orderStatus->handle = Craft::$app->getRequest()->getParam('handle');
        $orderStatus->color = Craft::$app->getRequest()->getParam('color');
        $orderStatus->default = Craft::$app->getRequest()->getParam('default');
        $emailIds = Craft::$app->getRequest()->getParam('emails', []);

        if (!$emailIds) {
            $emailIds = [];
        }

        // Save it
        if (Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($orderStatus, $emailIds)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Order status saved.'));
            $this->redirectToPostedUrl($orderStatus);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save order status.'));
        }

        Craft::$app->getUrlManager()->setRouteParams(compact('orderStatus', 'emailIds'));
    }

    /**
     * @throws HttpException
     */
    public function actionReorder()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredParam('ids'));
        if ($success = Plugin::getInstance()->getOrderStatuses()->reorderOrderStatuses($ids)) {
            return $this->asJson(['success' => $success]);
        };

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder Order Statuses.')]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requireAcceptsJson();

        $orderStatusId = Craft::$app->getRequest()->getRequiredParam('id');

        if (Plugin::getInstance()->getOrderStatuses()->deleteOrderStatusById($orderStatusId)) {
            $this->asJson(['success' => true]);
        };
    }

}
