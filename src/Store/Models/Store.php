<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Models;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\InventoryLocations;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Money\Currency as MoneyCurrency;
use function CraftCms\Cms\t;

class Store extends Component
{
    public const MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT = 'default';
    public const MINIMUM_TOTAL_PRICE_STRATEGY_ZERO = 'zero';
    public const MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING = 'shipping';
    public const FREE_ORDER_PAYMENT_STRATEGY_COMPLETE = 'complete';
    public const FREE_ORDER_PAYMENT_STRATEGY_PROCESS = 'process';

    public ?int $id = null;

    private ?string $_name = null;

    public ?string $handle = null;

    public bool $primary = false;

    public int $sortOrder = 99;

    private string $_currency = 'USD';

    private bool|string $_autoSetNewCartAddresses = false;
    private bool|string $_autoSetCartShippingMethodOption = false;
    private bool|string $_autoSetPaymentSource = false;
    private bool|string $_allowEmptyCartOnCheckout = false;
    private bool|string $_allowCheckoutWithoutPayment = false;
    private bool|string $_allowPartialPaymentOnCheckout = false;
    private bool|string $_requireShippingAddressAtCheckout = false;
    private bool|string $_requireBillingAddressAtCheckout = false;
    private bool|string $_requireShippingMethodSelectionAtCheckout = false;
    private bool|string $_useBillingAddressForTax = false;
    private bool|string $_validateOrganizationTaxIdAsVatId = false;
    private string $_orderReferenceFormat = '{{number[:7]}}';
    private string $_freeOrderPaymentStrategy = 'complete';
    private string $_minimumTotalPriceStrategy = 'default';

    public ?string $uid = null;

    #[\Override]
    public function fields(): array
    {
        $fields = parent::fields();
        $fields[] = 'name';
        $fields[] = 'settings';
        return $fields;
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string'],
            'handle' => ['required', 'string', Rule::unique(Table::STORES, 'handle')->ignore($this->id)],
            'currency' => [
                function($attribute, $value, \Closure $fail) {
                    if (!$this->id) {
                        return;
                    }
                    $isCurrencyChanging = \CraftCms\Commerce\Store\Records\Store::where('id', $this->id)
                        ->where('currency', $value)
                        ->doesntExist();
                    if (!$isCurrencyChanging) {
                        return;
                    }
                    $hasOrders = Order::find()
                        /** @phpstan-ignore-next-line */
                        ->trashed(null)
                        ->storeId($this->id)
                        ->exists();
                    if ($hasOrders) {
                        $fail(t('The primary currency cannot be changed after orders are placed.', category: 'commerce'));
                    }
                },
            ],
        ];
    }

    public function getName(bool $parse = true): string
    {
        return ($parse ? Env::parse($this->_name) : $this->_name) ?? '';
    }

    public function setName(string $name): void
    {
        $this->_name = $name;
    }

    public function getStoreSettingsUrl(?string $path = null): string
    {
        $path = $path ? '/' . $path : '';
        return Url::cpUrl('commerce/store-management/' . $this->handle . $path);
    }

    public function getSettings(): StoreSettings
    {
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getStoreSettings()->getStoreSettingsById($this->id);
    }

    public function getSites(): Collection
    {
        /** @phpstan-ignore-next-line */
        return Plugin::getInstance()->getStores()->getAllSitesForStore($this);
    }

    /**
     * @return Collection<int, string>
     */
    public function getSiteNames(): Collection
    {
        return collect($this->getSites())->map(fn(Site $site) => $site->getName());
    }

    #[\Override]
    public function attributeLabels(): array
    {
        return [
            'name' => t('Name', category: 'commerce'),
            'commerce' => t('Handle', category: 'commerce'),
            'primary' => t('Primary', category: 'commerce'),
        ];
    }

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

    public function getFreeOrderPaymentStrategyOptions(): array
    {
        return [
            self::FREE_ORDER_PAYMENT_STRATEGY_COMPLETE => t('Free orders complete immediately', category: 'commerce'),
            self::FREE_ORDER_PAYMENT_STRATEGY_PROCESS => t('Free orders are processed by the payment gateway', category: 'commerce'),
        ];
    }

    public function getMinimumTotalPriceStrategyOptions(): array
    {
        return [
            self::MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT => t('Default - Allow the price to be negative if discounts are greater than the order value.', category: 'commerce'),
            self::MINIMUM_TOTAL_PRICE_STRATEGY_ZERO => t('Zero - Minimum price is zero if discounts are greater than the order value.', category: 'commerce'),
            self::MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING => t('Shipping - Minimum cost is the shipping cost, if the order price is less than the shipping cost.', category: 'commerce'),
        ];
    }

    public function setAutoSetNewCartAddresses(bool|string $autoSetNewCartAddresses): void
    {
        $this->_autoSetNewCartAddresses = $autoSetNewCartAddresses;
    }

    public function getAutoSetNewCartAddresses(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_autoSetNewCartAddresses) ?? false) : $this->_autoSetNewCartAddresses;
    }

    public function setAutoSetCartShippingMethodOption(bool|string $autoSetCartShippingMethodOption): void
    {
        $this->_autoSetCartShippingMethodOption = $autoSetCartShippingMethodOption;
    }

    public function getAutoSetCartShippingMethodOption(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_autoSetCartShippingMethodOption) ?? false) : $this->_autoSetCartShippingMethodOption;
    }

    public function setAutoSetPaymentSource(bool|string $autoSetPaymentSource): void
    {
        $this->_autoSetPaymentSource = $autoSetPaymentSource;
    }

    public function getAutoSetPaymentSource(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_autoSetPaymentSource) ?? false) : $this->_autoSetPaymentSource;
    }

    public function setAllowEmptyCartOnCheckout(bool|string $allowEmptyCartOnCheckout): void
    {
        $this->_allowEmptyCartOnCheckout = $allowEmptyCartOnCheckout;
    }

    public function getAllowEmptyCartOnCheckout(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_allowEmptyCartOnCheckout) ?? false) : $this->_allowEmptyCartOnCheckout;
    }

    public function setAllowCheckoutWithoutPayment(bool|string $allowCheckoutWithoutPayment): void
    {
        $this->_allowCheckoutWithoutPayment = $allowCheckoutWithoutPayment;
    }

    public function getAllowCheckoutWithoutPayment(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_allowCheckoutWithoutPayment) ?? false) : $this->_allowCheckoutWithoutPayment;
    }

    public function setAllowPartialPaymentOnCheckout(bool|string $allowPartialPaymentOnCheckout): void
    {
        $this->_allowPartialPaymentOnCheckout = $allowPartialPaymentOnCheckout;
    }

    public function getAllowPartialPaymentOnCheckout(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_allowPartialPaymentOnCheckout) ?? false) : $this->_allowPartialPaymentOnCheckout;
    }

    public function setRequireShippingAddressAtCheckout(bool|string $requireShippingAddressAtCheckout): void
    {
        $this->_requireShippingAddressAtCheckout = $requireShippingAddressAtCheckout;
    }

    public function getRequireShippingAddressAtCheckout(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_requireShippingAddressAtCheckout) ?? false) : $this->_requireShippingAddressAtCheckout;
    }

    public function setRequireBillingAddressAtCheckout(bool|string $requireBillingAddressAtCheckout): void
    {
        $this->_requireBillingAddressAtCheckout = $requireBillingAddressAtCheckout;
    }

    public function getRequireBillingAddressAtCheckout(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_requireBillingAddressAtCheckout) ?? false) : $this->_requireBillingAddressAtCheckout;
    }

    public function setRequireShippingMethodSelectionAtCheckout(bool|string $requireShippingMethodSelectionAtCheckout): void
    {
        $this->_requireShippingMethodSelectionAtCheckout = $requireShippingMethodSelectionAtCheckout;
    }

    public function getRequireShippingMethodSelectionAtCheckout(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_requireShippingMethodSelectionAtCheckout) ?? false) : $this->_requireShippingMethodSelectionAtCheckout;
    }

    public function setUseBillingAddressForTax(bool|string $useBillingAddressForTax): void
    {
        $this->_useBillingAddressForTax = $useBillingAddressForTax;
    }

    public function getUseBillingAddressForTax(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_useBillingAddressForTax) ?? false) : $this->_useBillingAddressForTax;
    }

    public function setValidateOrganizationTaxIdAsVatId(bool|string $validateOrganizationTaxIdAsVatId): void
    {
        $this->_validateOrganizationTaxIdAsVatId = $validateOrganizationTaxIdAsVatId;
    }

    public function getValidateOrganizationTaxIdAsVatId(bool $parse = true): bool|string
    {
        return $parse ? (Env::parseBoolean($this->_validateOrganizationTaxIdAsVatId) ?? false) : $this->_validateOrganizationTaxIdAsVatId;
    }

    public function setOrderReferenceFormat(?string $orderReferenceFormat): void
    {
        if (!$orderReferenceFormat) {
            return;
        }

        $this->_orderReferenceFormat = $orderReferenceFormat;
    }

    public function getOrderReferenceFormat(bool $parse = true): string
    {
        return $parse ? (Env::parse($this->_orderReferenceFormat) ?? '') : $this->_orderReferenceFormat;
    }

    public function setFreeOrderPaymentStrategy(string $freeOrderPaymentStrategy): void
    {
        $this->_freeOrderPaymentStrategy = $freeOrderPaymentStrategy;
    }

    public function getFreeOrderPaymentStrategy(bool $parse = true): string
    {
        return $parse ? (Env::parse($this->_freeOrderPaymentStrategy) ?? '') : $this->_freeOrderPaymentStrategy;
    }

    public function setMinimumTotalPriceStrategy(string $minimumTotalPriceStrategy): void
    {
        $this->_minimumTotalPriceStrategy = $minimumTotalPriceStrategy;
    }

    public function getMinimumTotalPriceStrategy(bool $parse = true): string
    {
        return $parse ? (Env::parse($this->_minimumTotalPriceStrategy) ?? '') : $this->_minimumTotalPriceStrategy;
    }

    public function getCurrency(): ?MoneyCurrency
    {
        return $this->_currency ? (new MoneyCurrency($this->_currency)) : null;
    }

    public function setCurrency(string|MoneyCurrency $currency): void
    {
        if ($currency instanceof MoneyCurrency) {
            $currency = $currency->getCode();
        }

        $this->_currency = $currency;
    }

    public function getInventoryLocations(): Collection
    {
        return app(InventoryLocations::class)->getInventoryLocations($this->id);
    }

    public function getInventoryLocationsOptions(): array
    {
        return app(InventoryLocations::class)->getInventoryLocations($this->id)->map(fn($location) => ['value' => $location->id, 'label' => $location->getUiLabel()])->toArray();
    }
}
