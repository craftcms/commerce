<?php
namespace Craft;

/**
 * Currency record.
 *
 * @property int $id
 * @property string $name
 * @property string $iso
 * @property bool $default
 * @property float $rate
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_PaymentCurrencyRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_paymentcurrencies';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['iso'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'name' => [AttributeType::String, 'required' => true],
            'iso' => [
                AttributeType::String,
                'required' => true,
                'maxLength' => 3,
                'minLength' => 3
            ],
	        'default' => AttributeType::Bool,
            'rate' => [
                AttributeType::Number,
                'decimals' => 4,
                'default' => 0,
                'required' => true
            ],
        ];
    }
}