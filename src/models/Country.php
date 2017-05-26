<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\helpers\UrlHelper;

/**
 * Country Model
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class Country extends Model
{
    // Properties
    // =========================================================================

    /*
     * @var int ID
     */
    public $id;

    /*
     * @var string Name
     */
    public $name;

    /*
     * @var string ISO code
     */
    public $iso;

    /*
     * @var bool State Required
     */
    public $stateRequired;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->name;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/countries/'.$this->id);
    }
}