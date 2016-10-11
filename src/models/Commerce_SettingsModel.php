<?php
namespace Craft;

use Omnipay\Common\Currency;

/**
 * Settings model.
 *
 * @property string $weightUnits
 * @property string $dimensionUnits
 * @property string $emailSenderAddress
 * @property string $emailSenderName
 * @property string $orderPdfPath
 * @property string $orderPdfFilenameFormat
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_SettingsModel extends BaseModel
{
    /**
     * @var
     */
    public $emailSenderAddressPlaceholder;
    /**
     * @var
     */
    public $emailSenderNamePlaceholder;

    /**currency moved from plugin settings to currency table in DB.
     *
     * @return string
     */
    public function getDefaultCurrency()
    {
        craft()->deprecator->log('Commerce_SettingsModel::defaultCurrency:removed', 'You should no longer use `craft.commerce.settings.defaultCurrency`  to get the store currency. Use `craft.commerce.primaryPaymentCurrency`.');

        return craft()->commerce_paymentCurrencies->getPrimaryPaymentCurrencyIso();
    }

    /**
     * @return array
     */
    public function defineAttributes()
    {
        return [
            'weightUnits' => [
                AttributeType::String,
                'default' => 'g'
            ],
            'dimensionUnits' => [
                AttributeType::String,
                'default' => 'mm'
            ],
            'emailSenderAddress' => [AttributeType::String],
            'emailSenderName' => [AttributeType::String],
            'orderPdfPath' => [AttributeType::String],
            'orderPdfFilenameFormat' => [AttributeType::String]
        ];
    }

    /**
     * @return array
     */
    public function getWeightUnitsOptions()
    {
        return [
            'g' => Craft::t('Grams (g)'),
            'kg' => Craft::t('Kilograms (kg)'),
            'lb' => Craft::t('Pounds (lb)')
        ];
    }

    /**
     * @return array
     */
    public function getDimensionUnits()
    {
        return [
            'mm' => Craft::t('Millimeters (mm)'),
            'cm' => Craft::t('Centimeters (cm)'),
            'm' => Craft::t('Meters (m)'),
            'ft' => Craft::t('Feet (ft)'),
            'in' => Craft::t('Inches (in)'),
        ];
    }
}
