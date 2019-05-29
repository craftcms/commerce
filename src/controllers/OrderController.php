<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Field;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\Controller;
use Throwable;
use yii\base\Exception;
use yii\web\Response;

/**
 * Class Order Editor Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.2
 */
class OrderController extends Controller
{
    public $enableCsrfValidation = false;
    public $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    public function actionGet($orderId = null)
    {
        // The response
        $data = [];

        if (!$orderId) {
            return $this->asErrorJson(Craft::t('commerce', 'Missing order ID'));
        }

        $order = Order::find()->id($orderId)->one();

        if (!$order) {
            $order = new Order([
                'number' => Plugin::getInstance()->getCarts()->generateCartNumber()
            ]);

            Craft::$app->getElements()->saveElement($order);
        }

        $this->_addOrderToData($order, $data);
        $this->_addMetaToData($data);

        return $this->asJson($data);
    }

    /**
     * @return Response
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function actionSave()
    {

    }

    /**
     * @return Response
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function actionRecalculate()
    {
        $data = Craft::$app->getRequest()->getRawBody();
        $data = Json::decodeIfJson($data);

        if (!isset($data['order']['id'])) {
            return $this->asErrorJson(Craft::t('commerce', 'Missing order.'));
        }

        /** @var Order $order */
        $order = Order::find()->id($data['order']['id'])->one();

        if (!$order) {
            return $this->asErrorJson(Craft::t('commerce', 'No order found with ID: {id}', ['id' => $data['order']['id']]));
        }

        $order->setRecalculationMode($data['order']['recalculationMode']);

        $lineItems = [];
        $adjustments = [];

        foreach ($data['order']['lineItems'] as $lineItem) {
            $lineItemId = $lineItem['id'] ?? null;
            $note = $lineItem['note'] ?? '';
            $adminNote = $lineItem['adminNote'] ?? '';
            $purchasableId = $lineItem['purchasableId'];
            $lineItemStatusId = $lineItem['lineItemStatusId'];
            $options = $lineItem['options'] ?? [];
            $qty = $lineItem['qty'] ?? 1;

            $lineItemModel = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

            if (!$lineItemModel) {
                $lineItemModel = Plugin::getInstance()->getLineItems()->createLineItem($order->id, $purchasableId, $options, $qty, $note);
            }

            if ($purchasable = Craft::$app->getElements()->getElementById($purchasableId)) {
                $lineItemModel->setPurchasable($purchasable);
                if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
                    $lineItemModel->refreshFromPurchasable();
                }
            }

            $lineItemModel->purchasableId = $purchasableId;
            $lineItemModel->qty = $qty;
            $lineItemModel->note = $note;
            $lineItemModel->adminNote = $adminNote;
            $lineItemModel->lineItemStatusId = $lineItemStatusId;

            if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {
                $lineItemModel->salePrice = $lineItem['salePrice'];
                $lineItemModel->saleAmount = $lineItem['salePrice'] - $lineItemModel->price;
            }

            $lineItemModel->setOptions($options);

            if ($qty !== null || $qty == 0) {
                $lineItems[] = $lineItemModel;
            }

            if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {

                foreach ($lineItem['adjustments'] as $adjustment) {
                    $amount = $adjustment['amount'];
                    $id = $adjustment['id'];
                    $type = $adjustment['type'];
                    $name = $adjustment['name'];
                    $description = $adjustment['description'];
                    $included = $adjustment['included'];

                    $adjustment = null;
                    if ($id) {
                        $adjustment = Plugin::getInstance()->getOrderAdjustments()->getOrderAdjustmentById($id);
                    }
                    if($adjustment === null)
                    {
                        $adjustment = new OrderAdjustment();
                    }

                    $adjustment->setOrder($order);
                    $adjustment->setLineItem($lineItemModel);
                    $adjustment->amount = $amount;
                    $adjustment->type = $type;
                    $adjustment->name = $name;
                    $adjustment->description = $description;
                    $adjustment->included = $included;

                    $adjustments[] = $adjustment;
                }

                $order->setAdjustments($adjustments);
            }


        }

        $order->setLineItems($lineItems);

        if ($order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {

            foreach ($data['order']['orderAdjustments'] as $adjustment) {
                $amount = $adjustment['amount'];
                $id = $adjustment['id'];
                $type = $adjustment['type'];
                $name = $adjustment['name'];
                $description = $adjustment['description'];
                $included = $adjustment['included'];

                $adjustment = null;
                if ($id) {
                    $adjustment = Plugin::getInstance()->getOrderAdjustments()->getOrderAdjustmentById($id);
                }
                if($adjustment === null)
                {
                    $adjustment = new OrderAdjustment();
                }

                $adjustment->setOrder($order);
                $adjustment->amount = $amount;
                $adjustment->type = $type;
                $adjustment->name = $name;
                $adjustment->description = $description;
                $adjustment->included = $included;

                $adjustments[] = $adjustment;
            }

            $order->setAdjustments($adjustments);
        }

        if ($order->validate() && $order->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
            $order->recalculate();
        }

        $order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);

        $this->_addOrderToData($order, $data);

        return $this->asJson($data);
    }

    /**
     * @param array $data
     */
    private function _addMetaToData(array &$data)
    {
        // Add meta data
        $data['meta'] = [];
        $data['meta']['edition'] = Plugin::getInstance()->is(Plugin::EDITION_LITE) ? Plugin::EDITION_LITE : Plugin::EDITION_PRO;
    }

    /**
     * @param Order $order
     * @param array $data
     */
    private function _addOrderToData(Order $order, array &$data)
    {

        // Remove custom fields
        $orderFields = array_keys($order->fields());

        if ($order::hasContent() && ($fieldLayout = $order->getFieldLayout()) !== null) {
            foreach ($fieldLayout->getFields() as $field) {
                /** @var Field $field */
                ArrayHelper::removeValue($orderFields, $field->handle);
            }
        }

        $extraFields = $order->extraFields();
        $data['order'] = $order->toArray($orderFields, $extraFields);

        if ($order->hasErrors()) {
            $data['order']['errors'] = $order->getErrors();
            $data['errors'] = [];
            $data['errors']['order'] = Craft::t('commerce', 'The order is not in valid state.');
        }
    }
}
