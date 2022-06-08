<?php

namespace craftcommercetests\fixtures;

use craft\base\ElementInterface;
use craft\elements\Address;
use craft\elements\User;
use craft\test\fixtures\elements\BaseElementFixture;
use craft\test\fixtures\elements\UserFixture;

class CustomerAddressFixture extends BaseElementFixture
{
    public $dataFile = __DIR__ . '/data/customer-addresses.php';

    public $depends = [CustomerFixture::class];

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
            unset($attributes['_shippingAddress']);
        }

        parent::populateElement($element, $attributes);
    }
}