<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\errors\InvalidElementException;
use craft\test\fixtures\elements\BaseElementFixture;
use yii\helpers\ArrayHelper;

/**
 * Class OrdersFixture.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
 */
class OrdersFixture extends BaseElementFixture
{
    /**
     * @inheritDoc
     */
    public $dataFile = __DIR__.'/data/orders.php';

    /**
     * @inheritdoc
     */
    public $depends = [
        ProductFixture::class,
        CustomersAddressesFixture::class,
        OrderStatusesFixture::class,
    ];

    /**
     * @inheritDoc
     */
    protected function populateElement(ElementInterface $element, array $attributes): void
    {
        $keys = ['!_lineItems', '!_markAsComplete'];
        $attributes = ArrayHelper::filter($attributes, $keys);

        parent::populateElement($element, $attributes);
    }

    /**
     * @inheritDoc
     */
    protected function afterSaveElement(ElementInterface $element, array $attributes): void
    {
        /** @var Order $element */
        $this->_setLineItems($element, $attributes['_lineItems'] ?? []);

        // Resave after extra data
        if (!Craft::$app->getElements()->saveElement($element)) {
            throw new InvalidElementException($element, implode(' ', $element->getErrorSummary(true)));
        }

        // Complete order if required
        if ($attributes['_markAsComplete']) {
            $element->markAsComplete();
        }
    }

    /**
     * Set line items on the order from the fixture data.
     *
     * @param Order $order
     * @param $lineItems
     */
    private function _setLineItems(Order $order, $lineItems)
    {
        if (empty($lineItems)) {
            return;
        }

        $orderLineItems = [];
        foreach ($lineItems as $lineItem) {
            $orderLineItems[] = Plugin::getInstance()->getLineItems()->createLineItem($order->id, $lineItem['purchasableId'], $lineItem['options'], $lineItem['qty'], $lineItem['note']);
        }

        $order->setLineItems($orderLineItems);

    }

    /**
     * @inheritDoc
     */
    protected function createElement(): ElementInterface
    {
        return new Order();
    }

    /**
     * @inheritDoc
     */
    protected function deleteElement(ElementInterface $element): bool
    {
        /** @var Order $element */
        $addressIds = $element->isCompleted
            ? [$element->billingAddressId, $element->estimatedBillingAddressId, $element->shippingAddressId, $element->estimatedShippingAddressId]
            : [];

        $result = parent::deleteElement($element);

        $addressIds = array_filter($addressIds);
        if (!empty($addressIds)) {
            foreach ($addressIds as $addressId) {
                Plugin::getInstance()->getAddresses()->deleteAddressById($addressId);
            }
        }

        return $result;

    }
}
