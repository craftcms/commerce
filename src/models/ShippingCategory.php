<?php
namespace Craft;

use craft\commerce\base\Model;
use craft\helpers\UrlHelper;

/**
 * Shipping Category model.
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property bool   $default
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.2
 */
class ShippingCategory extends Model
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
     * @var string Description
     */
    public $description;

    /**
     * @var bool Default
     */
    public $default;

    /**
     * Returns the name of this shipping category.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->name;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/shippingcategories/'.$this->id);
    }
}