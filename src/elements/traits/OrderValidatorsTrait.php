<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Order as OrderHelper;
use craft\commerce\models\Address;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\db\Query;
use yii\base\InvalidConfigException;
use yii\validators\Validator;

/**
 * @property Order $this
 */
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
            $validator->addError($this, $attribute, Plugin::t('Invalid gateway: {value}'));
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
            Craft::error($e->getMessage());
            $validator->addError($this, $attribute, Plugin::t('Invalid payment source ID: {value}'));
        }
    }

    /**
     * @param $attribute
     * @param $params
     * @param Validator $validator
     */
    public function validatePaymentCurrency($attribute, $params, Validator $validator)
    {
        try {
            // this will confirm the payment source is valid and belongs to the orders customer
            $this->getPaymentCurrency();
        } catch (InvalidConfigException $e) {
            $validator->addError($this, $attribute, Plugin::t('Invalid payment source ID: {value}'));
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
    public function validateAddressCanBeUsed($attribute)
    {
        $customer = $this->getCustomer();
        /** @var Address $address */
        $address = $this->$attribute;

        // We need to have a customer ID and an address ID
        if ($customer && $customer->id && $address && $address->id) {

            $anotherOrdersAddress = false;

            // Is another customer related to this address?
            $anotherCustomerAddress = (new Query())
                ->select('id')
                ->from([Table::CUSTOMERS_ADDRESSES])
                ->where(['not', ['customerId' => $customer->id]])
                ->andWhere(['addressId' => $address->id])
                ->all();


            // Don't do an additional query if we already have an invalid address
            if ($anotherCustomerAddress) {
                // Is another order using this address?
                $anotherOrdersAddress = (new Query())
                    ->select('id')
                    ->from([Table::ORDERS])
                    ->where(['not', ['id' => $this->id]])
                    ->andWhere(['or', ['shippingAddressId' => $address->id], ['billingAddressId' => $address->id]])
                    ->all();
            }

            if ($anotherCustomerAddress || $anotherOrdersAddress) {
                $address->addError($attribute, Plugin::t('Address does not belong to customer.'));
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
            $this->addError($attribute, Plugin::t('shippingSameAsBilling and billingSameAsShipping can’t both be set.'));
        }
    }

    /**
     * Validates line items, and also adds prefixed validation errors to order
     *
     * @param string $attribute the attribute being validated
     */
    public function validateLineItems($attribute)
    {
        OrderHelper::mergeDuplicateLineItems($this);

        foreach ($this->getLineItems() as $key => $lineItem) {
            /** @var LineItem $lineItem */
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
        $recalculateAll = $this->recalculationMode == Order::RECALCULATION_MODE_ALL;
        $recalculateAll = $recalculateAll || $this->recalculationMode == Order::RECALCULATION_MODE_ADJUSTMENTS_ONLY;
        if ($recalculateAll && $this->$attribute && !Plugin::getInstance()->getDiscounts()->orderCouponAvailable($this, $explanation)) {
            $this->addError($attribute, $explanation);
        }
    }
}
