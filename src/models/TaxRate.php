<?php

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;

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
     * @var string taxable
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
     * @var TaxCategory
     */
    private $_taxCategory;

    /**
     * @var TaxZone
     */
    private $_taxZone;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['taxZoneId', 'taxCategoryId', 'name'], 'required']
        ];
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/taxrates/'.$this->id);
    }

    /**
     * @return string
     */
    public function getRateAsPercent()
    {
        $percentSign = Craft::$app->getLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        return $this->rate * 100 .''.$percentSign;
    }

    /**
     * @return \craft\commerce\models\TaxZone|null
     */
    public function getTaxZone()
    {
        if (!$this->_taxZone) {
            $this->_taxZone = Plugin::getInstance()->getTaxZones()->getTaxZoneById($this->taxZoneId);
        }

        return $this->_taxZone;
    }

    /**
     * @return \craft\commerce\models\TaxCategory|null
     */
    public function getTaxCategory()
    {
        if (!$this->_taxCategory) {
            $this->_taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
        }

        return $this->_taxCategory;
    }
}
