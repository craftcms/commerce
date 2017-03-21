<?php
namespace craft\commerce\models;

use craft\commerce\base\Model;

/**
 * Currency Model
 *
 * @property string $alphabeticCode
 * @property string $currency
 * @property string $entity
 * @property int    $minorUnit
 * @property int    $numericCode
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class Currency extends Model
{
    // Properties
    // =========================================================================

    /*
     * @var string Alphabetic code
     */
    public $alphabeticCode;

    /*
     * @var string Currency
     */
    public $currency;

    /*
     * @var string Entity
     */
    public $entity;

    /*
     * @var int Number of minor unites
     */
    public $minorUnit;

    /*
     * @var int Numeric code
     */
    public $numericCode;

    /**
     * @return string
     */
    function __toString()
    {
        return $this->iso;
    }

}