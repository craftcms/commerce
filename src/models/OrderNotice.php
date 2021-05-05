<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use yii\behaviors\AttributeTypecastBehavior;

/**
 * Order notice model.
 *
 * @property Order|null $order
 * @method void typecastAttributes()
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3
 */
class OrderNotice extends Model
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Type
     */
    public $type;

    /**
     * @var string Attribute
     */
    public $attribute;

    /**
     * @var string Message
     */
    public $message;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var Order|null The order this notice belongs to
     */
    private $_order;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->message ?: '';
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::class,
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                'orderId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'type' => AttributeTypecastBehavior::TYPE_STRING,
                'attribute' => AttributeTypecastBehavior::TYPE_STRING,
                'message' => AttributeTypecastBehavior::TYPE_STRING
            ]
        ];
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            [
                'type',
                'message',
                'attribute',
                'orderId'
            ], 'required'
        ];
        $rules[] = [['orderId'], 'integer'];

        return $rules;
    }

    /**
     * @param Order $order
     * @return void
     */
    public function setOrder(Order $order)
    {
        $this->_order = $order;
        $this->orderId = $order->id;
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
}
