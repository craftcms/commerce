<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;

/**
 * Settings model.
 *
 * @property array $weightUnitsOptions
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Settings extends Model
{
    // Properties
    // =========================================================================

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
    public $orderPdfPath;

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
    public $orderPdfFilenameFormat;

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
    public $cartCookieDuration = 'P3M';

    /**
     * @var array
     */
    public $paymentMethodSettings = [];

    /**
     * @var bool
     */
    public $purgeInactiveCarts = true;

    /**
     * @var string
     */
    public $purgeInactiveCartsDuration = 'P3M';

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
    public $requireShippingAddressAtCheckout = false;

    /**
     * @var bool
     */
    public $requireBillingAddressAtCheckout = false;

    /**
     * @var bool
     */
    public $autoSetNewCartAddresses = true;

    /**
     * @var bool
     */
    public $pdfAllowRemoteImages = false;

    /**
     * @var array
     */
    public $gatewaySettings = [];

    // Public Methods
    // =========================================================================

    /**
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
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['weightUnits', 'dimensionUnits', 'orderPdfPath', 'orderPdfFilenameFormat'], 'required']
        ];
    }
}
