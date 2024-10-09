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
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Store as StoreRecord;
use craft\errors\DeprecationException;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\models\Site;
use craft\validators\UniqueValidator;
use Illuminate\Support\Collection;
use Money\Currency as MoneyCurrency;
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
    public const MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT = 'default';
    public const MINIMUM_TOTAL_PRICE_STRATEGY_ZERO = 'zero';
    public const MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING = 'shipping';

    public const FREE_ORDER_PAYMENT_STRATEGY_COMPLETE = 'complete';
    public const FREE_ORDER_PAYMENT_STRATEGY_PROCESS = 'process';

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

    private ?string $_currency = 'USD';

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

    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'settings.locationAddress';

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'name';
        $names[] = 'countries';
        $names[] = 'marketAddressCondition';
        $names[] = 'settings';
        return $names;
    }

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
     * @see setValidateOrganizationTaxIdAsVatId()
     * @see getValidateOrganizationTaxIdAsVatId()
     */
    private bool|string $_validateOrganizationTaxIdAsVatId = false;

    /**
     * @var string
     * @see setOrderReferenceFormat()
     * @see getOrderReferenceFormat()
     */
    private string $_orderReferenceFormat = '{{number[:7]}}';

    /**
     * @var string
     * @see setFreeOrderPaymentStrategy()
     * @see getFreeOrderPaymentStrategy()
     */
    private string $_freeOrderPaymentStrategy = 'complete';

    /**
     * @var string
     * @see setMinimumTotalPriceStrategy()
     * @see getMinimumTotalPriceStrategy()
     */
    private string $_minimumTotalPriceStrategy = 'default';

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
        $rules[] = [
            ['currency'],
            // Only allow changing of currency if the store has no orders
            function($attribute) {
                $isCurrencyChanging = \craft\commerce\records\Store::findOne(['id' => $this->id, 'currency' => $this->$attribute]) === null;

                if (!$isCurrencyChanging) {
                    return;
                }

                $hasOrders = Order::find()
                    ->trashed(null)
                    ->storeId($this->id)
                    ->exists();

                if ($hasOrders) {
                    $this->addError($attribute, Craft::t('commerce', 'The primary currency cannot be changed after orders are placed.'));
                }
            },
            'when' => fn() => $this->id,
        ];
        $rules[] = [[
            'allowCheckoutWithoutPayment',
            'allowEmptyCartOnCheckout',
            'allowPartialPaymentOnCheckout',
            'autoSetCartShippingMethodOption',
            'autoSetNewCartAddresses',
            'autoSetPaymentSource',
            'freeOrderPaymentStrategy',
            'id',
            'orderReferenceFormat',
            'primary',
            'requireBillingAddressAtCheckout',
            'requireShippingAddressAtCheckout',
            'requireShippingMethodSelectionAtCheckout',
            'sortOrder',
            'uid',
            'useBillingAddressForTax',
            'validateOrganizationTaxIdAsVatId',
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
        return UrlHelper::cpUrl('commerce/store-management/' . $this->handle . $path);
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
            'primary' => Craft::t('commerce', 'Primary'),
        ];
    }

    /**
     * Returns the project config data for this store.
     */
    public function getConfig(): array
    {
        return [
            'allowCheckoutWithoutPayment' => $this->getAllowCheckoutWithoutPayment(false),
            'allowEmptyCartOnCheckout' => $this->getAllowEmptyCartOnCheckout(false),
            'allowPartialPaymentOnCheckout' => $this->getAllowPartialPaymentOnCheckout(false),
            'autoSetCartShippingMethodOption' => $this->getAutoSetCartShippingMethodOption(false),
            'autoSetNewCartAddresses' => $this->getAutoSetNewCartAddresses(false),
            'autoSetPaymentSource' => $this->getAutoSetPaymentSource(false),
            'freeOrderPaymentStrategy' => $this->getFreeOrderPaymentStrategy(false),
            'handle' => $this->handle,
            'minimumTotalPriceStrategy' => $this->getMinimumTotalPriceStrategy(false),
            'name' => $this->_name,
            'orderReferenceFormat' => $this->getOrderReferenceFormat(false),
            'primary' => $this->primary,
            'requireBillingAddressAtCheckout' => $this->getRequireBillingAddressAtCheckout(false),
            'requireShippingAddressAtCheckout' => $this->getRequireShippingAddressAtCheckout(false),
            'requireShippingMethodSelectionAtCheckout' => $this->getRequireShippingMethodSelectionAtCheckout(false),
            'sortOrder' => $this->sortOrder,
            'useBillingAddressForTax' => $this->getUseBillingAddressForTax(false),
            'validateOrganizationTaxIdAsVatId' => $this->getValidateOrganizationTaxIdAsVatId(false),
            'currency' => $this->getCurrency()->getCode(),
        ];
    }

    /**
     * Returns a key-value array of `freeOrderPaymentStrategy` options and labels.
     */
    public function getFreeOrderPaymentStrategyOptions(): array
    {
        return [
            self::FREE_ORDER_PAYMENT_STRATEGY_COMPLETE => Craft::t('commerce', 'Free orders complete immediately'),
            self::FREE_ORDER_PAYMENT_STRATEGY_PROCESS => Craft::t('commerce', 'Free orders are processed by the payment gateway'),
        ];
    }

    /**
     * Returns a key-value array of `minimumTotalPriceStrategy` options and labels.
     */
    public function getMinimumTotalPriceStrategyOptions(): array
    {
        return [
            self::MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT => Craft::t('commerce', 'Default - Allow the price to be negative if discounts are greater than the order value.'),
            self::MINIMUM_TOTAL_PRICE_STRATEGY_ZERO => Craft::t('commerce', 'Zero - Minimum price is zero if discounts are greater than the order value.'),
            self::MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING => Craft::t('commerce', 'Shipping - Minimum cost is the shipping cost, if the order price is less than the shipping cost.'),
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
     * Whether carts are allowed to be empty on checkout.
     *
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
     * Whether carts are can be marked as completed without a payment.
     *
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
     * Whether [partial payment](https://craftcms.com/docs/commerce/5.x/system/development/making-payments.html#checkout-with-partial-payment) can be made from the front end when the gateway allows them.
     *
     * The `false` default does not allow partial payments on the front end.
     *
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
     * Whether a billing address is required before making payment on an order.
     *
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
     * Whether shipping method selection is required before making payment on an order.
     *
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
     * Whether taxes should be calculated based on the billing address instead of the shipping address.
     *
     * @param bool $parse
     * @return bool|string
     */
    public function getUseBillingAddressForTax(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_useBillingAddressForTax) : $this->_useBillingAddressForTax;
    }

    /**
     * @param bool|string $validateOrganizationTaxIdAsVatId
     * @return void
     */
    public function setValidateOrganizationTaxIdAsVatId(bool|string $validateOrganizationTaxIdAsVatId): void
    {
        $this->_validateOrganizationTaxIdAsVatId = $validateOrganizationTaxIdAsVatId;
    }

    /**
     * @param bool $parse
     * @return bool|string Whether to enable validation requiring the `organizationTaxId` to be a valid VAT ID.
     *
     * When set to `false`, no validation is applied to `organizationTaxId`.
     *
     * When set to `true`, `organizationTaxId` must contain a valid VAT ID.
     *
     * ::: tip
     * This setting strictly toggles input validation and has no impact on tax configuration or behavior elsewhere in the system.
     * :::
     */
    public function getValidateOrganizationTaxIdAsVatId(bool $parse = true): bool|string
    {
        return $parse ? App::parseBooleanEnv($this->_validateOrganizationTaxIdAsVatId) : $this->_validateOrganizationTaxIdAsVatId;
    }

    /**
     * @param string|null $orderReferenceFormat
     * @return void
     */
    public function setOrderReferenceFormat(?string $orderReferenceFormat): void
    {
        if (!$orderReferenceFormat) {
            return;
        }

        $this->_orderReferenceFormat = $orderReferenceFormat;
    }

    /**
     * Human-friendly reference number format for orders. Result must be unique.
     *
     * See [Order Numbers](https://craftcms.com/docs/commerce/5.x/system/orders-carts.html#order-numbers).
     *
     * @param bool $parse
     * @return string
     */
    public function getOrderReferenceFormat(bool $parse = true): string
    {
        return $parse ? App::parseEnv($this->_orderReferenceFormat) : $this->_orderReferenceFormat;
    }

    /**
     * @param string $freeOrderPaymentStrategy
     * @return void
     */
    public function setFreeOrderPaymentStrategy(string $freeOrderPaymentStrategy): void
    {
        $this->_freeOrderPaymentStrategy = $freeOrderPaymentStrategy;
    }

    /**
     * How Commerce should handle free orders.
     *
     * The default `'complete'` setting automatically completes zero-balance orders without forwarding them to the payment gateway.
     *
     * The `'process'` setting forwards zero-balance orders to the payment gateway for processing. This can be useful if the customer’s balance
     * needs to be updated or otherwise adjusted by the payment gateway.
     *
     * @param bool $parse
     * @return string
     */
    public function getFreeOrderPaymentStrategy(bool $parse = true): string
    {
        return $parse ? App::parseEnv($this->_freeOrderPaymentStrategy) : $this->_freeOrderPaymentStrategy;
    }

    /**
     * @param string $minimumTotalPriceStrategy
     * @return void
     */
    public function setMinimumTotalPriceStrategy(string $minimumTotalPriceStrategy): void
    {
        $this->_minimumTotalPriceStrategy = $minimumTotalPriceStrategy;
    }

    /**
     * How Commerce should handle minimum total price for an order.
     *
     * Options:
     *
     * - `'default'` [rounds](commerce4:\craft\commerce\helpers\Currency::round()) the sum of the item subtotal and adjustments.
     * - `'zero'` returns `0` if the result from `'default'` would’ve been negative; minimum order total is `0`.
     * - `'shipping'` returns the total shipping cost if the `'default'` result would’ve been negative; minimum order total equals shipping amount.
     *
     * @param bool $parse
     * @return string
     */
    public function getMinimumTotalPriceStrategy(bool $parse = true): string
    {
        return $parse ? App::parseEnv($this->_minimumTotalPriceStrategy) : $this->_minimumTotalPriceStrategy;
    }

    /**
     * @param mixed $countries
     * @return void
     * @throws DeprecationException
     * @throws InvalidConfigException
     * @deprecated in 5.0.0. Use [[Store::getSettings()->setCountries()]] instead.
     */
    public function setCountries(mixed $countries): void
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Store::setCountries() is deprecated. Use Store::getSettings()->setCountries() instead.');
        $this->getSettings()->setCountries($countries);
    }

    /**
     * @return string[] $countries
     * @deprecated in 5.0.0. Use [[Store::getSettings()->getCountries()]] instead.
     */
    public function getCountries(): array
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Store::getCountries() is deprecated. Use Store::getSettings()->getCountries() instead.');
        return $this->getSettings()->getCountries();
    }

    /**
     * @return array
     * @throws DeprecationException
     * @deprecated in 5.0.0. Use [[Store::getSettings()->getCountriesList()]] instead.
     */
    public function getCountriesList(): array
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Store::getCountriesList() has been deprecated. Use Store::getSettings()->getCountriesList() instead.');
        return $this->getSettings()->getCountriesList();
    }

    /**
     * @return array
     * @throws DeprecationException
     * @deprecated in 5.0.0. Use [[Store::getSettings()->getAdministrativeAreasListByCountryCode()]] instead.
     */
    public function getAdministrativeAreasListByCountryCode(): array
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Store::getAdministrativeAreasListByCountryCode() has been deprecated. Use Store::getSettings()->getAdministrativeAreasListByCountryCode() instead.');
        return $this->getSettings()->getAdministrativeAreasListByCountryCode();
    }

    /**
     * @return ZoneAddressCondition
     * @deprecated in 5.0.0. Use [[Store::getSettings()->getMarketAddressCondition()]] instead.
     */
    public function getMarketAddressCondition(): ZoneAddressCondition
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'Store::getMarketAddressCondition() has been deprecated. Use Store::getSettings()->getMarketAddressCondition() instead.');
        return $this->getSettings()->getMarketAddressCondition();
    }

    /**
     * @return MoneyCurrency|null
     */
    public function getCurrency(): ?MoneyCurrency
    {
        return $this->_currency ? (new MoneyCurrency($this->_currency)) : null;
    }

    /**
     * @param string|MoneyCurrency $currency
     * @return void
     */
    public function setCurrency(string|MoneyCurrency $currency): void
    {
        if ($currency instanceof MoneyCurrency) {
            $currency = $currency->getCode();
        }

        $this->_currency = $currency;
    }

    /**
     * Returns the inventory locations related to this store.
     *
     * @return Collection
     * @throws InvalidConfigException
     * @throws \craft\errors\DeprecationException
     */
    public function getInventoryLocations(): Collection
    {
        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocations($this->id);
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    public function getInventoryLocationsOptions(): array
    {
        return Plugin::getInstance()->getInventoryLocations()->getInventoryLocations($this->id)->map(function($location) {
            return ['value' => $location->id, 'label' => $location->getUiLabel()];
        })->toArray();
    }
}
