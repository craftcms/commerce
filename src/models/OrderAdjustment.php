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

/**
 * Order adjustment model.
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
    public $sourceSnapshot;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var int Line item ID
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
    public function rules()
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
