<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\errors\CurrencyException;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\ConfigHelper;
use yii\base\InvalidConfigException;

/**
 * Settings model.
 *
 * @property-read array $weightUnitsOptions
 * @property-read array $dimensionsUnits
 * @property-read array $minimumTotalPriceStrategyOptions
 * @property-read array $freeOrderPaymentStrategyOptions
 * @property-read array $defaultViewOptions
 * @property-read string $paymentCurrency
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Settings extends Model
{
    const MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT = 'default';
    const MINIMUM_TOTAL_PRICE_STRATEGY_ZERO = 'zero';
    const MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING = 'shipping';

    const FREE_ORDER_PAYMENT_STRATEGY_COMPLETE = 'complete';
    const FREE_ORDER_PAYMENT_STRATEGY_PROCESS = 'process';

    const VIEW_URI_ORDERS = 'commerce/orders';
    const VIEW_URI_PRODUCTS = 'commerce/products';
    const VIEW_URI_CUSTOMERS = 'commerce/customers';
    const VIEW_URI_PROMOTIONS = 'commerce/promotions';
    const VIEW_URI_SHIPPING = 'commerce/shipping/shippingmethods';
    const VIEW_URI_TAX = 'commerce/tax/taxrates';
    const VIEW_URI_SUBSCRIPTIONS = 'commerce/subscriptions';

    /**
     * @var mixed How long a cart should go without being updated before it’s considered inactive.
     *
     * See [craft\helpers\ConfigHelper::durationInSeconds()](craft3:craft\helpers\ConfigHelper::durationInSeconds()) for a list of supported value types.
     *
     * @group Cart
     * @since 2.2
     * @defaultAlt 1 hour
     */
    public $activeCartDuration = 3600;

    /**
     * @var bool Whether the customer’s primary shipping and billing addresses should be set automatically on new carts.
     * @group Cart
     */
    public bool $autoSetNewCartAddresses = true;

    /**
     * @var bool Whether carts are allowed to be empty on checkout.
     * @group Cart
     * @since 2.2
     */
    public bool $allowEmptyCartOnCheckout = false;

    /**
     * @var bool Whether carts are can be marked as completed without a payment.
     * @group Cart
     * @since 3.3
     */
    public bool $allowCheckoutWithoutPayment = false;

    /**
     * @var bool Whether partial payment can be made from the front end. Gateway must also allow them.
     *
     * The default `false` does not allow partial payments on the front end.
     *
     * @group Payments
     */
    public bool $allowPartialPaymentOnCheckout = false;

    /**
     * @var string Key to be used when returning cart information in a response.
     * @group Cart
     */
    public string $cartVariable = 'cart';

    /**
     * @var string Commerce’s default control panel view. (Defaults to order index.)
     * @group System
     * @since 2.2
     */
    public string $defaultView = 'commerce/orders';

    /**
     * @var string Unit type for dimension measurements.
     *
     * Options:
     *
     * - `'mm'`
     * - `'cm'`
     * - `'m'`
     * - `'ft'`
     * - `'in'`
     *
     * @group Units
     */
    public string $dimensionUnits = 'mm';

    /**
     * @var string|null Default email address Commerce system messages should be sent from.
     *
     * If `null` (default), Craft’s [MailSettings::$fromEmail](craft3:craft\models\MailSettings::$fromEmail) will be used.
     *
     * @group System
     */
    public ?string $emailSenderAddress = null;

    /**
     * @var string|null Placeholder value displayed for the sender address control panel settings field.
     *
     * If `null` (default), Craft’s [MailSettings::$fromEmail](craft3:craft\models\MailSettings::$fromEmail) will be used.
     *
     * @group System
     */
    public ?string $emailSenderAddressPlaceholder = null;

    /**
     * @var string|null Default from name used for Commerce system emails.
     *
     * If `null` (default), Craft’s [MailSettings::$fromName](craft3:craft\models\MailSettings::$fromName) will be used.
     *
     * @group System
     */
    public ?string $emailSenderName = null;

    /**
     * @var string|null Placeholder value displayed for the sender name control panel settings field.
     *
     * If `null` (default), Craft’s [MailSettings::$fromName](craft3:craft\models\MailSettings::$fromName) will be used.
     *
     * @group System
     */
    public ?string $emailSenderNamePlaceholder = null;

    /**
     * @var string How Commerce should handle free orders.
     *
     * The default `'complete'` setting automatically completes zero-balance orders without forwarding them to the payment gateway.
     *
     * The `'process'` setting forwards zero-balance orders to the payment gateway for processing. This can be useful if the customer’s balance
     * needs to be updated or otherwise adjusted by the payment gateway.
     *
     * @group Orders
     */
    public string $freeOrderPaymentStrategy = 'complete';

    /**
     * @var string The path to the template that should be used to perform POST requests to offsite payment gateways.
     *
     * The template must contain a form that posts to the URL supplied by the `actionUrl` variable and outputs all hidden inputs with
     * the `inputs` variable.
     *
     * ```twig
     * <!DOCTYPE html>
     * <html>
     * <head>
     *     <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
     *     <title>Redirecting...</title>
     * </head>
     * <body onload="document.forms[0].submit();">
     * <form action="{{ actionUrl }}" method="post">
     *     <p>Redirecting to payment page...</p>
     *     <p>
     *         {{ inputs|raw }}
     *         <input type="submit" value="Continue">
     *     </p>
     * </form>
     * </body>
     * </html>
     * ```
     *
     * ::: tip
     * Since this template is simply used for redirecting, it only appears for a few seconds, so we suggest making it load fast with minimal
     * images and inline styles to reduce HTTP requests.
     * :::
     *
     * If empty (default), each gateway will decide how to handle after-payment redirects.
     *
     * @group Payments
     */
    public string $gatewayPostRedirectTemplate = '';

    /**
     * @var array Payment gateway settings indexed by each gateway’s handle.
     *
     * Check each gateway’s documentation for settings that may be stored.
     *
     * @group Payments
     */
    public array $gatewaySettings = [];

    /**
     * @var string|null Default URL to be loaded after using the [load cart controller action](loading-a-cart.md).
     *
     * If `null` (default), Craft’s default [`siteUrl`](config3:siteUrl) will be used.
     *
     * @group Cart
     * @since 3.1
     */
    public ?string $loadCartRedirectUrl = null;

    /**
     * @var string How Commerce should handle minimum total price for an order.
     *
     * Options:
     *
     * - `'default'` [rounds](commerce3:\craft\commerce\helpers\Currency::round()) the sum of the item subtotal and adjustments.
     * - `'zero'` returns `0` if the result from `'default'` would’ve been negative; minimum order total is `0`.
     * - `'shipping'` returns the total shipping cost if the `'default'` result would’ve been negative; minimum order total equals shipping amount.
     *
     * @group Orders
     */
    public string $minimumTotalPriceStrategy = 'default';

    /**
     * @var string Human-friendly reference number format for orders. Result must be unique.
     *
     * See [Order Numbers](orders.md#order-numbers).
     *
     * @group Orders
     */
    public string $orderReferenceFormat = '{{number[:7]}}';

    /**
     * @var array|null ISO codes for supported payment currencies.
     *
     * See [Payment Currencies](payment-currencies.md).
     *
     * @group Payments
     */
    public ?array $paymentCurrency = null;

    /**
     * @var string The orientation of the paper to use for generated order PDF files.
     *
     * Options are `'portrait'` and `'landscape'`.
     *
     * @group Orders
     */
    public string $pdfPaperOrientation = 'portrait';

    /**
     * @var string The size of the paper to use for generated order PDFs.
     *
     * The full list of supported paper sizes can be found [in the dompdf library](https://github.com/dompdf/dompdf/blob/master/src/Adapter/CPDF.php#L45).
     *
     * @group Orders
     */
    public string $pdfPaperSize = 'letter';

    /**
     * @var bool Whether to allow non-local images in generated order PDFs.
     * @group Orders
     */
    public bool $pdfAllowRemoteImages = false;

    /**
     * @var bool Whether inactive carts should automatically be deleted from the database during garbage collection.
     *
     * ::: tip
     * You can control how long a cart should go without being updated before it gets deleted [`purgeInactiveCartsDuration`](#purgeinactivecartsduration) setting.
     * :::
     *
     * @group Cart
     */
    public bool $purgeInactiveCarts = true;

    /**
     * @var mixed Default length of time before inactive carts are purged. (Defaults to 90 days.)
     *
     * See [craft\helpers\ConfigHelper::durationInSeconds()](craft3:craft\helpers\ConfigHelper::durationInSeconds()) for a list of supported value types.
     *
     * @group Cart
     * @defaultAlt 90 days
     */
    public $purgeInactiveCartsDuration = 7776000;

    /**
     * @var bool Whether a shipping address is required before making payment on an order.
     * @group Orders
     */
    public bool $requireShippingAddressAtCheckout = false;

    /**
     * @var bool Whether a billing address is required before making payment on an order.
     * @group Orders
     */
    public bool $requireBillingAddressAtCheckout = false;

    /**
     * @var bool Whether shipping method selection is required before making payment on an order.
     * @group Orders
     */
    public bool $requireShippingMethodSelectionAtCheckout = false;

    /**
     * @var bool Whether the [customer info tab](customers.md#user-customer-info-tab) should be shown when viewing users in the control panel.
     * @group System
     * @since 3.0
     */
    public bool $showCustomerInfoTab = true;

    /**
     * @var string URL for a user to resolve billing issues with their subscription.
     *
     * ::: tip
     * The example templates include [a template for this page](https://github.com/craftcms/commerce/tree/master/example-templates/shop/plans/update-billing-details.twig).
     * :::
     *
     * @group Orders
     */
    public string $updateBillingDetailsUrl = '';

    /**
     * @var bool Whether the search index for a cart should be updated when saving the cart via `commerce/cart/*` controller actions.
     *
     * May be set to `false` to reduce performance impact on high-traffic sites.
     *
     * ::: warning
     * Setting this to `false` will result in fewer index update queue jobs, but you’ll need to manually re-index orders to ensure up-to-date cart search results in the control panel.
     * :::
     *
     * @group Cart
     * @since 3.1.5
     */
    public bool $updateCartSearchIndexes = true;

    /**
     * @var bool Whether taxes should be calculated based on the billing address instead of the shipping address.
     * @group Orders
     */
    public bool $useBillingAddressForTax = false;

    /**
     * @var bool Whether to enable validation requiring the `businessTaxId` to be a valid VAT ID.
     *
     * When set to `false`, no validation is applied to `businessTaxId`.
     *
     * When set to `true`, `businessTaxId` must contain a valid VAT ID.
     *
     * ::: tip
     * This setting strictly toggles input validation and has no impact on tax configuration or behavior elsewhere in the system.
     * :::
     *
     * @group Orders
     */
    public bool $validateBusinessTaxIdAsVatId = false;

    /**
     * @var string Units to be used for weight measurements.
     *
     * Options:
     *
     * - `'g'`
     * - `'kg'`
     * - `'lb'`
     *
     * @group Units
     */
    public string $weightUnits = 'g';

    /**
     * @var bool Whether to validate custom fields when a cart is updated.
     *
     * Set to `true` to allow custom content fields to return validation errors when a cart is updated.
     *
     * @group Cart
     * @since 3.0.12
     */
    public bool $validateCartCustomFieldsOnSubmission = false;

    /**
     * @todo remove in 4.0 #COM-60
     */
    private ?string $_orderPdfFilenameFormat = null;

    /**
     * @todo remove in 4.0 #COM-60
     */
    public ?string $_orderPdfPath = null;

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();

        $commerce = Craft::$app->getPlugins()->getStoredPluginInfo('commerce');

        // We only want to mass set or retrieve these prior to 3.2 #COM-60
        if ($commerce && version_compare($commerce['version'], '3.2.0', '<')) {
            $names[] = 'orderPdfFilenameFormat'; // @todo remove in 4.0
            $names[] = 'orderPdfPath'; // @todo remove in 4.0
        }

        return $names;
    }

    /**
     * Returns a key-value array of weight unit options and labels.
     *
     * @return array
     */
    public function getWeightUnitsOptions(): array
    {
        return [
            'g' => Craft::t('commerce', 'Grams (g)'),
            'kg' => Craft::t('commerce', 'Kilograms (kg)'),
            'lb' => Craft::t('commerce', 'Pounds (lb)')
        ];
    }

    /**
     * Returns a key-value array of dimension unit options and labels.
     *
     * @return array
     */
    public function getDimensionUnits(): array
    {
        return [
            'mm' => Craft::t('commerce', 'Millimeters (mm)'),
            'cm' => Craft::t('commerce', 'Centimeters (cm)'),
            'm' => Craft::t('commerce', 'Meters (m)'),
            'ft' => Craft::t('commerce', 'Feet (ft)'),
            'in' => Craft::t('commerce', 'Inches (in)'),
        ];
    }

    /**
     * Returns a key-value array of `minimumTotalPriceStrategy` options and labels.
     *
     * @return array
     */
    public function getMinimumTotalPriceStrategyOptions(): array
    {
        return [
            self::MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT => Craft::t('commerce', 'Default - Allow the price to be negative if discounts are greater than the order value.'),
            self::MINIMUM_TOTAL_PRICE_STRATEGY_ZERO => Craft::t('commerce', 'Zero - Minimum price is zero if discounts are greater than the order value.'),
            self::MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING => Craft::t('commerce', 'Shipping - Minimum cost is the shipping cost, if the order price is less than the shipping cost.')
        ];
    }

    /**
     * Returns a key-value array of `freeOrderPaymentStrategy` options and labels.
     *
     * @return array
     */
    public function getFreeOrderPaymentStrategyOptions(): array
    {
        return [
            self::FREE_ORDER_PAYMENT_STRATEGY_COMPLETE => Craft::t('commerce', 'Free orders complete immediately'),
            self::FREE_ORDER_PAYMENT_STRATEGY_PROCESS => Craft::t('commerce', 'Free orders are processed by the payment gateway'),
        ];
    }

    /**
     * Returns the ISO payment currency for a given site, or the default site if no handle is provided.
     *
     * @param string|null $siteHandle
     * @return string|null
     * @throws InvalidConfigException if the currency in the config file is not set up
     * @throws CurrencyException
     */
    public function getPaymentCurrency(string $siteHandle = null): ?string
    {
        $paymentCurrency = ConfigHelper::localizedValue($this->paymentCurrency, $siteHandle);
        $allPaymentCurrencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();
        $paymentCurrencies = ArrayHelper::getColumn($allPaymentCurrencies, 'iso');

        if ($paymentCurrency && !in_array($paymentCurrency, $paymentCurrencies, false)) {
            throw new InvalidConfigException("Invalid payment currency: {$paymentCurrency}");
        }

        return $paymentCurrency;
    }

    /**
     * Returns a key-value array of default control panel view options and labels.
     *
     * @return array
     * @since 2.2
     */
    public function getDefaultViewOptions(): array
    {
        return [
            self::VIEW_URI_ORDERS => Craft::t('commerce', 'Orders'),
            self::VIEW_URI_PRODUCTS => Craft::t('commerce', 'Products'),
            self::VIEW_URI_CUSTOMERS => Craft::t('commerce', 'Customers'),
            self::VIEW_URI_PROMOTIONS => Craft::t('commerce', 'Promotions'),
            self::VIEW_URI_SHIPPING => Craft::t('commerce', 'Shipping'),
            self::VIEW_URI_TAX => Craft::t('commerce', 'Tax'),
            self::VIEW_URI_SUBSCRIPTIONS => Craft::t('commerce', 'Subscriptions'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules [] = [['weightUnits', 'dimensionUnits', 'orderReferenceFormat'], 'required'];

        return $rules;
    }

    /**
     * @deprecated in 3.2.0. Use the [Default PDF](pdfs.md) model instead.
     * // TODO only remove when migrations have a breakpoint #COM-60
     */
    public function setOrderPdfFilenameFormat($value): void
    {
        $this->_orderPdfFilenameFormat = $value;
    }

    /**
     * @deprecated in 3.2.0. Use the [Default PDF](pdfs.md) model instead.
     * // TODO only remove when migrations have a breakpoint #COM-60
     */
    public function setOrderPdfPath($value): void
    {
        $this->_orderPdfPath = $value;
    }

    /**
     * @param bool $fromSettings For use in migration only
     * @deprecated in 3.2.0. Use the [Default PDF](pdfs.md) model instead.
     * // TODO only remove when migrations have a breakpoint #COM-60
     */
    public function getOrderPdfFilenameFormat($fromSettings = false): string
    {
        if ($fromSettings) {
            return $this->_orderPdfFilenameFormat ?? '';
        }

        Craft::$app->getDeprecator()->log('Settings::getOrderPdfFilenameFormat()', '`Settings::getOrderPdfFilenameFormat()` has been deprecated. Use the configured default PDF model instead.');

        $pdfs = Plugin::getInstance()->getPdfs()->getAllEnabledPdfs();
        /** @var Pdf $pdf */
        $pdf = ArrayHelper::firstValue($pdfs);

        return $pdf->fileNameFormat ?? '';
    }

    /**
     * @param bool $fromSettings For use in migration only
     * @deprecated in 3.2.0. Use the [Default PDF](pdfs.md) model instead.
     * // TODO only remove when migrations have a breakpoint #COM-60
     */
    public function getOrderPdfPath($fromSettings = false): string
    {
        if ($fromSettings) {
            return $this->_orderPdfPath ?? '';
        }

        Craft::$app->getDeprecator()->log('Settings::getOrderPdfFilenameFormat()', '`Settings::getOrderPdfFilenameFormat()` has been deprecated. Use the configured default PDF model instead.');

        $pdfs = Plugin::getInstance()->getPdfs()->getAllEnabledPdfs();
        /** @var Pdf $pdf */
        $pdf = ArrayHelper::firstValue($pdfs);

        return $pdf->templatePath ?? '';
    }
}
