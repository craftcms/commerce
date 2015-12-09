<?php
namespace Craft;

use Commerce\Traits\Commerce_ModelRelationsTrait;

/**
 * Tax rate model.
 *
 * @property int $id
 * @property string $name
 * @property float $rate
 * @property bool $include
 * @property string $taxable
 * @property bool $showInLabel
 * @property int $taxZoneId
 * @property int $taxCategoryId
 *
 * @property Commerce_TaxZoneModel $taxZone
 * @property Commerce_TaxCategoryModel $taxCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_TaxRateModel extends BaseModel
{
    use Commerce_ModelRelationsTrait;

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/taxrates/' . $this->id);
    }

    public function getRateAsPercent()
    {
        $localeData = craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');

        return $this->rate * 100 . "" . $percentSign;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'name' => AttributeType::String,
            'rate' => [AttributeType::Number, 'default' => .05, 'decimals' => 5],
            'include' => AttributeType::Bool,
            'taxable' => [AttributeType::String, 'default' => Commerce_TaxRateRecord::TAXABLE_PRICE],
            'showInLabel' => AttributeType::Bool,
            'taxCategoryId' => [AttributeType::Number, 'required' => true, 'label' => Craft::t('Tax Category')],
            'taxZoneId' => [AttributeType::Number, 'required' => true, 'label' => Craft::t('Tax Zone')]
        ];
    }
}
