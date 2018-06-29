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
            // this will confirm the gateway is
            $this->getGateway();
        } catch (InvalidConfigException $e) {
            $validator->addError($this, $attribute, Craft::t('commerce', 'Invalid gateway ID: {value}'));
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
        $customer = $this->getCustomer();

        if (!$address->validate()) {
            $this->addModelErrors($address, $attribute);
        }

        $addressesIds = Plugin::getInstance()->getCustomers()->getAddressIds($customer->id);

        if ($address->id && !in_array($address->id, $addressesIds, false)) {
            $address->addError($attribute, Craft::t('commerce', 'Address does not belong to customer.'));
            $this->addModelErrors($address, $attribute);
        }
    }

    /**
     * Validates that shipping address isn't being set to be the same as billing adress, when billing address is set to be shipping address
     *
     * @param string $attribute the attribute being validated
     */
    public function validateAddressReuse($attribute)
    {
        if ($this->shippingSameAsBilling && $this->billingSameAsShipping) {
            $this->addError($attribute, Craft::t('commerce', 'You can\'t set shipping address to be the same as billing when you\'re setting billing address to be same as shipping'));
        }
    }

    /**
     * Validates line items, and also adds prefixed validation errors to order
     *
     * @param string $attribute the attribute being validated
     */
    public function validateLineItems($attribute)
    {
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
