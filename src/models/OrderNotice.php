<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\helpers\Json;
use yii\base\InvalidArgumentException;
use yii\behaviors\AttributeTypecastBehavior;

/**
 * Order notice model.
 *
 * @property Order|null $order
 * @method void typecastAttributes()
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
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
     * @var Order|null The order this adjustment belongs to
     */
    private $_order;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->message ?: '';
    }

    public static function create(string $type, string $attribute, string $message, Order $order = null)
    {
        $new = Craft::createObject([
            'class' => static::class,
            'type' => $type,
            'attribute' => $attribute,
            'message' => $message
        ]);

        if ($order) {
            $new->setOrder($order);
        }

        return $new;
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
        $this->orderId = $order->id;
    }
}
