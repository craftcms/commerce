<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\base\Field;
use craft\commerce\elements\Order;
use craft\commerce\models\Customer;
use craft\events\ConfigEvent;
use craft\events\FieldEvent;
use craft\helpers\Json;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use yii\base\Component;

/**
 * Orders service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Orders extends Component
{
    const CONFIG_FIELDLAYOUT_KEY = 'commerce.orders.fieldLayouts';

    // Public Methods
    // =========================================================================

    /**
     * Handle field layout change
     *
     * @param ConfigEvent $event
     */
    public function handleChangedFieldLayout(ConfigEvent $event)
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty($config = reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Order::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Order::class)->id;
        $layout->type = Order::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout);
    }


    /**
     * Prune a deleted field from order field layouts.
     *
     * @param FieldEvent $event
     */
    public function pruneDeletedField(FieldEvent $event)
    {
        /** @var Field $field */
        $field = $event->field;
        $fieldUid = $field->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $layoutData = $projectConfig->get(self::CONFIG_FIELDLAYOUT_KEY);

        // Prune the UID from field layouts.
        if (is_array($layoutData)) {
            foreach ($layoutData as $layoutUid => $layout) {
                if (!empty($layout['tabs'])) {
                    foreach ($layout['tabs'] as $tabUid => $tab) {
                        $projectConfig->remove(self::CONFIG_FIELDLAYOUT_KEY . '.' . $layoutUid . '.tabs.' . $tabUid . '.fields.' . $fieldUid);
                    }
                }
            }
        }
    }

    /**
     * Handle field layout being deleted
     *
     * @param ConfigEvent $event
     */
    public function handleDeletedFieldLayout(ConfigEvent $event)
    {
        Craft::$app->getFields()->deleteLayoutsByType(Order::class);
    }

    /**
     * Get an order by its ID.
     *
     * @param int $id
     * @return Order|null
     */
    public function getOrderById(int $id)
    {
        if (!$id) {
            return null;
        }

        $query = Order::find();
        $query->id($id);
        $query->status(null);

        return $query->one();
    }

    /**
     * Get an order by its number.
     *
     * @param string $number
     * @return Order|null
     */
    public function getOrderByNumber($number)
    {
        $query = Order::find();
        $query->number($number);

        return $query->one();
    }

    /**
     * Get all orders by their customer.
     *
     * @param int|Customer $customer
     * @return Order[]|null
     */
    public function getOrdersByCustomer($customer)
    {
        $query = Order::find();
        if ($customer instanceof Customer) {
            $query->customer($customer);
        } else {
            $query->customerId($customer);
        }
        $query->isCompleted();
        $query->limit(null);

        return $query->all();
    }

    /**
     * Get all orders by their email.
     *
     * @param string $email
     * @return Order[]|null
     */
    public function getOrdersByEmail($email)
    {
        $query = Order::find();
        $query->email($email);
        $query->isCompleted();
        $query->limit(null);

        return $query->all();
    }

    /**
     * @param Order $cart
     * @return array
     * @deprecated 2.2 use `$order->toArray()` instead
     */
    public function cartArray($cart)
    {
        $data = [];
        $data['id'] = $cart->id;
        $data['number'] = $cart->number;
        $data['couponCode'] = $cart->couponCode;
        $data['itemTotal'] = $cart->getItemTotal();
        $data['itemSubtotal'] = $cart->getItemSubtotal();
        $data['totalPaid'] = $cart->getTotalPaid();
        $data['email'] = $cart->getEmail();
        $data['isCompleted'] = (bool)$cart->isCompleted;
        $data['dateOrdered'] = $cart->dateOrdered;
        $data['datePaid'] = $cart->datePaid;
        $data['currency'] = $cart->currency;
        $data['paymentCurrency'] = $cart->paymentCurrency;
        $data['lastIp'] = $cart->lastIp;
        $data['message'] = $cart->message;
        $data['returnUrl'] = $cart->returnUrl;
        $data['cancelUrl'] = $cart->cancelUrl;
        $data['orderStatusId'] = $cart->orderStatusId;
        $data['orderLanguage'] = $cart->orderLanguage;
        $data['shippingMethod'] = $cart->shippingMethodHandle;
        $data['shippingMethodId'] = $cart->getShippingMethodId();
        $data['paymentMethodId'] = $cart->gatewayId;
        $data['gatewayId'] = $cart->gatewayId;
        $data['paymentSourceId'] = $cart->paymentSourceId;
        $data['customerId'] = $cart->customerId;
        $data['isPaid'] = $cart->getIsPaid();
        $data['paidStatus'] = $cart->getPaidStatus();
        $data['totalQty'] = $cart->getTotalQty();
        $data['pdfUrl'] = UrlHelper::actionUrl("commerce/downloads/pdf?number={$cart->number}&option=ajax");
        $data['isEmpty'] = $cart->getIsEmpty();
        $data['itemSubtotal'] = $cart->getItemSubtotal();
        $data['totalWeight'] = $cart->getTotalWeight();
        $data['total'] = $cart->getTotal();
        $data['totalPrice'] = $cart->getTotalPrice();

        $availableShippingMethods = $cart->getAvailableShippingMethods();
        $data['availableShippingMethods'] = [];
        foreach ($availableShippingMethods as $shippingMethod) {
            $data['availableShippingMethods'][$shippingMethod->getHandle()] = $shippingMethod->toArray();
            $data['availableShippingMethods'][$shippingMethod->getHandle()]['price'] = $shippingMethod->getPriceForOrder($cart);
        }

        $data['shippingAddressId'] = $cart->shippingAddressId;
        if ($cart->getShippingAddress()) {
            $data['shippingAddress'] = $cart->shippingAddress->toArray();
            if ($cart->shippingAddress->getErrors()) {
                $lineItems['shippingAddress']['errors'] = $cart->getShippingAddress()->getErrors();
            }
        } else {
            $data['shippingAddress'] = null;
        }

        $data['billingAddressId'] = $cart->billingAddressId;
        if ($cart->getBillingAddress()) {
            $data['billingAddress'] = $cart->billingAddress->toArray();
            if ($cart->billingAddress->getErrors()) {
                $lineItems['billingAddress']['errors'] = $cart->getBillingAddress()->getErrors();
            }
        } else {
            $data['billingAddress'] = null;
        }

        $lineItems = [];
        foreach ($cart->lineItems as $lineItem) {
            $lineItemData = [];
            $lineItemData['id'] = $lineItem->id;
            $lineItemData['price'] = $lineItem->price;
            $lineItemData['saleAmount'] = $lineItem->saleAmount;
            $lineItemData['salePrice'] = $lineItem->salePrice;
            $lineItemData['qty'] = $lineItem->qty;
            $lineItemData['weight'] = $lineItem->weight;
            $lineItemData['length'] = $lineItem->length;
            $lineItemData['height'] = $lineItem->height;
            $lineItemData['width'] = $lineItem->width;
            $lineItemData['total'] = $lineItem->total;
            $lineItemData['qty'] = $lineItem->qty;
            $lineItemData['snapshot'] = Json::decodeIfJson($lineItem->snapshot);
            $lineItemData['note'] = $lineItem->note;
            $lineItemData['orderId'] = $lineItem->orderId;
            $lineItemData['purchasableId'] = $lineItem->purchasableId;
            $lineItemData['taxCategoryId'] = $lineItem->taxCategoryId;
            $lineItemData['shippingCategoryId'] = $lineItem->shippingCategoryId;
            $lineItemData['onSale'] = $lineItem->getOnSale();
            $lineItemData['options'] = $lineItem->options;
            $lineItemData['optionsSignature'] = $lineItem->getOptionsSignature();
            $lineItemData['subtotal'] = $lineItem->getSubtotal();
            $lineItemData['total'] = $lineItem->getTotal();
            $data['totalTax'] = $cart->getAdjustmentsTotalByType('tax');
            $data['totalTaxIncluded'] = $cart->getAdjustmentsTotalByType('tax', true);
            $data['totalShippingCost'] = $cart->getAdjustmentsTotalByType('shipping');
            $data['totalDiscount'] = $cart->getAdjustmentsTotalByType('discount');
            $lineItems[$lineItem->id] = $lineItemData;
            if ($lineItem->getErrors()) {
                $lineItems['errors'] = $lineItem->getErrors();
            }
        }
        $data['lineItems'] = $lineItems;
        $data['totalLineItems'] = count($lineItems);

        $adjustments = [];
        foreach ($cart->adjustments as $adjustment) {
            $adjustmentData = [];
            $adjustmentData['id'] = $adjustment->id;
            $adjustmentData['type'] = $adjustment->type;
            $adjustmentData['name'] = $adjustment->name;
            $adjustmentData['description'] = $adjustment->description;
            $adjustmentData['amount'] = $adjustment->amount;
            $adjustmentData['sourceSnapshot'] = $adjustment->sourceSnapshot;
            $adjustmentData['orderId'] = $adjustment->orderId;
            $adjustments[$adjustment->type][] = $adjustmentData;
        }
        $data['adjustments'] = $adjustments;
        $data['totalAdjustments'] = count($adjustments);

        if ($cart->getErrors()) {
            $data['errors'] = $cart->getErrors();
        }

        // remove un-needed base element attributes
        $remove = ['archived', 'cancelUrl', 'lft', 'level', 'rgt', 'slug', 'uri', 'root'];
        foreach ($remove as $r) {
            unset($data[$r]);
        }

        return $data;
    }
}
