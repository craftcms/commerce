<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\commerce\records\PaymentCurrency as PaymentCurrencyRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use DateTime;
use Money\Currency;
use yii\base\InvalidConfigException;

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
     * @var int|null Store ID
     */
    public ?int $storeId = null;

    /**
     * @var string|null ISO code
     */
    public ?string $iso = null;

    /**
     * @var float Exchange rate vs primary currency
     */
    public float $rate = 1;

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

    /**
     * @return Currency
     */
    public function getCurrency(): Currency
    {
        return new Currency($this->iso);
    }

    /**
     * @return string
     * @throws InvalidConfigException
     */
    public function getCpEditUrl(): string
    {
        if ($this->storeId === null) {
            return '';
        }

        $store = Plugin::getInstance()->getStores()->getStoreById($this->storeId);
        if ($store === null) {
            throw new InvalidConfigException('Invalid store ID: ' . $this->storeId);
        }

        return UrlHelper::cpUrl(sprintf('commerce/store-management/%s/payment-currencies/%s', $store->handle, $this->id));
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

    public function safeAttributes()
    {
        $names = parent::safeAttributes();
        return array_unique(array_merge(['id', 'storeId', 'iso', 'rate'], $names));
    }

    /**
     * @return string|null
     */
    public function getAlphabeticCode(): ?string
    {
        return $this->iso;
    }

    /**
     * @return int|null
     * @throws InvalidConfigException
     */
    public function getNumericCode(): ?int
    {
        return Plugin::getInstance()->getCurrencies()->numericCodeFor($this->iso);
    }

    public function getEntity(): ?string
    {
        // TODO: Implement getEntity() method on \craft\commerce\services\Currencies::$_isoCurrencies
        return '';
    }

    /**
     * @return int|null
     * @throws InvalidConfigException
     * @deprecated Use getSubUnit() instead.
     */
    public function getMinorUnit(): ?int
    {
        return $this->getSubUnit();
    }

    /**
     * @return int|null
     * @throws InvalidConfigException
     */
    public function getSubUnit(): ?int
    {
        return Plugin::getInstance()->getCurrencies()->getSubunitFor($this->iso);
    }

    /**
     * Returns alias of getCurrency()
     */
    public function getName(): ?string
    {
        return $this->iso;
    }

    /**
     * @return Store
     * @throws InvalidConfigException
     */
    public function getStore()
    {
        return Plugin::getInstance()->getStores()->getStoreById($this->storeId);
    }

    /**
     * @return bool
     * @throws InvalidConfigException
     */
    public function getPrimary(): bool
    {
        return $this->getCode() === $this->getStore()->getCurrency()->getCode();
    }

    /**
     * @return string|null
     */
    public function getCode()
    {
        return $this->iso;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['iso', 'rate'], 'required'],
            [['iso'], UniqueValidator::class, 'targetClass' => PaymentCurrencyRecord::class, 'targetAttribute' => ['iso', 'storeId'], 'message' => '{attribute} "{value}" has already been taken.'],
        ];
    }
}
