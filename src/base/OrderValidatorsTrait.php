<?php

namespace craft\commerce\base;

use Craft;
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
        $address = $this->$attribute;
        if (!$address->validate()) {
            $this->addModelErrors($address, $attribute);
        }
    }

    /**
     * Validates line items, and also adds prefixed validation errors to order
     *
     * @param string $attribute the attribute being validated
     */
    public function validateLineItems($attribute)
    {
        foreach ($this->getLineItems() as $key => $lineItem)
        {
            if (!$lineItem->validate()) {
                $this->addModelErrors($lineItem, "lineItems.{$key}");
            }
        }
    }
}