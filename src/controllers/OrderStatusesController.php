<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Order Status Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatusesController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses();

        return $this->renderTemplate('commerce/settings/orderstatuses/index', compact('orderStatuses'));
    }

    /**
     * @param int|null $id
     * @param OrderStatus|null $orderStatus
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, OrderStatus $orderStatus = null): Response
    {
        $variables = [
            'id' => $id,
            'orderStatus' => $orderStatus
        ];

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

        $emails = Plugin::getInstance()->getEmails()->getAllEmails();
        $variables['emails'] = ArrayHelper::map($emails, 'id', 'name');

        return $this->renderTemplate('commerce/settings/orderstatuses/_edit', $variables);
    }

    public function actionSave()
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getBodyParam('id');
        $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($id);

        if (!$orderStatus) {
            $orderStatus = new OrderStatus();
        }

        $orderStatus->name = Craft::$app->getRequest()->getBodyParam('name');
        $orderStatus->handle = Craft::$app->getRequest()->getBodyParam('handle');
        $orderStatus->color = Craft::$app->getRequest()->getBodyParam('color');
        $orderStatus->default = (bool)Craft::$app->getRequest()->getBodyParam('default');
        $emailIds = Craft::$app->getRequest()->getBodyParam('emails', []);

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
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        if ($success = Plugin::getInstance()->getOrderStatuses()->reorderOrderStatuses($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder Order Statuses.')]);
    }

    /**
     * @return Response|null
     */
    public function actionArchive()
    {
        $this->requireAcceptsJson();

        $orderStatusId = Craft::$app->getRequest()->getRequiredParam('id');

        if (Plugin::getInstance()->getOrderStatuses()->archiveOrderStatusById((int) $orderStatusId)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t archive Order Status.')]);
    }
}
