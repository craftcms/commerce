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
use DateTime;
use yii\base\InvalidConfigException;
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
     * @var array
     */
    private array $_lineItems = [];

    /**
     * @var bool
     */
    private bool $_markAsComplete = false;

    /**
     * Ability to manually set the dateOrdered attribute
     *
     * @var bool|null|DateTime
     */
    private $_dateOrdered = false;

    public function init(): void
    {
        Craft::$app->getPlugins()->switchEdition('commerce', Plugin::EDITION_PRO);

        parent::init();
    }

    /**
     * @inheritDoc
     */
    protected function populateElement(ElementInterface $element, array $attributes): void
    {
        $this->_lineItems = ArrayHelper::remove($attributes, '_lineItems');
        $this->_markAsComplete = ArrayHelper::remove($attributes, '_markAsComplete');
        $this->_dateOrdered = ArrayHelper::remove($attributes, '_dateOrdered');

        parent::populateElement($element, $attributes);
    }

    /**
     * @inerhitDoc
     */
    protected function saveElement(ElementInterface $element): bool
    {
        // Do an initial save to get an ID
        $result = parent::saveElement($element);

        /** @var Order $element */
        $this->_setLineItems($element, $this->_lineItems);

        // Re-save after extra data
        if (!$result = Craft::$app->getElements()->saveElement($element)) {
            throw new InvalidElementException($element, implode(' ', $element->getErrorSummary(true)));
        }

        // Complete order if required
        if ($this->_markAsComplete) {
            $element->markAsComplete();
        }

        if ($this->_dateOrdered) {
            $element->dateOrdered = $this->_dateOrdered;
            // Re-save after extra data
            if (!$result = Craft::$app->getElements()->saveElement($element)) {
                throw new InvalidElementException($element, implode(' ', $element->getErrorSummary(true)));
            }
        }

        // Reset private variables
        $this->_lineItems = [];
        $this->_markAsComplete = false;
        $this->_dateOrdered = false;

        return $result;
    }

    /**
     * Set line items on the order from the fixture data.
     *
     * @param Order $order
     * @param $lineItems
     * @throws InvalidConfigException
     */
    private function _setLineItems(Order $order, $lineItems): void
    {
        if (empty($lineItems)) {
            return;
        }

        $orderLineItems = [];
        foreach ($lineItems as $lineItem) {
            $orderLineItems[] = Plugin::getInstance()->getLineItems()->createLineItem($order, $lineItem['purchasableId'], $lineItem['options'], $lineItem['qty'], $lineItem['note']);
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
