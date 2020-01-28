<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\records\PaymentCurrency as PaymentCurrencyRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;

/**
 * Currency model.
 *
 * @property string $alphabeticCode
 * @property string $cpEditUrl
 * @property Currency $currency
 * @property string $entity
 * @property int $minorUnit
 * @property null|string $name
 * @property int $numericCode
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class PaymentCurrency extends Model
{
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
    public function __toString(): string
    {
        return (string)$this->iso;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/store-settings/paymentcurrencies/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'minorUnit';
        $names[] = 'alphabeticCode';
        $names[] = 'currency';
        $names[] = 'numericCode';
        $names[] = 'entity';
        return $names;
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
     * Returns alias of getCurrency()
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

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['iso'], 'required'];
        $rules[] = [['iso'], UniqueValidator::class, 'targetClass' => PaymentCurrencyRecord::class, 'targetAttribute' => ['iso']];

        return $rules;
    }
}
