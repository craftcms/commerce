<?php
namespace Craft;

use JsonSerializable;

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
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.2
 */
class Commerce_PaymentCurrencyModel extends BaseModel implements JsonSerializable
{
    private $_currency;

    /**
     * @inheritdoc
     *
     * @param mixed $values
     *
     * @return
     */
    public static function populateModel($values)
    {
        if ($values instanceof \CModel)
        {
            $values = $values->getAttributes();
        }
        /** @var Commerce_PaymentCurrencyModel $currency */
        $currency = parent::populateModel($values);

        $iso = $values['iso'];
        if ($currencyModel = craft()->commerce_currencies->getCurrencyByIso($iso))
        {
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
    function __toString()
    {
        return $this->iso;
    }

    /**
     * @return array
     */
    function jsonSerialize()
    {
        $data = [];
        $data['id'] = $this->getAttribute('id');
        $data['iso'] = $this->getAttribute('iso');
        $data['primary'] = $this->getAttribute('primary');
        $data['rate'] = $this->getAttribute('rate');
        $data['minorUnits'] = $this->getMinorUnits();
        $data['alphabeticCode'] = $this->getAlphabeticCode();
        $data['currency'] = $this->getCurrency();
        $data['numericCode'] = $this->getNumericCode();
        $data['entity'] = $this->getEntity();

        return $data;
    }

    /**
     * @return int|null
     */
    public function getMinorUnit()
    {
        if (isset($this->_currency))
        {
            return $this->_currency->minorUnit;
        }
    }

    /**
     * @return string|null
     */
    public function getAlphabeticCode()
    {
        if (isset($this->_currency))
        {
            return $this->_currency->alphabeticCode;
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
        if (isset($this->_currency))
        {
            return $this->_currency->currency;
        }
    }

    /**
     * Sets the Currency Model data on the Payment Currency
     *
     * @param $currency
     */
    public function setCurrency(Commerce_CurrencyModel $currency)
    {
        $this->_currency = $currency;
    }

    /**
     * @return int|null
     */
    public function getNumericCode()
    {
        if (isset($this->_currency))
        {
            return $this->_currency->numericCode;
        }
    }

    /**
     * @return string|null
     */
    public function getEntity()
    {
        if (isset($this->_currency))
        {
            return $this->_currency->entity;
        }
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return ['id'      => AttributeType::Number,
                'iso'     => AttributeType::String,
                'primary' => AttributeType::Bool,
                'rate'    => AttributeType::Number];
    }

}