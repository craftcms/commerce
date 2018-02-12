<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;

/**
 * Order adjustment model.
 *
 * @property float  $amount
 * @property string $description
 * @property int    $id
 * @property bool   $included
 * @property int    $lineItemId
 * @property string $name
 * @property Order  $order
 * @property int    $orderId
 * @property string $sourceSnapshot
 * @property string $type
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
    public $included;

    /**
     * @var mixed Adjuster options
     */
    public $sourceSnapshot;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var int Order ID
     */
    public $lineItemId;

    // Public Methods
    // =========================================================================

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        return Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['type', 'amount', 'sourceSnapshot', 'orderId'], 'required']
        ];
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourceSnapshot = [];
    }
}
