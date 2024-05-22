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
use yii\base\InvalidConfigException;

/**
 * Customer address behavior.
 *
 * @property bool $isPrimaryBilling
 * @property bool $isPrimaryShipping
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
    public function events(): array
    {
        return [
            Address::EVENT_DEFINE_RULES => 'defineRules',
            Address::EVENT_AFTER_PROPAGATE => 'afterPropagate',
        ];
    }

    /**
     * @param DefineRulesEvent $event
     * @throws InvalidConfigException
     */
    public function defineRules(DefineRulesEvent $event): void
    {
        if (!$this->owner->getOwner() instanceof User) {
            return;
        }

        $event->rules[] = [['isPrimaryBilling', 'isPrimaryShipping'], 'boolean'];
    }

    public function afterPropagate(): void
    {
        if ($this->owner->getIsDraft()) {
            return;
        }

        /** @var User|null $user */
        $user = $this->owner->getOwner();

        if (!$user instanceof User) {
            return;
        }

        $customersService = Plugin::getInstance()->getCustomers();

        $customer = $customersService->ensureCustomer($user);
        if (isset($this->_isPrimaryBilling) && ($this->_isPrimaryBilling || $customer->primaryBillingAddressId === $this->owner->id)) {
            $customersService->savePrimaryBillingAddressId($user, $this->_isPrimaryBilling ? $this->owner->id : null);
        }

        if (isset($this->_isPrimaryShipping) && ($this->_isPrimaryShipping || $customer->primaryShippingAddressId === $this->owner->id)) {
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

            /** @var User|CustomerBehavior|null $user */
            $user = $this->owner->getOwner();

            if (!$this->owner->id || !$user) {
                return false;
            }

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

            /** @var User|CustomerBehavior|null $user */
            $user = $this->owner->getOwner();

            if (!$this->owner->id || !$user) {
                return false;
            }

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
