<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use DateTime;

/**
 * Tax Category model.
 *
 * @property string $cpEditUrl
 * @property \craft\commerce\models\ProductType[] $productTypes
 * @property-read int[] $productTypeIds
 * @property array|TaxRate[] $taxRates
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxCategory extends Model
{
    /**
     * @var int|null ID;
     */
    public ?int $id = null;

    /**
     * @var string Name
     */
    public string $name;

    /**
     * @var string Handle
     */
    public string $handle;

    /**
     * @var string Description
     */
    public string $description;

    /**
     * @var bool Default
     */
    public bool $default;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated;

    /**
     * @var array Product Types
     */
    private array $_productTypes;


    /**
     * Returns the name of this tax category.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }

    /**
     * @return TaxRate[]
     */
    public function getTaxRates(): array
    {
        $taxRates = [];

        foreach (Plugin::getInstance()->getTaxRates()->getAllTaxRates() as $rate) {
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
        return UrlHelper::cpUrl('commerce/tax/taxcategories/' . $this->id);
    }

    /**
     * @param ProductType[] $productTypes
     */
    public function setProductTypes($productTypes): void
    {
        $this->_productTypes = $productTypes;
    }

    /**
     * @return ProductType[]
     */
    public function getProductTypes(): array
    {
        if (!isset($this->_productTypes)) {
            $this->_productTypes = Plugin::getInstance()->getProductTypes()->getProductTypesByTaxCategoryId($this->id);
        }

        return $this->_productTypes;
    }

    /**
     * Helper method to just get the product type IDs
     *
     * @return int[]
     */
    public function getProductTypeIds(): array
    {
        return ArrayHelper::getColumn($this->getProductTypes(), 'id');
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['handle'], 'required'];

        return $rules;
    }
}
