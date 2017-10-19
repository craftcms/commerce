<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\helpers\UrlHelper;

/**
 * Currency model.
 *
 * @property int         $id
 * @property string      $iso
 * @property bool        $primary
 * @property float       $rate
 * @property string      $alphabeticCode
 * @property string      $currency
 * @property string      $entity
 * @property int         $minorUnit
 * @property null|string $name
 * @property string      $cpEditUrl
 * @property int         $numericCode
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
 */
class PaymentCurrency extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string ISO code
     */
    public $iso;

    /**
     * @var bool Is primary currency
     */
    public $primary;

    /**
     * @var float Exchange rate vs primary currency
     */
    public $rate;

    /**
     * @var Currency
     */
    private $_currency;

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/paymentcurrencies/'.$this->id);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->iso;
    }

    /**
     * @inheritdoc
     */
    public function fields(): array
    {
        $fields = parent::fields();

        $fields['minorUnits'] = function($model) {
            return $model->getMinorUnits();
        };
        $fields['alphabeticCode'] = function($model) {
            return $model->getAlphabeticCode();
        };
        $fields['currency'] = function($model) {
            return $model->getCurrency();
        };
        $fields['numericCode'] = function($model) {
            return $model->getNumericCode();
        };
        $fields['entity'] = function($model) {
            return $model->getEntity();
        };

        return $fields;
    }

    /**
     * @return string|null
     */
    public function getAlphabeticCode()
    {
        if ($this->_currency !== null) {
            return $this->_currency->alphabeticCode;
        }

        return null;
    }

    /**
     * @return int|null
     */
    public function getNumericCode()
    {
        if ($this->_currency !== null) {
            return $this->_currency->numericCode;
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getEntity()
    {
        if ($this->_currency !== null) {
            return $this->_currency->entity;
        }

        return null;
    }

    /**
     * @return int|null
     */
    public function getMinorUnit()
    {
        if ($this->_currency !== null) {
            return $this->_currency->minorUnit;
        }

        return null;
    }

    /**
     * Alias of getCurrency()
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->getCurrency();
    }

    /**
     * @return string|null
     */
    public function getCurrency()
    {
        if ($this->_currency !== null) {
            return $this->_currency->currency;
        }

        return null;
    }

    /**
     * Sets the Currency Model data on the Payment Currency
     *
     * @param $currency
     */
    public function setCurrency(Currency $currency)
    {
        $this->_currency = $currency;
    }
}
