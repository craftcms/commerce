<?php

namespace Craft;

/**
 * Class Market_SettingsModel
 *
 * @package Craft
 *
 * @property string defaultCurrency
 * @property string paymentMethod
 * @property string weightUnits
 * @property string dimensionUnits
 * @property string emailSenderAddress
 * @property string emailSenderName
 */
class Market_SettingsModel extends BaseModel
{
    public function defineAttributes()
    {
        return [
            'defaultCurrency'          => [
                AttributeType::String,
                'default'  => 'USD',
                'required' => true
            ],
            'paymentMethod'            => [
                AttributeType::Enum,
                'values'   => ['authorize', 'purchase'],
                'required' => true
            ],
            'cartExpiryTimeout'        => [
                AttributeType::Number,
                'default'  => 1440,
                'required' => true
            ],
            'currencySymbol'           => [
                AttributeType::String,
                'default' => '$'
            ],
            'currencySuffix'           => [AttributeType::String],
            'currencyDecimalPlaces'    => [
                AttributeType::Number,
                'default' => 2
            ],
            'currencyDecimalSymbol'    => [
                AttributeType::String,
                'default' => '.'
            ],
            'currencyDecimalSeparator' => [
                AttributeType::String,
                'default' => ','
            ],
            'weightUnits'              => [
                AttributeType::String,
                'default' => 'g'
            ],
            'dimensionUnits'           => [
                AttributeType::String,
                'default' => 'mm'
            ],
            'emailSenderAddress'       => [AttributeType::String],
            'emailSenderName'          => [AttributeType::String],
        ];
    }

    public function getPaymentMethodOptions()
    {
        return [
            'authorize' => 'Authorize Only',
            'purchase'  => 'Purchase (Authorize and Capture)',
        ];
    }

    public function getWeightUnitsOptions()
    {
        return [
            'g'  => 'Grams (g)',
            'kg' => 'Kilograms (kg)',
            'lb' => 'Pounds (lb)',
        ];
    }

    public function getDimensionUnits()
    {
        return [
            'mm' => 'Millimeters (mm)',
            'cm' => 'Centimeters (cm)',
            'm'  => 'Metres (m)',
            'ft' => 'Feet (ft)',
            'in' => 'Inches (in)',
        ];
    }
}