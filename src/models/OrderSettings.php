<?php
namespace craft\commerce\models;

use craft\behaviors\FieldLayoutBehavior;
use craft\commerce\base\Model;
use craft\helpers\UrlHelper;
use craft\commerce\elements\Order;
/**
 * Order settings model.
 *
 * @property int               $id
 * @property string            $name
 * @property string            $handle
 * @property int               $fieldLayoutId
 *
 * @property \craft\models\FieldLayout $fieldLayout
 *
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class OrderSettings extends Model
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
     * @var string Handle
     */
    public $handle;

    /**
     * @var int Field layout ID
     */
    public $fieldLayoutId;

    /**
     * @return null|string
     */
    function __toString()
    {

        return $this->handle;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/ordersettings');
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['fieldLayout'] = [
            'class' => FieldLayoutBehavior::class,
            'elementType' => Order::class
        ];

        return $behaviors;
    }
}