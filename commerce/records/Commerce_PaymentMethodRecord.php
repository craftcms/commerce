<?php
namespace Craft;

/**
 * Payment method record.
 *
 * @property int    $id
 * @property string $class
 * @property string $name
 * @property string $paymentType
 * @property array  $settings
 * @property string $type
 * @property bool   $frontendEnabled
 * @property bool   $isArchived
 * @property bool   $dateArchived
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_PaymentMethodRecord extends BaseRecord
{
    /**
     * The name of the table not including the craft db prefix e.g craft_
     *
     * @return string
     */
    public function getTableName()
    {
        return 'commerce_paymentmethods';
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['name'], 'unique' => true],
        ];
    }

    /**
     * @return array
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            ['class', 'in', 'range' => $this->gatewayNames()],
        ]);
    }

    /**
     * @return array
     */
    private function gatewayNames()
    {
        $gateways = craft()->commerce_gateways->getAllGateways();

        return array_keys($gateways);
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'class'           => [AttributeType::String, 'required' => true],
            'name'            => [AttributeType::String, 'required' => true],
            'settings'        => [AttributeType::Mixed],
            'paymentType'     => [
                AttributeType::Enum,
                'values'   => ['authorize', 'purchase'],
                'required' => true,
                'default'  => 'purchase'
            ],
            'frontendEnabled' => [
                AttributeType::Bool,
                'required' => true,
                'default'  => 0
            ],
            'isArchived'      => [AttributeType::Bool, 'default' => false],
            'dateArchived'    => [AttributeType::DateTime],
            'sortOrder'       => [AttributeType::Number],
        ];
    }
}
