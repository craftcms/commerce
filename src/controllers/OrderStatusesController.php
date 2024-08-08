<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\Email;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\db\Query;
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
        $orderStatuses = [];
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        $stores->each(function(Store $store) use (&$orderStatuses) {
            $orderStatuses[$store->handle] = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($store->id);
        });
        $stores = $stores->all();

        return $this->renderTemplate('commerce/settings/orderstatuses/index', compact('orderStatuses', 'stores'));
    }

    /**
     * @param int|null $id
     * @param OrderStatus|null $orderStatus
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, OrderStatus $orderStatus = null): Response
    {
        $variables = compact('id', 'orderStatus');
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        if (!$variables['orderStatus']) {
            if ($variables['id']) {
                $variables['orderStatus'] = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($variables['id'], $store->id);

                if (!$variables['orderStatus']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['orderStatus'] = Craft::createObject([
                    'class' => OrderStatus::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        $variables['statusColors'] = ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'];

        if ($variables['orderStatus']->id) {
            $variables['title'] = $variables['orderStatus']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new order status');

            $statusColors = $variables['statusColors'];
            Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($store->id)->each(function(OrderStatus $status) use (&$statusColors) {
                $key = array_search($status->color, $statusColors, true);
                if ($key !== false) {
                    unset($statusColors[$key]);
                }
            });

            $variables['nextAvailableColor'] = !empty($statusColors) ? array_shift($statusColors) : 'green';
        }

        DebugPanel::prependOrAppendModelTab(model: $variables['orderStatus'], prepend: true);

        $variables['emails'] = Plugin::getInstance()->getEmails()->getAllEmails($store->id)->mapWithKeys(fn(Email $email) => [$email->id => $email->name])->all();

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
        $storeId = $this->request->getBodyParam('storeId');
        $orderStatus = $id ? Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($id, $storeId) : false;

        if (!$orderStatus) {
            $orderStatus = new OrderStatus();
        }

        $orderStatus->storeId = $storeId;
        $orderStatus->name = $this->request->getBodyParam('name');
        $orderStatus->handle = $this->request->getBodyParam('handle');
        $orderStatus->color = $this->request->getBodyParam('color');
        $orderStatus->description = $this->request->getBodyParam('description');
        $orderStatus->default = (bool)$this->request->getBodyParam('default');
        $emailIds = $this->request->getBodyParam('emails', []);

        if (!$emailIds) {
            $emailIds = [];
        }

        if (!$id) {
            $orderStatus->sortOrder = (new Query())
                    ->from(Table::ORDERSTATUSES)
                    ->where(['storeId' => $storeId])
                    ->max("[[sortOrder]]") + 1;
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
     * Returns the order statuses for a store based on the current user.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @since 5.0.0
     */
    public function actionGetOrderStatuses(): Response
    {
        $this->requireAcceptsJson();

        $storeId = $this->request->getRequiredParam('storeId');
        $store = Plugin::getInstance()->getStores()->getStoreById($storeId);
        $allowableStoreIds = Plugin::getInstance()->getStores()->getStoresByUserId(Craft::$app->getUser()->id)->map(fn(Store $s) => $s->id)->all();

        if (!$store || !in_array($store->id, $allowableStoreIds)) {
            return $this->asFailure(Craft::t('commerce', 'Invalid store.'));
        }

        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($storeId)->all();

        return $this->asSuccess(data: compact('orderStatuses'));
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

        if (!$orderStatusId) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t archive Order Status.'));
        }

        $storeId = (new Query())->from(Table::ORDERSTATUSES)->select(['storeId'])->where(['id' => $orderStatusId])->scalar();

        if (!$storeId || !Plugin::getInstance()->getOrderStatuses()->deleteOrderStatusById((int)$orderStatusId, $storeId)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t archive Order Status.'));
        }

        return $this->asSuccess();
    }
}
