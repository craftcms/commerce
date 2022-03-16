<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\behaviors;

use craft\commerce\Plugin;
use craft\elements\Address;
use craft\elements\User;
use craft\events\DefineRulesEvent;
use yii\base\Behavior;

/**
 * Customer address behavior.
 *
 * @property Address $owner
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0.0
 */
class CustomerAddressBehavior extends Behavior
{
    private bool $_isPrimaryBilling;
    private bool $_isPrimaryShipping;

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            Address::EVENT_DEFINE_RULES => 'defineRules',
            Address::EVENT_AFTER_PROPAGATE => 'afterPropagate',
        ];
    }

    /**
     * @param DefineRulesEvent $event
     */
    public function defineRules(DefineRulesEvent $event): void
    {
        $event->rules[] = [['isPrimaryBilling', 'isPrimaryShipping'], 'boolean'];
    }

    public function afterPropagate(): void
    {
        if ($this->owner->getIsDraft()) {
            return;
        }

        /** @var User $user */
        $user = $this->owner->getOwner();
        $customersService = Plugin::getInstance()->getCustomers();

        if (isset($this->_isPrimaryBilling)) {
            $customersService->savePrimaryBillingAddressId($user, $this->_isPrimaryBilling ? $this->owner->id : null);
        }

        if (isset($this->_isPrimaryShipping)) {
            $customersService->savePrimaryShippingAddressId($user, $this->_isPrimaryShipping ? $this->owner->id : null);
        }
    }

    /**
     * Returns whether this is the user’s primary billing address.
     *
     * @return bool
     */
    public function getIsPrimaryBilling(): bool
    {
        if (!isset($this->_isPrimaryBilling)) {
            if (!$this->owner->id) {
                return false;
            }

            /** @var User|CustomerBehavior $user */
            $user = $this->owner->getOwner();
            $this->_isPrimaryBilling = $this->owner->id === $user->getPrimaryBillingAddressId();
        }

        return $this->_isPrimaryBilling;
    }

    /**
     * Sets whether this is the user’s primary billing address.
     *
     * @param bool|string $value
     */
    public function setIsPrimaryBilling(bool|string $value): void
    {
        $this->_isPrimaryBilling = (bool)$value;
    }

    /**
     * Returns whether this is the user’s primary shipping address.
     *
     * @return bool
     */
    public function getIsPrimaryShipping(): bool
    {
        if (!isset($this->_isPrimaryShipping)) {
            if (!$this->owner->id) {
                return false;
            }

            /** @var User|CustomerBehavior $user */
            $user = $this->owner->getOwner();
            $this->_isPrimaryShipping = $this->owner->id === $user->getPrimaryShippingAddressId();
        }

        return $this->_isPrimaryShipping;
    }

    /**
     * Sets whether this is the user’s primary shipping address.
     *
     * @param bool|string $value
     */
    public function setIsPrimaryShipping(bool|string $value): void
    {
        $this->_isPrimaryShipping = (bool)$value;
    }
}
