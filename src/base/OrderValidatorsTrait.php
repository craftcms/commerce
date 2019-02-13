<?php

namespace craft\commerce\base;

use Craft;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;
use yii\validators\Validator;

trait OrderValidatorsTrait
{
    /**
     * @param $attribute
     * @param $params
     * @param Validator $validator
     */
    public function validateGatewayId($attribute, $params, Validator $validator)
    {
        try {
            /** @var GatewayInterface $gateway */
            $this->getGateway();
        } catch (InvalidConfigException $e) {
            $validator->addError($this, $attribute, Craft::t('commerce', 'Invalid gateway: {value}'));
        }
    }

    /**
     * @param $attribute
     * @param $params
     * @param Validator $validator
     */
    public function validatePaymentSourceId($attribute, $params, Validator $validator)
    {
        try {
            // this will confirm the payment source is valid and belongs to the orders customer
            $this->getPaymentSource();
        } catch (InvalidConfigException $e) {
            $validator->addError($this, $attribute, Craft::t('commerce', 'Invalid payment source ID: {value}'));
        }
    }

    /**
     * Validates addresses, and also adds prefixed validation errors to order
     *
     * @param string $attribute the attribute being validated
     */
    public function validateAddress($attribute)
    {
        /** @var Address $address */
        $address = $this->$attribute;

        if ($address && !$address->validate()) {
            $this->addModelErrors($address, $attribute);
        }
    }

    /**
     * Validates that an address belongs to the order‘s customer.
     *
     * @param string $attribute the attribute being validated
     */
    public function validateAddressBelongsToOrdersCustomer($attribute)
    {
        $customer = $this->getCustomer();
        /** @var Address $address */
        $address = $this->$attribute;

        if ($customer && $address) {
            $addressesIds = Plugin::getInstance()->getCustomers()->getAddressIds($customer->id);

            if ($address->id && !in_array($address->id, $addressesIds, false)) {
                $address->addError($attribute, Craft::t('commerce', 'Address does not belong to customer.'));
                $this->addModelErrors($address, $attribute);
            }
        }
    }

    /**
     * Validates that shipping address isn't being set to be the same as billing address, when billing address is set to be shipping address
     *
     * @param string $attribute the attribute being validated
     */
    public function validateAddressReuse($attribute)
    {
        if ($this->shippingSameAsBilling && $this->billingSameAsShipping) {
            $this->addError($attribute, Craft::t('commerce', 'shippingSameAsBilling and billingSameAsShipping can’t both be set.'));
        }
    }

    /**
     * Validates line items, and also adds prefixed validation errors to order
     *
     * @param string $attribute the attribute being validated
     */
    public function validateLineItems($attribute)
    {

        // Ensure no duplicate line items exist, and if they do, combine them.
        $keysByLineItemId = [];
        $quantityByLineItemId = [];
        $idsToRemove = [];
        foreach ($this->getLineItems() as $lineItem) {
            $quantityByLineItemId[$lineItem->id] = $lineItem->qty;
            $uniqueKey = [$lineItem->orderId, $lineItem->purchasableId, $lineItem->getOptionsSignature()];
            $keysByLineItemId[$lineItem->id] = $uniqueKey;
            foreach ($keysByLineItemId as $index => $key) {
                if ($uniqueKey === $key && $index != $lineItem->id) {
                    $lineItem->qty += $quantityByLineItemId[$index];
                    $idsToRemove[] = $index;
                }
            }
        }

        foreach ($idsToRemove as $id) {
            if ($lineItem = Plugin::getInstance()->lineItems->getLineItemById($id)) {
                $this->removeLineItem($lineItem);
            }
        }

        foreach ($this->getLineItems() as $key => $lineItem) {
            if (!$lineItem->validate()) {
                $this->addModelErrors($lineItem, "lineItems.{$key}");
            }
        }
    }

    /**
     * @param $attribute
     */
    public function validateCouponCode($attribute)
    {
        if (!$this->isCompleted && $this->$attribute && !Plugin::getInstance()->getDiscounts()->orderCouponAvailable($this, $explanation)) {
            $this->addError($attribute, $explanation);
        }
    }
}
