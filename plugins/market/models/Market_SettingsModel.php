<?php

namespace Craft;

/**
 * Class Market_SettingsModel
 *
 * @package Craft
 *
 * @property string defaultCurrency
 * @property string paymentMethod
 * @property int cartExpiryTimeout
 * @property string weightUnits
 * @property string dimensionUnits
 * @property string emailSenderAddress
 * @property string emailSenderName
 * @property string purgeIncompleteCartDuration
 * @property string orderPdfPath
 *
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
                'required' => true,
                'default'  => 'authorize'
            ],
            'cartExpiryTimeout'        => [
                AttributeType::Number,
                'default'  => 10080,
                'required' => true
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
            'purgeIncompleteCartDuration' => [AttributeType::String,'default'=>'P3M'],
            'orderPdfPath'             => [AttributeType::String]
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