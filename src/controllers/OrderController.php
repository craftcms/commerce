<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\commerce\elements\Order;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\Controller;
use JsonSchema\Validator;
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
    public $allowAnonymous = true;

    private $_responseData;
    /**
     * @var Order
     */
    private $_order;

    // Public Methods
    // =========================================================================

    public function actionGet($orderId = null)
    {
        if (!$orderId) {
            return $this->asErrorJson(Craft::t('commerce', 'Missing order ID'));
        }

        $this->_order = Order::find()->id($orderId)->one();

        if (!$this->_order) {
            return $this->asErrorJson(Craft::t('commerce', 'No order found.'));
        }

        $this->_addOrderToData();

        return $this->asJson($this->_responseData);
    }

    /**
     * @return Response
     * @throws Throwable
     * @throws Exception
     */
    public function actionSave()
    {
        $data = Craft::$app->getRequest()->getRawBody();
        $this->_responseData = Json::decodeIfJson($data);

        // Are there any json data schema errors
        $errors = $this->_validateJson(Json::decodeIfJson($data, false));
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $attribute = preg_replace('/lineItems\[(\d*)]./', 'lineItems.$1.', $error['property']);
                $attribute = preg_replace('/adjustments\[(\d*)]./', 'adjustments.$1.', $attribute);
                $attribute = preg_replace('/orderAdjustments\[(\d*)]./', 'orderAdjustments.$1.', $attribute);
                $this->_responseData['order']['errors'][$attribute] = $error['message'];
                $this->_responseData['error'] = Craft::t('commerce', 'Errors found on the order.');
            }

            return $this->asJson($this->_responseData);
        }

        $this->_processOrder();
        $this->_setLineItemsAndAdjustments();

        $this->_order->setScenario(Element::SCENARIO_LIVE);
        $this->_order->setFieldValuesFromRequest('fields');

        if ($this->_order->validate()) {
            Craft::$app->getElements()->saveElement($this->_order);
        }

        // Set the recalculation mode for the response.
        $this->_order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        if (!$this->_order->isCompleted) {
            $this->_order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
        }

        $this->_addOrderToData();

        return $this->asJson($this->_responseData);
    }

    /**
     * @return Response
     * @throws Throwable
     * @throws Exception
     */
    public function actionRecalculate()
    {
        $data = Craft::$app->getRequest()->getRawBody();
        $this->_responseData = Json::decodeIfJson($data);

        // Are there any json data schema errors
        $errors = $this->_validateJson(Json::decodeIfJson($data, false));
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $attribute = preg_replace('/lineItems\[(\d*)]./', 'lineItems.$1.', $error['property']);
                $attribute = preg_replace('/adjustments\[(\d*)]./', 'adjustments.$1.', $attribute);
                $attribute = preg_replace('/orderAdjustments\[(\d*)]./', 'orderAdjustments.$1.', $attribute);
                $this->_responseData['order']['errors'][$attribute] = $error['message'];
                $this->_responseData['error'] = Craft::t('commerce', 'Errors found on the order.');
            }

            return $this->asJson($this->_responseData);
        }

        $this->_processOrder();
        $this->_setLineItemsAndAdjustments();

        if ($this->_order->validate() && $this->_order->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
            $this->_order->recalculate(); // dont save just recalc
        }

        // Set the recalculation mode for the response.
        $this->_order->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        if (!$this->_order->isCompleted) {
            $this->_order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
        }

        $this->_addOrderToData();

        return $this->asJson($this->_responseData);
    }

    /**
     * @param Order $order
     */
    private function _addOrderToData()
    {
        // Remove custom fields
        $orderFields = array_keys($this->_order->fields());

        sort($orderFields);

        // Remove unneeded fields
        ArrayHelper::removeValue($orderFields, 'hasDescendants');
        ArrayHelper::removeValue($orderFields, 'makePrimaryShippingAddress');
        ArrayHelper::removeValue($orderFields, 'shippingSameAsBilling');
        ArrayHelper::removeValue($orderFields, 'billingSameAsShipping');
        ArrayHelper::removeValue($orderFields, 'tempId');
        ArrayHelper::removeValue($orderFields, 'resaving');
        ArrayHelper::removeValue($orderFields, 'duplicateOf');
        ArrayHelper::removeValue($orderFields, 'totalDescendants');
        ArrayHelper::removeValue($orderFields, 'fieldLayoutId');
        ArrayHelper::removeValue($orderFields, 'contentId');
        ArrayHelper::removeValue($orderFields, 'trashed');
        ArrayHelper::removeValue($orderFields, 'structureId');
        ArrayHelper::removeValue($orderFields, 'url');
        ArrayHelper::removeValue($orderFields, 'ref');
        ArrayHelper::removeValue($orderFields, 'title');
        ArrayHelper::removeValue($orderFields, 'slug');

        if ($this->_order::hasContent() && ($fieldLayout = $this->_order->getFieldLayout()) !== null) {
            foreach ($fieldLayout->getFields() as $field) {
                /** @var Field $field */
                ArrayHelper::removeValue($orderFields, $field->handle);
            }
        }

        // Typecast order attributes
        $this->_order->typeCastAttributes();

        // Always recalculate if order is a cart
        if (!$this->_order->isCompleted) {
            $this->_order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
        }

        $extraFields = ['lineItems.snapshot'];
        $this->_responseData['order'] = $this->_order->toArray($orderFields, $extraFields);

        if ($this->_order->hasErrors()) {
            $this->_responseData['order']['errors'] = $this->_order->getErrors();
            $this->_responseData['error'] = Craft::t('commerce', 'The order is not valid.');
        }
    }

    private function _processOrder()
    {
        if (!isset($this->_responseData['order']['id'])) {
            return $this->asErrorJson(Craft::t('commerce', 'Missing order.'));
        }

        /** @var Order $order */
        $this->_order = Order::find()->id($this->_responseData['order']['id'])->one();

        if (!$this->_order) {
            return $this->asErrorJson(Craft::t('commerce', 'No order found with ID: {id}', ['id' => $this->_responseData['order']['id']]));
        }

        $this->_order->setRecalculationMode($this->_responseData['order']['recalculationMode']);
    }

    private function _setLineItemsAndAdjustments()
    {
        $lineItems = [];
        $adjustments = [];

        foreach ($this->_responseData['order']['lineItems'] as $lineItem) {
            $lineItemId = $lineItem['id'] ?? null;
            $note = $lineItem['note'] ?? '';
            $adminNote = $lineItem['adminNote'] ?? '';
            $purchasableId = $lineItem['purchasableId'];
            $lineItemStatusId = $lineItem['lineItemStatusId'];
            $options = $lineItem['options'] ?? [];
            $qty = $lineItem['qty'] ?? 1;

            $lineItemModel = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

            if (!$lineItemModel) {
                $lineItemModel = Plugin::getInstance()->getLineItems()->createLineItem($this->_order->id, $purchasableId, $options, $qty, $note);
            }

            if ($purchasable = Craft::$app->getElements()->getElementById($purchasableId)) {
                $lineItemModel->setPurchasable($purchasable);
                if ($this->_order->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
                    $lineItemModel->refreshFromPurchasable();
                }
            }

            $lineItemModel->purchasableId = $purchasableId;
            $lineItemModel->qty = $qty;
            $lineItemModel->note = $note;
            $lineItemModel->adminNote = $adminNote;
            $lineItemModel->lineItemStatusId = $lineItemStatusId;

            if ($this->_order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {
                $lineItemModel->salePrice = $lineItem['salePrice'];
            }

            $lineItemModel->setOptions($options);

            if ($qty !== null && $qty > 0) {
                $lineItems[] = $lineItemModel;
            }

            if ($this->_order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {

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
                    if ($adjustment === null) {
                        $adjustment = new OrderAdjustment();
                    }

                    $adjustment->setOrder($this->_order);
                    $adjustment->setLineItem($lineItemModel);
                    $adjustment->amount = $amount;
                    $adjustment->type = $type;
                    $adjustment->name = $name;
                    $adjustment->description = $description;
                    $adjustment->included = $included;

                    $adjustments[] = $adjustment;
                }
            }
        }

        $this->_order->setLineItems($lineItems);

        if ($this->_order->getRecalculationMode() == Order::RECALCULATION_MODE_NONE) {

            foreach ($this->_responseData['order']['orderAdjustments'] as $adjustment) {
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
                if ($adjustment === null) {
                    $adjustment = new OrderAdjustment();
                }

                $adjustment->setOrder($this->_order);
                $adjustment->amount = $amount;
                $adjustment->type = $type;
                $adjustment->name = $name;
                $adjustment->description = $description;
                $adjustment->included = $included;

                $adjustments[] = $adjustment;
            }

            $this->_order->setAdjustments($adjustments);
        }
    }

    private function _validateJson($data)
    {
        $schemaJson = Json::decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'order-edit.json'));
        $validator = new Validator();
        $validator->validate($data, $schemaJson);

        if (!$validator->isValid()) {
            return $validator->getErrors();
        }

        return [];
    }

    private function _setOrderFromPost(): Order
    {
        $orderId = Craft::$app->getRequest()->getBodyParam('orderId');
        $order = Plugin::getInstance()->getOrders()->getOrderById($orderId);

        if (!$order) {
            throw new Exception(Craft::t('commerce', 'No order with the ID “{id}”', ['id' => $orderId]));
        }



        return $order;
    }
}
