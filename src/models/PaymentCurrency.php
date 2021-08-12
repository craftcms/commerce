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
use DateTime;

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
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string ISO code
     */
    public string $iso;

    /**
     * @var bool Is primary currency
     */
    public bool $primary;

    /**
     * @var float Exchange rate vs primary currency
     */
    public float $rate;

    /**
     * @var Currency
     */
    private Currency $_currency;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated;

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
    public function getAlphabeticCode(): ?string
    {
        if (isset($this->_currency)) {
            return $this->_currency->alphabeticCode;
        }

        return null;
    }

    /**
     * @return int|null
     */
    public function getNumericCode(): ?int
    {
        if (isset($this->_currency)) {
            return $this->_currency->numericCode;
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getEntity(): ?string
    {
        if (isset($this->_currency)) {
            return $this->_currency->entity;
        }

        return null;
    }

    /**
     * @return int|null
     */
    public function getMinorUnit(): ?int
    {
        if (isset($this->_currency)) {
            return $this->_currency->minorUnit;
        }

        return null;
    }

    /**
     * Returns alias of getCurrency()
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getCurrency();
    }

    /**
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        if (isset($this->_currency)) {
            return $this->_currency->currency;
        }

        return null;
    }

    /**
     * Sets the Currency Model data on the Payment Currency
     *
     * @param $currency
     */
    public function setCurrency(Currency $currency): void
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
        $rules[] = [['rate'], 'required'];
        $rules[] = [['iso'], UniqueValidator::class, 'targetClass' => PaymentCurrencyRecord::class, 'targetAttribute' => ['iso']];

        return $rules;
    }
}
