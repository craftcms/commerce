<?php
namespace Craft;

use Omnipay\Common\Currency;

/**
 * Settings model.
 *
 * @property string $defaultCurrency
 * @property string $weightUnits
 * @property string $dimensionUnits
 * @property string $emailSenderAddress
 * @property string $emailSenderName
 * @property string $orderPdfPath
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

    /**
     * @return array
     */
    public function defineAttributes()
    {
        return [
            'defaultCurrency' => [
                AttributeType::String,
                'default' => 'USD',
                'required' => true
            ],
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
            'orderPdfPath' => [AttributeType::String]
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
            'm' => Craft::t('Metres (m)'),
            'ft' => Craft::t('Feet (ft)'),
            'in' => Craft::t('Inches (in)'),
        ];
    }

    /**
     * @return array
     */
    public function getCurrencies()
    {

        $currencies = Currency::all();

        foreach ($currencies as $key => &$value) {
            $value = $key;
        }

        ksort($currencies, SORT_STRING);

        return $currencies;
    }
}
