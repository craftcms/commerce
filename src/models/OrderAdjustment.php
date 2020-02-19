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
use craft\helpers\Json;
use yii\base\InvalidArgumentException;
use yii\behaviors\AttributeTypecastBehavior;

/**
 * Order adjustment model.
 *
 * @property Order|null $order
 * @property LineItem|null $lineItem
 * @property array $sourceSnapshot
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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


    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['typecast'] = [
            'class' => AttributeTypecastBehavior::className(),
            'attributeTypes' => [
                'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                'lineItemId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'orderId' => AttributeTypecastBehavior::TYPE_INTEGER,
                'included' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'isEstimated' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                'type' => AttributeTypecastBehavior::TYPE_STRING,
                'amount' => AttributeTypecastBehavior::TYPE_FLOAT,
                'name' => AttributeTypecastBehavior::TYPE_STRING,
                'description' => AttributeTypecastBehavior::TYPE_STRING
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
                'amount',
                'sourceSnapshot',
                'orderId'
            ], 'required'
        ];
        $rules[] = [['amount'], 'number'];
        $rules[] = [['orderId'], 'integer'];
        $rules[] = [['lineItemId'], 'integer'];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $attributes = parent::attributes();
        $attributes[] = 'sourceSnapshot';

        return $attributes;
    }

    /**
     * The attributes on the order that should be made available as formatted currency.
     *
     * @return array
     */
    public function currencyAttributes(): array
    {
        $attributes = [];
        $attributes[] = 'amount';
        return $attributes;
    }

    /**
     * @return array
     */
    public function fields(): array
    {
        $fields = parent::fields();

        foreach ($this->currencyAttributes() as $attribute) {
            $fields[$attribute . 'AsCurrency'] = function($model, $attribute) {
                $attribute = substr($attribute, 0, -10);
                if (!empty($model->$attribute)) {
                    return Craft::$app->getFormatter()->asCurrency($model->$attribute, $this->getOrder()->currency, [], [], true);
                }

                return $model->$attribute;
            };
        }

        return $fields;
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
        $this->orderId = $order->id;
    }
}
