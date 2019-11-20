<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\helpers\Json;
use yii\base\InvalidArgumentException;

/**
 * Order adjustment model.
 *
 * @property array $sourceSnapshot
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderAdjustment extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Description
     */
    public $description;

    /**
     * @var string Type
     */
    public $type;

    /**
     * @var float Amount
     */
    public $amount;

    /**
     * @var bool Included
     */
    public $included = false;

    /**
     * @var mixed Adjuster options
     */
    private $_sourceSnapshot = [];

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var int Line item ID this adjustment belongs to
     */
    public $lineItemId;

    /**
     * @var bool Whether the adjustment is based of estimated data
     */
    public $isEstimated = false;

    /**
     * @var LineItem|null The line item this adjustment belongs to
     */
    private $_lineItem;

    /**
     * @var Order|null The order this adjustment belongs to
     */
    private $_order;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'amount', 'sourceSnapshot', 'orderId'], 'required']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        $attributes = parent::attributes();
        $attributes[] = 'sourceSnapshot';

        return $attributes;
    }

    /**
     * Gets the options for the line item.
     */
    public function getSourceSnapshot(): array
    {
        return $this->_sourceSnapshot;
    }

    /**
     * Set the options array on the line item.
     *
     * @param array|string $snapshot
     */
    public function setSourceSnapshot($snapshot)
    {
        if (is_string($snapshot)) {
            $snapshot = Json::decode($snapshot);
        }

        if (!is_array($snapshot)) {
            throw new InvalidArgumentException('Adjustment source snapshot must be an array.');
        }

        $this->_sourceSnapshot = $snapshot;
    }

    /**
     * @return LineItem|null
     */
    public function getLineItem()
    {
        if ($this->_lineItem === null && $this->lineItemId) {
            $this->_lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($this->lineItemId);
        }

        return $this->_lineItem;
    }

    /**
     * @param LineItem $lineItem
     * @return void
     */
    public function setLineItem(LineItem $lineItem)
    {
        $this->_lineItem = $lineItem;
    }

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        if ($this->_order === null && $this->orderId) {
            $this->_order = Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @param Order $order
     * @return void
     */
    public function setOrder(Order $order)
    {
        $this->_order = $order;
    }
}
