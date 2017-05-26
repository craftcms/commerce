<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;

/**
 * Currency model.
 *
 * @property int    $id
 * @property string $iso
 * @property bool   $primary
 * @property float  $rate
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
class PaymentCurrency extends Model
{
    private $_currency;

    public function populateModel($values)
    {
        if ($values instanceof \CModel) {
            $values = $values->getAttributes();
        }
        /** @var PaymentCurrency $currency */
        $currency = parent::populateModel($values);

        $iso = $values['iso'];
        if ($currencyModel = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($iso)) {
            $currency->setCurrency($currencyModel);
        }

        return $currency;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/paymentcurrencies/'.$this->id);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->iso;
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
        if (null !== $this->_currency) {
            return $this->_currency->alphabeticCode;
        }
    }

    /**
     * @return int|null
     */
    public function getNumericCode()
    {
        if (null !== $this->_currency) {
            return $this->_currency->numericCode;
        }
    }

    /**
     * @return string|null
     */
    public function getEntity()
    {
        if (null !== $this->_currency) {
            return $this->_currency->entity;
        }
    }

    /**
     * @return int|null
     */
    public function getMinorUnit()
    {
        if (null !== $this->_currency) {
            return $this->_currency->minorUnit;
        }
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
        if (null !== $this->_currency) {
            return $this->_currency->currency;
        }
    }

    /**
     * Sets the Currency Model data on the Payment Currency
     *
     * @param $currency
     */
    public function setCurrency(\craft\commerce\models\Currency $currency)
    {
        $this->_currency = $currency;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'iso' => AttributeType::String,
            'primary' => AttributeType::Bool,
            'rate' => AttributeType::Number
        ];
    }

}