<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Tax Category model.
 *
 * @property string          $cpEditUrl
 * @property array|TaxRate[] $taxRates
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class TaxCategory extends Model
{
    /**
     * @var int ID;
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Handle
     */
    public $handle;

    /**
     * @var string Description
     */
    public $description;

    /**
     * @var bool Default
     */
    public $default;

    // Public Methods
    // =========================================================================

    /**
     * Returns the name of this tax category.
     *
     * @return string
     */
    public function __toString()
    {
        return (string)$this->name;
    }

    /**
     * @return TaxRate[]
     */
    public function getTaxRates(): array
    {
        $allTaxRates = Plugin::getInstance()->getTaxRates()->getAllTaxRates();
        $taxRates = [];

        /** @var TaxRate $rate */
        foreach ($allTaxRates as $rate) {
            if ($this->id === $rate->taxCategoryId) {
                $taxRates[] = $rate;
            }
        }

        return $taxRates;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/taxcategories/'.$this->id);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['handle'], 'required']
        ];
    }
}
