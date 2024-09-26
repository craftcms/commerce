<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\events\PaymentCurrencyRateEvent;
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
	 * @event PaymentCurrencyRateEvent The event that is triggered when the payment currency rate is defined
	 */
	const EVENT_DEFINE_PAYMENT_CURRENCY_RATE = 'definePaymentCurrencyRate';

	/**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null ISO code
     */
    public ?string $iso = null;

    /**
     * @var bool Is primary currency
     */
    public bool $primary = false;

    /**
     * @var float Exchange rate vs primary currency
     */
    public float $rate = 1;

    /**
     * @var Currency
     */
    private Currency $_currency;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

    public function __toString(): string
    {
        return (string)$this->iso;
    }

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

    public function getAlphabeticCode(): ?string
    {
        if (isset($this->_currency)) {
            return $this->_currency->alphabeticCode;
        }

        return null;
    }

    public function getNumericCode(): ?int
    {
        if (isset($this->_currency)) {
            return $this->_currency->numericCode;
        }

        return null;
    }

    public function getEntity(): ?string
    {
        if (isset($this->_currency)) {
            return $this->_currency->entity;
        }

        return null;
    }

    public function getMinorUnit(): ?int
    {
        if (isset($this->_currency)) {
            return $this->_currency->minorUnit;
        }

        return null;
    }

    /**
     * Returns alias of getCurrency()
     */
    public function getName(): ?string
    {
        return $this->getCurrency();
    }

    public function getCurrency(): ?string
    {
        if (isset($this->_currency)) {
            return $this->_currency->currency;
        }

        return null;
    }

    /**
     * Sets the Currency Model data on the Payment Currency
     */
    public function setCurrency(Currency $currency): void
    {
        $this->_currency = $currency;
    }

	/**
	 * @param Transaction|null $transaction
	 * @return float
	 */
	public function getRate(Transaction $transaction = null) : float
	{
		$event = new PaymentCurrencyRateEvent([
			'rate' => $this->rate,
			'transaction' => $transaction,
		]);

		$this->trigger(self::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $event);

		return $this->rate;
	}

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['iso'], 'required'],
            [['rate'], 'required'],
            [['iso'], UniqueValidator::class, 'targetClass' => PaymentCurrencyRecord::class, 'targetAttribute' => ['iso']],
        ];
    }
}
