<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Order Status Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatusesController extends BaseAdminController
{
    public function actionIndex(): Response
    {
        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();

        return $this->renderTemplate('commerce/settings/orderstatuses/index', compact('orderStatuses'));
    }

    /**
     * @param int|null $id
     * @param OrderStatus|null $orderStatus
     * @throws HttpException
     */
    public function actionEdit(int $id = null, OrderStatus $orderStatus = null): Response
    {
        $variables = compact('id', 'orderStatus');

        if (!$variables['orderStatus']) {
            if ($variables['id']) {
                $variables['orderStatus'] = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($variables['id']);

                if (!$variables['orderStatus']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['orderStatus'] = new OrderStatus();
            }
        }

        if ($variables['orderStatus']->id) {
            $variables['title'] = $variables['orderStatus']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new order status');
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['orderStatus'], prepend: true);

        $emails = Plugin::getInstance()->getEmails()->getAllEmails();
        $variables['emails'] = ArrayHelper::map($emails, 'id', 'name');

        return $this->renderTemplate('commerce/settings/orderstatuses/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): void
    {
        $this->requirePostRequest();

        $id = $this->request->getBodyParam('id');
        $orderStatus = $id ? Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($id) : false;

        if (!$orderStatus) {
            $orderStatus = new OrderStatus();
        }

        $orderStatus->name = $this->request->getBodyParam('name');
        $orderStatus->handle = $this->request->getBodyParam('handle');
        $orderStatus->color = $this->request->getBodyParam('color');
        $orderStatus->description = $this->request->getBodyParam('description');
        $orderStatus->default = (bool)$this->request->getBodyParam('default');
        $emailIds = $this->request->getBodyParam('emails', []);

        if (!$emailIds) {
            $emailIds = [];
        }

        // Save it
        if (Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($orderStatus, $emailIds)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Order status saved.'));
            $this->redirectToPostedUrl($orderStatus);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save order status.'));
        }

        Craft::$app->getUrlManager()->setRouteParams(compact('orderStatus', 'emailIds'));
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws ErrorException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $ids = Json::decode($this->request->getRequiredBodyParam('ids'));

        if (!Plugin::getInstance()->getOrderStatuses()->reorderOrderStatuses($ids)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t reorder Order Statuses.'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws Throwable
     * @throws BadRequestHttpException
     * @since 2.2
     */
    public function actionDelete(): ?Response
    {
        $this->requireAcceptsJson();

        $orderStatusId = $this->request->getRequiredParam('id');

        if (!Plugin::getInstance()->getOrderStatuses()->deleteOrderStatusById((int)$orderStatusId)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t archive Order Status.'));
        }

        return $this->asSuccess();
    }
}
