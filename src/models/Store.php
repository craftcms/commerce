<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\commerce\records\Store as StoreRecord;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\validators\UniqueValidator;
use Illuminate\Support\Collection;
use yii\base\InvalidConfigException;

/**
 * Store model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 *
 * @property-read StoreSettings|null $settings
 * @property-write string $name
 * @property-read array $config
 */
class Store extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null
     */
    private ?string $_name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var bool Primary store?
     */
    public bool $primary = false;

    /**
     * @var int Sort order
     */
    public int $sortOrder = 99;

    /**
     * @var bool
     * @see setAutoSetNewCartAddresses()
     * @see getAutoSetNewCartAddresses()
     */
    private bool|string $_autoSetNewCartAddresses = false;

    /**
     * @var bool
     * @see setAutoSetCartShippingMethodOption()
     * @see getAutoSetCartShippingMethodOption()
     */
    private bool|string $_autoSetCartShippingMethodOption = false;

    /**
     * @var bool
     * @see setAutoSetPaymentSource()
     * @see getAutoSetPaymentSource()
     */
    private bool|string $_autoSetPaymentSource = false;

    /**
     * @var bool
     * @see setAllowEmptyCartOnCheckout()
     * @see getAllowEmptyCartOnCheckout()
     */
    private bool|string $_allowEmptyCartOnCheckout = false;

    /**
     * @var bool
     * @see setAllowCheckoutWithoutPayment()
     * @see getAllowCheckoutWithoutPayment()
     */
    private bool|string $_allowCheckoutWithoutPayment = false;

    /**
     * @var bool
     * @see setAllowPartialPaymentOnCheckout()
     * @see getAllowPartialPaymentOnCheckout()
     */
    private bool|string $_allowPartialPaymentOnCheckout = false;

    /**
     * @var bool
     * @see setRequireShippingAddressAtCheckout()
     * @see getRequireShippingAddressAtCheckout()
     */
    private bool|string $_requireShippingAddressAtCheckout = false;

    /**
     * @var bool
     * @see setRequireBillingAddressAtCheckout()
     * @see getRequireBillingAddressAtCheckout()
     */
    private bool|string $_requireBillingAddressAtCheckout = false;

    /**
     * @var bool
     * @see setRequireShippingMethodSelectionAtCheckout()
     * @see getRequireShippingMethodSelectionAtCheckout()
     */
    private bool|string $_requireShippingMethodSelectionAtCheckout = false;

    /**
     * @var bool
     * @see setUseBillingAddressForTax()
     * @see getUseBillingAddressForTax()
     */
    private bool|string $_useBillingAddressForTax = false;

    /**
     * @var bool
     * @see setValidateBusinessTaxIdAsVatId()
     * @see getValidateBusinessTaxIdAsVatId()
     */
    private bool|string $_validateBusinessTaxIdAsVatId = false;

    /**
     * @var string
     * @see setOrderReferenceFormat()
     * @see getOrderReferenceFormat()
     */
    private string $_orderReferenceFormat = '{{number[:7]}}';

    /**
     * @var string|null Store UID
     */
    public ?string $uid = null;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['handle'], UniqueValidator::class, 'targetClass' => StoreRecord::class, 'targetAttribute' => ['handle']];
        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [[
            'autoSetNewCartAddresses',
            'autoSetCartShippingMethodOption',
            'autoSetPaymentSource',
            'allowEmptyCartOnCheckout',
            'allowCheckoutWithoutPayment',
            'allowPartialPaymentOnCheckout',
            'requireShippingAddressAtCheckout',
            'requireBillingAddressAtCheckout',
            'requireShippingMethodSelectionAtCheckout',
            'useBillingAddressForTax',
            'validateBusinessTaxIdAsVatId',
            'id',
            'primary',
            'sortOrder',
            'uid',
        ], 'safe'];

        return $rules;
    }

    /**
     * Returns the store’s name.
     *
     * @param bool $parse Whether to parse the name for an environment variable
     * @return string
     */
    public function getName(bool $parse = true): string
    {
        return ($parse ? App::parseEnv($this->_name) : $this->_name) ?? '';
    }

    /**
     * Sets the store’s name.
     *
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->_name = $name;
    }

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'name' => fn() => $this->getName(false),
                ],
            ],
        ];
    }

    /**
     * Gets the CP url to these stores settings
     *
     * @param string|null $path
     * @return string
     */
    public function getStoreSettingsUrl(?string $path = null): string
    {
        $path = $path ? '/' . $path : '';
        return UrlHelper::cpUrl('commerce/store-settings/' . $this->handle . $path);
    }

    /**
     * @return StoreSettings
     */
    public function getSettings(): StoreSettings
    {
        return Plugin::getInstance()->getStoreSettings()->getStoreSettingsById($this->id);
    }

    /**
     * Returns the sites that are related to this store.
     *
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getSites(): Collection
    {
        return Plugin::getInstance()->getStores()->getAllSitesForStore($this);
    }

    /**
     * Returns the names of the sites related to this store
     *
     * @return Collection<string>
     * @throws InvalidConfigException
     */
    public function getSiteNames(): Collection
    {
        return collect($this->getSites())->map(function(Site $site) {
            return $site->getName();
        });
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'name' => Craft::t('commerce', 'Name'),
            'commerce' => Craft::t('commerce', 'Handle'),
            'primary' => Craft::t('commerce', 'primary'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $attributes = parent::attributes();
        $attributes[] = 'name';
        return $attributes;
    }

    /**
     * Returns the project config data for this store.
     */
    public function getConfig(): array
    {
        return [
            'autoSetNewCartAddresses' => $this->getAutoSetCartShippingMethodOption(false),
            'autoSetCartShippingMethodOption' => $this->getAutoSetCartShippingMethodOption(false),
            'autoSetPaymentSource' => $this->getAutoSetPaymentSource(false),
            'allowEmptyCartOnCheckout' => $this->getAllowEmptyCartOnCheckout(false),
            'allowCheckoutWithoutPayment' => $this->getAllowCheckoutWithoutPayment(false),
            'allowPartialPaymentOnCheckout' => $this->getAllowPartialPaymentOnCheckout(false),
            'requireShippingAddressAtCheckout' => $this->getRequireShippingAddressAtCheckout(false),
            'requireBillingAddressAtCheckout' => $this->getRequireBillingAddressAtCheckout(false),
            'requireShippingMethodSelectionAtCheckout' => $this->getRequireShippingMethodSelectionAtCheckout(false),
            'useBillingAddressForTax' => $this->getUseBillingAddressForTax(false),
            'validateBusinessTaxIdAsVatId' => $this->getValidateBusinessTaxIdAsVatId(false),
            'handle' => $this->handle,
            'name' => $this->_name,
            'primary' => $this->primary,
            'sortOrder' => $this->sortOrder,
        ];
    }

    /**
     * @param bool|string $autoSetNewCartAddresses
     * @return void
     */
    public function setAutoSetNewCartAddresses(bool|string $autoSetNewCartAddresses): void
    {
        $this->_autoSetNewCartAddresses = $autoSetNewCartAddresses;
    }

    /**
     * Whether the user’s primary shipping and billing addresses should be set automatically on new carts.
     *
     * @param bool $parse
     * @return bool|string
     */
    public function getAutoSetNewCartAddresses(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_autoSetNewCartAddresses) : $this->_autoSetNewCartAddresses;
    }

    /**
     * @param bool|string $autoSetCartShippingMethodOption
     * @return void
     */
    public function setAutoSetCartShippingMethodOption(bool|string $autoSetCartShippingMethodOption): void
    {
        $this->_autoSetCartShippingMethodOption = $autoSetCartShippingMethodOption;
    }

    /**
     * Whether the first available shipping method option should be set automatically on carts.
     *
     * @param bool $parse
     * @return bool|string
     */
    public function getAutoSetCartShippingMethodOption(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_autoSetCartShippingMethodOption) : $this->_autoSetCartShippingMethodOption;
    }

    /**
     * @param bool|string $autoSetPaymentSource
     * @return void
     */
    public function setAutoSetPaymentSource(bool|string $autoSetPaymentSource): void
    {
        $this->_autoSetPaymentSource = $autoSetPaymentSource;
    }

    /**
     * Whether the user’s primary payment source should be set automatically on new carts.
     *
     * @param bool $parse
     * @return bool|string
     */
    public function getAutoSetPaymentSource(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_autoSetPaymentSource) : $this->_autoSetPaymentSource;
    }

    /**
     * @param bool|string $allowEmptyCartOnCheckout
     * @return void
     */
    public function setAllowEmptyCartOnCheckout(bool|string $allowEmptyCartOnCheckout): void
    {
        $this->_allowEmptyCartOnCheckout = $allowEmptyCartOnCheckout;
    }

    /**
     * @param bool $parse
     * @return bool|string
     */
    public function getAllowEmptyCartOnCheckout(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_allowEmptyCartOnCheckout) : $this->_allowEmptyCartOnCheckout;
    }

    /**
     * @param bool|string $allowCheckoutWithoutPayment
     * @return void
     */
    public function setAllowCheckoutWithoutPayment(bool|string $allowCheckoutWithoutPayment): void
    {
        $this->_allowCheckoutWithoutPayment = $allowCheckoutWithoutPayment;
    }

    /**
     * @param bool $parse
     * @return bool|string
     */
    public function getAllowCheckoutWithoutPayment(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_allowCheckoutWithoutPayment) : $this->_allowCheckoutWithoutPayment;
    }

    /**
     * @param bool|string $allowPartialPaymentOnCheckout
     * @return void
     */
    public function setAllowPartialPaymentOnCheckout(bool|string $allowPartialPaymentOnCheckout): void
    {
        $this->_allowPartialPaymentOnCheckout = $allowPartialPaymentOnCheckout;
    }

    /**
     * @param bool $parse
     * @return bool|string
     */
    public function getAllowPartialPaymentOnCheckout(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_allowPartialPaymentOnCheckout) : $this->_allowPartialPaymentOnCheckout;
    }

    /**
     * @param bool|string $requireShippingAddressAtCheckout
     * @return void
     */
    public function setRequireShippingAddressAtCheckout(bool|string $requireShippingAddressAtCheckout): void
    {
        $this->_requireShippingAddressAtCheckout = $requireShippingAddressAtCheckout;
    }

    /**
     * @param bool $parse
     * @return bool|string
     */
    public function getRequireShippingAddressAtCheckout(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_requireShippingAddressAtCheckout) : $this->_requireShippingAddressAtCheckout;
    }

    /**
     * @param bool|string $requireBillingAddressAtCheckout
     * @return void
     */
    public function setRequireBillingAddressAtCheckout(bool|string $requireBillingAddressAtCheckout): void
    {
        $this->_requireBillingAddressAtCheckout = $requireBillingAddressAtCheckout;
    }

    /**
     * @param bool $parse
     * @return bool|string
     */
    public function getRequireBillingAddressAtCheckout(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_requireBillingAddressAtCheckout) : $this->_requireBillingAddressAtCheckout;
    }

    /**
     * @param bool|string $requireShippingMethodSelectionAtCheckout
     * @return void
     */
    public function setRequireShippingMethodSelectionAtCheckout(bool|string $requireShippingMethodSelectionAtCheckout): void
    {
        $this->_requireShippingMethodSelectionAtCheckout = $requireShippingMethodSelectionAtCheckout;
    }

    /**
     * @param bool $parse
     * @return bool|string
     */
    public function getRequireShippingMethodSelectionAtCheckout(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_requireShippingMethodSelectionAtCheckout) : $this->_requireShippingMethodSelectionAtCheckout;
    }

    /**
     * @param bool|string $useBillingAddressForTax
     * @return void
     */
    public function setUseBillingAddressForTax(bool|string $useBillingAddressForTax): void
    {
        $this->_useBillingAddressForTax = $useBillingAddressForTax;
    }

    /**
     * @param bool $parse
     * @return bool|string
     */
    public function getUseBillingAddressForTax(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_useBillingAddressForTax) : $this->_useBillingAddressForTax;
    }

    /**
     * @param bool|string $validateBusinessTaxIdAsVatId
     * @return void
     */
    public function setValidateBusinessTaxIdAsVatId(bool|string $validateBusinessTaxIdAsVatId): void
    {
        $this->_validateBusinessTaxIdAsVatId = $validateBusinessTaxIdAsVatId;
    }

    /**
     * @param bool $parse
     * @return bool|string
     */
    public function getValidateBusinessTaxIdAsVatId(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_validateBusinessTaxIdAsVatId) : $this->_validateBusinessTaxIdAsVatId;
    }

    /**
     * @param bool|string $orderReferenceFormat
     * @return void
     */
    public function setOrderReferenceFormat(bool|string $orderReferenceFormat): void
    {
        $this->_orderReferenceFormat = $orderReferenceFormat;
    }

    /**
     * @param bool $parse
     * @return string
     */
    public function getOrderReferenceFormat(bool $parse = true): string
    {
        return $parse ? App::parseEnv($this->_orderReferenceFormat) : $this->_orderReferenceFormat;
    }
}
