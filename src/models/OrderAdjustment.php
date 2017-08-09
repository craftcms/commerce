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
 * @property string $optionsJson
 * @property int    $orderId
 *
 * @property Order  $order
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class OrderAdjustment extends Model
{

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
    public $optionsJson;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @return \craft\commerce\elements\Order|null
     */
    public function getOrder()
    {
        return Plugin::getInstance()->getOrder()->getOrderById($this->orderId);
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            [['type', 'amount', 'optionsJson', 'orderId'], 'required']
        ];
    }

    /**
     * @return null
     */
    public function init()
    {
        $this->included = false;
        $this->optionsJson = [];
    }
}