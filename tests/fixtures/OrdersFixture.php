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
    public $dataFile = __DIR__ . '/data/orders.php';

    /**
     * @inheritdoc
     */
    public $depends = [
        CustomerFixture::class,
        ProductFixture::class,
        OrderStatusesFixture::class,
        ShippingFixture::class,
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

    private ?array $_billingAddress = null;
    private ?array $_shippingAddress = null;

    public function init(): void
    {
        parent::init();
    }

    /**
     * @inheritDoc
     */
    protected function populateElement(ElementInterface $element, array $attributes): void
    {
        $customerEmail = ArrayHelper::remove($attributes, '_customerEmail');
        if ($customerEmail && $user = Craft::$app->getUsers()->ensureUserByEmail($customerEmail)) {
            $attributes['customerId'] = $user->id;
        }

        $this->_lineItems = ArrayHelper::remove($attributes, '_lineItems');
        $this->_markAsComplete = ArrayHelper::remove($attributes, '_markAsComplete');
        $this->_dateOrdered = ArrayHelper::remove($attributes, '_dateOrdered');
        $this->_billingAddress = ArrayHelper::remove($attributes, '_billingAddress');
        $this->_shippingAddress = ArrayHelper::remove($attributes, '_shippingAddress');

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

        $reSaveOrder = false;

        if ($this->_dateOrdered) {
            $element->dateOrdered = $this->_dateOrdered;
            $reSaveOrder = true;
        }

        if ($this->_billingAddress) {
            $element->setBillingAddress($this->_billingAddress);
            $reSaveOrder = true;
        }

        if ($this->_shippingAddress) {
            $element->setShippingAddress($this->_shippingAddress);
            $reSaveOrder = true;
        }

        if ($reSaveOrder && !Craft::$app->getElements()->saveElement($element)) {
            // Re-save after extra data
            throw new InvalidElementException($element, implode(' ', $element->getErrorSummary(true)));
        }

        // Reset private variables
        $this->_lineItems = [];
        $this->_markAsComplete = false;
        $this->_dateOrdered = false;
        $this->_billingAddress = null;
        $this->_shippingAddress = null;

        return true;
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
                Craft::$app->getElements()->deleteElementById(elementId: $addressId, hardDelete: true);
            }
        }
        //
        // if ($customerId = $element->getCustomerId()) {
        //     Craft::$app->getElements()->deleteElementById(elementId: $customerId, hardDelete: true);
        // }

        return $result;
    }
}
