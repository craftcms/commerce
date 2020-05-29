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
 * @property array $weightUnitsOptions
 * @property-read string $paymentCurrency
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Settings extends Model
{
    const MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT = 'default';
    const MINIMUM_TOTAL_PRICE_STRATEGY_ZERO = 'zero';
    const MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING = 'shipping';

    const VIEW_URI_ORDERS = 'commerce/orders';
    const VIEW_URI_PRODUCTS = 'commerce/products';
    const VIEW_URI_CUSTOMERS = 'commerce/customers';
    const VIEW_URI_PROMOTIONS = 'commerce/promotions';
    const VIEW_URI_SHIPPING = 'commerce/shipping/shippingmethods';
    const VIEW_URI_TAX = 'commerce/tax/taxrates';
    const VIEW_URI_SUBSCRIPTIONS = 'commerce/subscriptions';


    /**
     * @var string Weight Units
     */
    public $weightUnits = 'g';

    /**
     * @var string Dimension Units
     */
    public $dimensionUnits = 'mm';

    /**
     * @var string Sender's email address
     */
    public $emailSenderAddress;

    /**
     * @var string Sender's name
     */
    public $emailSenderName;

    /**
     * @var string Order PDF Path
     */
    public $orderPdfPath = 'shop/receipt';

    /**
     * @var string Order PDF Size
     */
    public $pdfPaperSize = 'letter';

    /**
     * @var string Order PDF Orientation
     */
    public $pdfPaperOrientation = 'portrait';

    /**
     * @var string Order PDF file name format
     */
    public $orderPdfFilenameFormat = 'Order-{number}';

    /**
     * @var string
     */
    public $emailSenderAddressPlaceholder;

    /**
     * @var string
     */
    public $emailSenderNamePlaceholder;

    /**
     * @var string
     */
    public $minimumTotalPriceStrategy = 'default';

    /**
     * @var array
     */
    public $paymentCurrency;

    /**
     * @var array
     */
    public $paymentMethodSettings = [];

    /**
     * @var bool
     */
    public $purgeInactiveCarts = true;

    /**
     * @var int The default length of time before inactive carts are purged. Default: 90 days
     *
     * See [[ConfigHelper::durationInSeconds()]] for a list of supported value types.
     */
    public $purgeInactiveCartsDuration = 7776000;

    /**
     * @var int The default length of time a cart is considered active since its last update
     *
     * See [[ConfigHelper::durationInSeconds()]] for a list of supported value types.
     * @since 2.2
     */
    public $activeCartDuration = 3600;

    /**
     * @var string
     */
    public $gatewayPostRedirectTemplate = '';

    /**
     * @var bool
     */
    public $useBillingAddressForTax = false;

    /**
     * @var bool
     */
    public $validateBusinessTaxIdAsVatId = false;

    /**
     * @var bool
     */
    public $requireShippingAddressAtCheckout = false;

    /**
     * @var bool
     */
    public $requireBillingAddressAtCheckout = false;

    /**
     * @var bool
     */
    public $requireShippingMethodSelectionAtCheckout = false;

    /**
     * @var bool
     */
    public $autoSetNewCartAddresses = true;

    /**
     * @var bool Allow the cart to be empty on checkout
     * @since 2.2
     */
    public $allowEmptyCartOnCheckout = false;

    /**
     * @var bool
     */
    public $pdfAllowRemoteImages = false;

    /**
     * @var string The order reference format
     */
    public $orderReferenceFormat = '{{number[:7]}}';

    /**
     * @var string Default view for Commerce in the CP
     * @since 2.2
     */
    public $defaultView = 'commerce/orders';

    /**
     * @var string
     */
    public $cartVariable = 'cart';

    /**
     * @var array
     */
    public $gatewaySettings = [];

    /**
     * @var string
     */
    public $updateBillingDetailsUrl = '';

    /**
     * @var bool
     * @since 3.0
     */
    public $showCustomerInfoTab = true;

    /**
     * @var bool
     * @since 3.0.12
     */
     public $validateCartCustomFieldsOnSubmission = false;

    /**
     * @var string|null the uri to redirect to after using the load cart url
     * @since 3.1
     */
     public $loadCartRedirectUrl = null;

    /**
     * @var bool Should the search index for a cart be updated when saving the cart on the front-end.
     * @since 3.1.5
     */
    public $updateCartSearchIndexes = true;

    /**
     * @return array
     */
    public function getWeightUnitsOptions(): array
    {
        return [
            'g' => Plugin::t('Grams (g)'),
            'kg' => Plugin::t('Kilograms (kg)'),
            'lb' => Plugin::t('Pounds (lb)')
        ];
    }

    /**
     * @return array
     */
    public function getDimensionUnits(): array
    {
        return [
            'mm' => Plugin::t('Millimeters (mm)'),
            'cm' => Plugin::t('Centimeters (cm)'),
            'm' => Plugin::t('Meters (m)'),
            'ft' => Plugin::t('Feet (ft)'),
            'in' => Plugin::t('Inches (in)'),
        ];
    }

    /**
     * @return array
     */
    public function getMinimumTotalPriceStrategyOptions(): array
    {
        return [
            self::MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT => Plugin::t('Default - Allow the price to be negative if discounts are greater than the order value.'),
            self::MINIMUM_TOTAL_PRICE_STRATEGY_ZERO => Plugin::t('Zero - Minimum price is zero if discounts are greater than the order value.'),
            self::MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING => Plugin::t('Shipping - Minimum cost is the shipping cost, if the order price is less than the shipping cost.')
        ];
    }

    /**
     * @param string|null $siteHandle
     * @return string|null
     * @throws InvalidConfigException if the currency in the config file is not set up
     * @throws CurrencyException
     */
    public function getPaymentCurrency(string $siteHandle = null)
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
     * @return array
     * @since 2.2
     */
    public function getDefaultViewOptions(): array
    {
        return [
            self::VIEW_URI_ORDERS => Plugin::t('Orders'),
            self::VIEW_URI_PRODUCTS => Plugin::t('Products'),
            self::VIEW_URI_CUSTOMERS => Plugin::t('Customers'),
            self::VIEW_URI_PROMOTIONS => Plugin::t('Promotions'),
            self::VIEW_URI_SHIPPING => Plugin::t('Shipping'),
            self::VIEW_URI_TAX => Plugin::t('Tax'),
            self::VIEW_URI_SUBSCRIPTIONS => Plugin::t('Subscriptions'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules [] = [['weightUnits', 'dimensionUnits', 'orderPdfPath', 'orderPdfFilenameFormat', 'orderReferenceFormat'], 'required'];

        return $rules;
    }
}
