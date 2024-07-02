<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\errors\CurrencyException;
use craft\commerce\Plugin;
use craft\errors\SiteNotFoundException;
use craft\helpers\ConfigHelper;
use craft\models\Site;
use yii\base\InvalidConfigException;

/**
 * Settings model.
 *
 * @property-read array $weightUnitsOptions
 * @property-read array $dimensionsUnits
 * @property-read array $defaultViewOptions
 * @property-read string $paymentCurrency
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Settings extends Model
{
    public const VIEW_URI_ORDERS = 'commerce/orders';
    public const VIEW_URI_PRODUCTS = 'commerce/products';
    /**
     * @since 5.0.0.
     */
    public const VIEW_URI_INVENTORY = 'commerce/inventory';

    /**
     * @since 5.0.0.
     */
    public const VIEW_URI_STORE_MANAGEMENT = 'commerce/store-management';

    /**
     * @deprecated in 5.0.0.
     */
    public const VIEW_URI_CUSTOMERS = 'commerce/customers';

    /**
     * @deprecated in 5.0.0.
     */
    public const VIEW_URI_PROMOTIONS = 'commerce/promotions';

    /**
     * @deprecated in 5.0.0.
     */
    public const VIEW_URI_SHIPPING = 'commerce/shipping/shippingmethods';

    /**
     * @deprecated in 5.0.0.
     */
    public const VIEW_URI_TAX = 'commerce/tax/taxrates';
    public const VIEW_URI_SUBSCRIPTIONS = 'commerce/subscriptions';

    /**
     * @var mixed How long a cart should go without being updated before it’s considered inactive.
     *
     * See [craft\helpers\ConfigHelper::durationInSeconds()](craft5:craft\helpers\ConfigHelper::durationInSeconds()) for a list of supported value types.
     *
     * @group Cart
     * @since 2.2
     * @defaultAlt 1 hour
     */
    public mixed $activeCartDuration = 3600;

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
     * @var string The path to the template that should be used to perform POST requests to offsite payment gateways.
     *
     * The template must contain a form that posts to the URL supplied by the `actionUrl` variable and outputs all hidden inputs with
     * the `inputs` variable.
     *
     * ```twig
     * <!DOCTYPE html>
     * <html>
     * <head>
     *   <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
     *   <title>Redirecting...</title>
     * </head>
     * <body onload="document.forms[0].submit();">
     * <form action="{{ actionUrl }}" method="post">
     *   <p>Redirecting to payment page...</p>
     *   <p>
     *     {{ inputs|raw }}
     *     <button type="submit">Continue</button>
     *   </p>
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
     * @var string|null Default URL to be loaded after using the [load cart controller action](https://craftcms.com/docs/commerce/5.x/system/orders-carts.html#loading-a-cart).
     *
     * If `null` (default), Craft’s default [`siteUrl`](config5:siteUrl) will be used.
     *
     * @group Cart
     * @since 3.1
     */
    public ?string $loadCartRedirectUrl = null;

    /**
     * @var array|null ISO codes for supported payment currencies.
     *
     * See [Payment Currencies](https://craftcms.com/docs/commerce/5.x/system/payment-currencies.html).
     *
     * @group Payments
     */
    public ?array $paymentCurrency = null;

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
     * See [craft\helpers\ConfigHelper::durationInSeconds()](craft5:craft\helpers\ConfigHelper::durationInSeconds()) for a list of supported value types.
     *
     * @group Cart
     * @defaultAlt 90 days
     */
    public mixed $purgeInactiveCartsDuration = 7776000;

    /**
     * @var string URL for a user to resolve billing issues with their subscription.
     *
     * ::: tip
     * The example templates include [a template for this page](https://github.com/craftcms/commerce/tree/5.x/example-templates/dist/shop/plans/update-billing-details.twig).
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
     * @inheritDoc
     */
    public function setAttributes($values, $safeOnly = true): void
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
        parent::setAttributes($values, $safeOnly);
    }

    /**
     * Returns a key-value array of weight unit options and labels.
     */
    public function getWeightUnitsOptions(): array
    {
        return [
            'g' => Craft::t('commerce', 'Grams (g)'),
            'kg' => Craft::t('commerce', 'Kilograms (kg)'),
            'lb' => Craft::t('commerce', 'Pounds (lb)'),
        ];
    }

    /**
     * Returns a key-value array of dimension unit options and labels.
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
     * Returns the ISO payment currency for a given site, or the default site if no handle is provided.
     *
     * @param string|null $siteHandle
     * @return string|null
     * @throws CurrencyException
     * @throws InvalidConfigException if the currency in the config file is not set up
     * @throws SiteNotFoundException
     */
    public function getPaymentCurrency(string $siteHandle = null): ?string
    {
        /** @var Site|StoreBehavior|null $site */
        $site = $siteHandle ? Craft::$app->getSites()->getSiteByHandle($siteHandle) : Craft::$app->getSites()->getPrimarySite();
        if (!$site) {
            throw new InvalidConfigException("Invalid site: $siteHandle");
        }

        $paymentCurrency = ConfigHelper::localizedValue($this->paymentCurrency, $siteHandle);
        $allPaymentCurrencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies($site->getStore()->id);

        if ($paymentCurrency && !$allPaymentCurrencies->contains('iso', '==', $paymentCurrency)) {
            throw new InvalidConfigException("Invalid payment currency: $paymentCurrency");
        }

        return $paymentCurrency;
    }

    /**
     * Returns a key-value array of default control panel view options and labels.
     *
     * @since 2.2
     */
    public function getDefaultViewOptions(): array
    {
        return [
            self::VIEW_URI_ORDERS => Craft::t('commerce', 'Orders'),
            self::VIEW_URI_PRODUCTS => Craft::t('commerce', 'Products'),
            self::VIEW_URI_INVENTORY => Craft::t('commerce', 'Inventory'),
            self::VIEW_URI_STORE_MANAGEMENT => Craft::t('commerce', 'Store Management'),
            self::VIEW_URI_SUBSCRIPTIONS => Craft::t('commerce', 'Subscriptions'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['weightUnits', 'dimensionUnits'], 'required'],
        ];
    }
}
