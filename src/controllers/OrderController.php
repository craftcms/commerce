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
 * @since 2.0
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
        $data = Craft::$app->getRequest()->getRawBody();
        $data = Json::decodeIfJson($data);

        if (!isset($data['order']['id'])) {
            return $this->asErrorJson(Craft::t('commerce', 'Missing order.'));
        }

        $order = Order::find()->id($data['order']['id'])->one();

        if (!$order) {
            return $this->asErrorJson(Craft::t('commerce', 'No order found with ID: {id}', ['id' => $data['order']['id']]));
        }

        $lineItems = [];
        foreach ($data['lineItems'] as $lineItem) {
            $lineItemId =  $lineItem['id'] ?? null;
            $note = $lineItem['note'] ?? '';
            $purchasableId = $lineItem['purchasableId'];
            $options = $lineItem['options'] ?? [];
            $qty = $lineItem['qty'] ?? 1;

            $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

            if (!$lineItem){
                $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($order->id, $purchasableId, $options, $qty, $note);
            }

            $lineItem->purchasableId = $purchasableId;
            $lineItem->qty = $qty;
            $lineItem->note = $note;
            $lineItem->setOptions($options);

            if ($qty !== null || $qty == 0) {
                $lineItems[] = $lineItem;
            }
        }

        $order->setLineItems($lineItems);
        $order->recalculate();

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
        $orderAttributes = $order->attributes();
        if ($order::hasContent() && ($fieldLayout = $order->getFieldLayout()) !== null) {
            foreach ($fieldLayout->getFields() as $field) {
                /** @var Field $field */
                ArrayHelper::removeValue($orderAttributes, $field->handle);
            }
        }

        // Remove unneeded fields
        ArrayHelper::removeValue($orderAttributes, 'hasDescendants');
        ArrayHelper::removeValue($orderAttributes, 'makePrimaryShippingAddress');
        ArrayHelper::removeValue($orderAttributes, 'shippingSameAsBilling');
        ArrayHelper::removeValue($orderAttributes, 'billingSameAsShipping');
        ArrayHelper::removeValue($orderAttributes, 'registerUserOnOrderComplete');
        ArrayHelper::removeValue($orderAttributes, 'tempId');
        ArrayHelper::removeValue($orderAttributes, 'resaving');
        ArrayHelper::removeValue($orderAttributes, 'duplicateOf');
        ArrayHelper::removeValue($orderAttributes, 'totalDescendants');
        ArrayHelper::removeValue($orderAttributes, 'fieldLayoutId');
        ArrayHelper::removeValue($orderAttributes, 'contentId');

        $extraFields = [
            'billingAddress', 'shippingAddress'
        ];

        $data['order'] = $order->toArray($orderAttributes, $extraFields);
    }
}
