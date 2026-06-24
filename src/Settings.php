<?php

declare(strict_types=1);

namespace CraftCms\Commerce;

use craft\commerce\Plugin;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Site\Exceptions\SiteNotFoundException;
use CraftCms\Cms\Support\Config;
use CraftCms\Cms\Support\Facades\Sites;
use function CraftCms\Cms\t;

class Settings extends Component
{
    public const VIEW_URI_ORDERS = 'commerce/orders';
    public const VIEW_URI_PRODUCTS = 'commerce/products';
    public const VIEW_URI_INVENTORY = 'commerce/inventory';
    public const VIEW_URI_STORE_MANAGEMENT = 'commerce/store-management';
    public const VIEW_URI_SUBSCRIPTIONS = 'commerce/subscriptions';

    public mixed $activeCartDuration = 3600;

    public string $cartVariable = 'cart';

    public string $defaultView = 'commerce/orders';

    public string $dimensionUnits = 'mm';

    public string $gatewayPostRedirectTemplate = '';

    public ?string $loadCartRedirectUrl = null;

    public ?array $paymentCurrency = null;

    public bool $pdfAllowRemoteImages = false;

    public bool $purgeInactiveCarts = true;

    public mixed $purgeInactiveCartsDuration = 7776000;

    public string $updateBillingDetailsUrl = '';

    public bool $updateCartSearchIndexes = true;

    public string $weightUnits = 'g';

    public bool $validateCartCustomFieldsOnSubmission = false;

    #[\Override]
    public function setAttributes($values): void
    {
        unset(
            $values['orderPdfFilenameFormat'],
            $values['orderPdfPath'],
            $values['emailSenderAddress'],
            $values['emailSenderAddressPlaceholder'],
            $values['emailSenderName'],
            $values['emailSenderNamePlaceholder'],
            $values['autoSetNewCartAddresses'],
            $values['autoSetCartShippingMethodOption'],
            $values['autoSetPaymentSource'],
            $values['allowEmptyCartOnCheckout'],
            $values['allowCheckoutWithoutPayment'],
            $values['allowPartialPaymentOnCheckout'],
            $values['orderReferenceFormat'],
            $values['requireShippingAddressAtCheckout'],
            $values['requireBillingAddressAtCheckout'],
            $values['requireShippingMethodSelectionAtCheckout'],
            $values['useBillingAddressForTax'],
            $values['freeOrderPaymentStrategy'],
            $values['minimumTotalPriceStrategy'],
            $values['showEditUserCommerceTab'],
        );
        parent::setAttributes($values);
    }

    public function getWeightUnitsOptions(): array
    {
        return [
            'g' => t('Grams (g)', category: 'commerce'),
            'kg' => t('Kilograms (kg)', category: 'commerce'),
            'lb' => t('Pounds (lb)', category: 'commerce'),
        ];
    }

    public function getDimensionUnits(): array
    {
        return [
            'mm' => t('Millimeters (mm)', category: 'commerce'),
            'cm' => t('Centimeters (cm)', category: 'commerce'),
            'm' => t('Meters (m)', category: 'commerce'),
            'ft' => t('Feet (ft)', category: 'commerce'),
            'in' => t('Inches (in)', category: 'commerce'),
        ];
    }

    /**
     * @throws SiteNotFoundException
     * @throws \InvalidArgumentException
     */
    public function getPaymentCurrency(?string $siteHandle = null): ?string
    {
        $site = $siteHandle ? Sites::getSiteByHandle($siteHandle) : Sites::getPrimarySite();
        if (!$site) {
            throw new \InvalidArgumentException("Invalid site: $siteHandle");
        }

        $paymentCurrency = Config::localizedValue($this->paymentCurrency, $siteHandle);
        /** @phpstan-ignore-next-line */
        $store = Plugin::getInstance()->getStores()->getStoreBySiteId($site->id);
        /** @phpstan-ignore-next-line */
        $allPaymentCurrencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies($store?->id);

        if ($paymentCurrency && !$allPaymentCurrencies->contains('iso', '==', $paymentCurrency)) {
            throw new \InvalidArgumentException("Invalid payment currency: $paymentCurrency");
        }

        return $paymentCurrency;
    }

    public function getDefaultViewOptions(): array
    {
        return [
            self::VIEW_URI_ORDERS => t('Orders', category: 'commerce'),
            self::VIEW_URI_PRODUCTS => t('Products', category: 'commerce'),
            self::VIEW_URI_INVENTORY => t('Inventory', category: 'commerce'),
            self::VIEW_URI_STORE_MANAGEMENT => t('Store Management', category: 'commerce'),
            self::VIEW_URI_SUBSCRIPTIONS => t('Subscriptions', category: 'commerce'),
        ];
    }

    #[\Override]
    public function getRules(): array
    {
        return [
            'weightUnits' => ['required', 'string'],
            'dimensionUnits' => ['required', 'string'],
        ];
    }
}
