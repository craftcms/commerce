<?php

namespace craftcommercetests\fixtures;

use craft\base\ElementInterface;
use craft\commerce\Plugin;
use craft\elements\Address;
use craft\elements\User;
use craft\errors\InvalidElementException;
use craft\test\fixtures\elements\BaseElementFixture;
use craft\test\fixtures\elements\UserFixture;

class CustomerAddressFixture extends BaseElementFixture
{
    public $dataFile = __DIR__ . '/data/customer-addresses.php';

    public $depends = [CustomerFixture::class];
    private ?array $_shippingAddress = null;
    /**
     * @inheritdoc
     */
    protected function createElement(): ElementInterface
    {
        return new User();
    }

    public function populateElement(ElementInterface $element, array $attributes): void
    {
        if (isset($attributes['_shippingAddress'])) {
            $this->_shippingAddress = $attributes['_shippingAddress'];
            unset($attributes['_shippingAddress']);
        }

        parent::populateElement($element, $attributes);
    }

    protected function saveElement(ElementInterface $element): bool
    {
        // Do an initial save to get an ID
        parent::saveElement($element);

        $user = \Craft::$app->getUsers()->getUserByUsernameOrEmail('customer3@crafttest.com');

        $address = new Address();
        $address->ownerId = $user->id;
        $address->setAttributes($this->_shippingAddress);


        if (!\Craft::$app->getElements()->saveElement($address)) {
            throw new InvalidElementException($address, implode(' ', $address->getErrorSummary(true)));
        }

        Plugin::getInstance()->getCustomers()->savePrimaryShippingAddressId($user, $address->id);

        return true;
    }
}