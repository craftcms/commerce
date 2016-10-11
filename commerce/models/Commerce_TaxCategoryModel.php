<?php
namespace Craft;

/**
 * Tax Category model.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property bool $default
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_TaxCategoryModel extends BaseModel implements \JsonSerializable
{
    public function jsonSerialize()
    {
        return $this->getAttributes();
    }

    /**
     * Returns the name of this tax category.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getAttribute('name');
    }

    /**
     * @return Commerce_TaxRateModel[]
     */
    public function getTaxRates()
    {
        $allTaxRates = craft()->commerce_taxRates->getAllTaxRates();
        $taxRates = [];
        /** @var Commerce_TaxRateModel $rate */
        foreach ($allTaxRates as $rate)
        {
            if ($this->id == $rate->taxCategoryId)
            {
                $taxRates[] = $rate;
            }
        }

        return $taxRates;
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/taxcategories/' . $this->id);
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'name' => AttributeType::String,
            'handle' => AttributeType::String,
            'description' => AttributeType::String,
            'default' => AttributeType::Bool,
        ];
    }

}