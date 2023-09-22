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
     * @var bool Is primary currency
     */
    public bool $primary = false;

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

        return UrlHelper::cpUrl(sprintf('commerce/store-settings/%s/payment-currencies/%s', $store->handle, $this->id));
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
        return $this->iso;
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
            [['storeId'], 'safe'],
        ];
    }
}
