<?php

namespace craft\commerce\models;

use craft\behaviors\FieldLayoutBehavior;
use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;

/**
 * Order settings model.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $handle
 * @property int         $fieldLayoutId
 * @property string      $cpEditUrl
 * @property FieldLayout $fieldLayout
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class OrderSettings extends Model
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
     * @var string Handle
     */
    public $handle;

    /**
     * @var int Field layout ID
     */
    public $fieldLayoutId;

    // Public Methods
    // =========================================================================

    /**
     * @return null|string
     */
    public function __toString(): string
    {
        return (string)$this->handle;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/ordersettings');
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['fieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => Order::class
        ];

        return $behaviors;
    }
}
