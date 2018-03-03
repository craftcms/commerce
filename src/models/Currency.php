<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;

/**
 * Currency Model
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Currency extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var string Alphabetic code
     */
    public $alphabeticCode;

    /**
     * @var string Currency
     */
    public $currency;

    /**
     * @var string Entity
     */
    public $entity;

    /**
     * @var int Number of minor unites
     */
    public $minorUnit;

    /**
     * @var int Numeric code
     */
    public $numericCode;

    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->alphabeticCode;
    }
}
