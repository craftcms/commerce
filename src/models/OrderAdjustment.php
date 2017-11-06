<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;

/**
 * Order adjustment model.
 *
 * @property int    $id
 * @property string $name
 * @property string $description
 * @property string $type
 * @property float  $amount
 * @property bool   $included
 * @property string $sourceSnapshot
 * @property int    $orderId
 * @property int    $lineItemId
 * @property Order  $order
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
     * @return \craft\commerce\elements\Order|null
     */
    public function getOrder()
    {
        return Plugin::getInstance()->getOrders()->getOrderById($this->orderId);
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['type', 'amount', 'sourceSnapshot', 'orderId'], 'required']
        ];
    }

    /**
     *
     */
    public function init()
    {
        $this->sourceSnapshot = [];
    }
}
