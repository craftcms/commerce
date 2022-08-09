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
        $fieldsService->saveLayout($layout, false);
    }


    /**
     * @deprecated in 3.4.17. Unused fields will be pruned automatically as field layouts are resaved.
     */
    public function pruneDeletedField(FieldEvent $event)
    {
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
    public function getOrderByNumber(string $number)
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
        if (!$customer) {
            return null;
        }

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
    public function getOrdersByEmail(string $email)
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
     * @deprecated 3.0 use `$order->toArray()` instead
     */
    public function cartArray($cart)
    {
        $data = [];
        $data['id'] = $cart->id;
        $data['number'] = $cart->number;
        $data['couponCode'] = $cart->couponCode;
        $data['itemTotal'] = $cart->getItemTotal();
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
        $data['origin'] = $cart->origin;
        $data['orderLanguage'] = $cart->orderLanguage;
        $data['shippingMethod'] = $cart->shippingMethodHandle;
        $data['shippingMethodId'] = $cart->getShippingMethodId(); // TODO: Remove in Commerce 4
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
        $data['recalculationMode'] = $cart->getRecalculationMode();

        $data['availableShippingMethods'] = $cart->getAvailableShippingMethodOptions();

        $data['shippingAddressId'] = $cart->shippingAddressId;
        if ($cart->getShippingAddress()) {
            $data['shippingAddress'] = $cart->getShippingAddress()->toArray();
            if ($cart->getShippingAddress()->getErrors()) {
                $lineItems['shippingAddress']['errors'] = $cart->getShippingAddress()->getErrors();
            }
        } else {
            $data['shippingAddress'] = null;
        }

        $data['billingAddressId'] = $cart->billingAddressId;
        if ($cart->getBillingAddress()) {
            $data['billingAddress'] = $cart->getBillingAddress()->toArray();
            if ($cart->getBillingAddress()->getErrors()) {
                $lineItems['billingAddress']['errors'] = $cart->getBillingAddress()->getErrors();
            }
        } else {
            $data['billingAddress'] = null;
        }

        $data['estimatedShippingAddressId'] = $cart->estimatedShippingAddressId;
        if ($cart->getEstimatedShippingAddress()) {
            $data['estimatedShippingAddress'] = $cart->getEstimatedShippingAddress()->toArray();
            if ($cart->getEstimatedShippingAddress()->getErrors()) {
                $lineItems['estimatedShippingAddress']['errors'] = $cart->getEstimatedShippingAddress()->getErrors();
            }
        } else {
            $data['estimatedShippingAddress'] = null;
        }

        $data['estimatedBillingAddressId'] = $cart->estimatedBillingAddressId;
        if ($cart->getEstimatedBillingAddress()) {
            $data['estimatedBillingAddress'] = $cart->getEstimatedBillingAddress()->toArray();
            if ($cart->getEstimatedBillingAddress()->getErrors()) {
                $lineItems['estimatedBillingAddress']['errors'] = $cart->getEstimatedBillingAddress()->getErrors();
            }
        } else {
            $data['estimatedBillingAddress'] = null;
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

            $lineItemData['totalTax'] = $lineItem->getTax(); // deprecate in 3.0
            $lineItemData['totalTaxIncluded'] = $lineItem->getTaxIncluded(); // deprecate in 3.0
            $lineItemData['totalShippingCost'] = $lineItem->getShippingCost(); // deprecate in 3.0
            $lineItemData['totalDiscount'] = $lineItem->getDiscount(); // deprecate in 3.0

            $lineItemData['tax'] = $lineItem->getTax();
            $lineItemData['taxIncluded'] = $lineItem->getTaxIncluded();
            $lineItemData['shippingCost'] = $lineItem->getShippingCost();
            $lineItemData['discount'] = $lineItem->getDiscount();

            $lineItemAdjustments = [];
            foreach ($lineItem->getAdjustments() as $adjustment) {
                $adjustmentData = [];
                $adjustmentData['id'] = $adjustment->id;
                $adjustmentData['type'] = $adjustment->type;
                $adjustmentData['name'] = $adjustment->name;
                $adjustmentData['description'] = $adjustment->description;
                $adjustmentData['amount'] = $adjustment->amount;
                $adjustmentData['sourceSnapshot'] = $adjustment->sourceSnapshot;
                $adjustmentData['orderId'] = $adjustment->orderId;
                $adjustmentData['lineItemId'] = $adjustment->lineItemId;
                $adjustmentData['isEstimated'] = $adjustment->isEstimated;
                $adjustments[$adjustment->type][] = $adjustmentData;
                $lineItemAdjustments[] = $adjustmentData;
            }
            $lineItemData['adjustments'] = $lineItemAdjustments;
            $lineItems[$lineItem->id] = $lineItemData;
            if ($lineItem->getErrors()) {
                $lineItems['errors'] = $lineItem->getErrors();
            }
        }
        $data['totalTax'] = $cart->getTotalTax();
        $data['totalTaxIncluded'] = $cart->getTotalTaxIncluded();
        $data['totalShippingCost'] = $cart->getTotalShippingCost();
        $data['totalDiscount'] = $cart->getTotalDiscount();
        $data['lineItems'] = $lineItems;
        $data['totalLineItems'] = count($lineItems);

        $adjustments = [];
        foreach ($cart->getAdjustments() as $adjustment) {
            $adjustmentData = [];
            $adjustmentData['id'] = $adjustment->id;
            $adjustmentData['type'] = $adjustment->type;
            $adjustmentData['name'] = $adjustment->name;
            $adjustmentData['description'] = $adjustment->description;
            $adjustmentData['amount'] = $adjustment->amount;
            $adjustmentData['sourceSnapshot'] = $adjustment->sourceSnapshot;
            $adjustmentData['orderId'] = $adjustment->orderId;
            $adjustmentData['lineItemId'] = $adjustment->lineItemId;
            $adjustmentData['isEstimated'] = $adjustment->isEstimated;
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
        ksort($data);
        return $data;
    }
}
