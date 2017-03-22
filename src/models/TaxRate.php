<?php
namespace craft\commerce\models;

use craft\commerce\base\Model;

/**
 * Tax rate model.
 *
 * @property \craft\commerce\models\TaxZone|null     $taxZone
 * @property \craft\commerce\models\TaxCategory|null $taxCategory
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class TaxRate extends Model
{

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var float Rate
     */
    public $rate = .05;

    /**
     * @var bool Include
     */
    public $include;

    /**
     * @var bool Is VAT
     */
    public $isVat = false;

    /**
     * @var bool taxable
     */
    public $taxable = 'price';

    /**
     * @var int Tax category ID
     */
    public $taxCategoryId;

    /**
     * @var int Tax zone ID
     */
    public $taxZoneId;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['taxZoneId', 'taxCategoryId'], 'required']
        ];
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/taxrates/'.$this->id);
    }

    /**
     * @return string
     */
    public function getRateAsPercent()
    {
        $localeData = craft()->i18n->getLocaleData();
        $percentSign = $localeData->getNumberSymbol('percentSign');

        return $this->rate * 100 ."".$percentSign;
    }

    /**
     * @return \craft\commerce\models\TaxZone|null
     */
    public function getTaxZone()
    {
        return Plugin::getInstance()->getTaxZones()->getTaxZoneById($this->taxZoneId);
    }

    /**
     * @return \craft\commerce\models\TaxCategory|null
     */
    public function getTaxCategory()
    {
        return Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
    }
}
