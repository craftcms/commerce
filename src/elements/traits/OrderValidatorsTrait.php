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
use craft\commerce\errors\CurrencyException;
use craft\commerce\helpers\Order as OrderHelper;
use craft\commerce\models\Address;
use craft\commerce\models\OrderNotice;
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
     * @param string $attribute
     * @param $params
     * @param Validator $validator
     */
    public function validateGatewayId(string $attribute, $params, Validator $validator): void
    {
        if ($this->gatewayId && !$this->getGateway()) {
            $validator->addError($this, $attribute, Craft::t('commerce', 'Invalid gateway: {value}'));
        }
    }

    /**
     * @param string $attribute
     * @param $params
     * @param Validator $validator
     */
    public function validatePaymentSourceId(string $attribute, $params, Validator $validator): void
    {
        try {
            // this will confirm the payment source is valid and belongs to the orders customer
            $this->getPaymentSource();
        } catch (InvalidConfigException $e) {
            Craft::error($e->getMessage());
            $validator->addError($this, $attribute, Craft::t('commerce', 'Invalid payment source ID: {value}'));
        }
    }

    /**
     * @param string $attribute
     * @param $params
     * @param Validator $validator
     * @throws CurrencyException
     * @noinspection PhpUnused
     */
    public function validatePaymentCurrency(string $attribute, $params, Validator $validator): void
    {
        try {
            // this will confirm the payment source is valid and belongs to the orders customer
            $this->getPaymentCurrency();
        } catch (InvalidConfigException $e) {
            $validator->addError($this, $attribute, Craft::t('commerce', 'Invalid payment source ID: {value}'));
        }
    }

    /**
     * Validates addresses, and also adds prefixed validation errors to order
     *
     * @param string $attribute the attribute being validated
     * @noinspection PhpUnused
     */
    public function validateAddress(string $attribute): void
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
     * @noinspection PhpUnused
     */
    public function validateAddressCanBeUsed(string $attribute): void
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
    public function validateAddressReuse(string $attribute): void
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
    public function validateLineItems(string $attribute): void
    {
        OrderHelper::mergeDuplicateLineItems($this);

        foreach ($this->getLineItems() as $key => $lineItem) {
            if (!$lineItem->validate()) {
                $this->addModelErrors($lineItem, "lineItems.{$key}");
            }
        }
    }

    /**
     * @param $attribute
     * @throws InvalidConfigException
     * @noinspection PhpUnused
     */
    public function validateCouponCode($attribute): void
    {
        $recalculateAll = $this->recalculationMode == Order::RECALCULATION_MODE_ALL;
        $recalculateAll = $recalculateAll || $this->recalculationMode == Order::RECALCULATION_MODE_ADJUSTMENTS_ONLY;
        if ($recalculateAll && $this->$attribute && !Plugin::getInstance()->getDiscounts()->orderCouponAvailable($this, $explanation)) {
            /** @var OrderNotice $notice */
            $notice = Craft::createObject([
                'class' => OrderNotice::class,
                'attributes' => [
                    'type' => 'invalidCouponRemoved',
                    'attribute' => $attribute,
                    'message' => Craft::t('commerce', 'Coupon removed: {explanation}', [
                        'explanation' => $explanation,
                    ]),
                ],
            ]);
            $this->addNotice($notice);
            $this->$attribute = null;
        }
    }
}
