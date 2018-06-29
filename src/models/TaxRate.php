<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;

/**
 * Tax rate model.
 *
 * @property string $cpEditUrl
 * @property string $rateAsPercent
 * @property TaxAddressZone|null $taxZone
 * @property TaxCategory|null $taxCategory
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxRate extends Model
{
    // Properties
    // =========================================================================

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
     * @var TaxAddressZone
     */
    private $_taxZone;

    // Public Methods
    // =========================================================================

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
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/taxrates/' . $this->id);
    }

    /**
     * @return string
     */
    public function getRateAsPercent(): string
    {
        $percentSign = Craft::$app->getLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        return $this->rate * 100 . '' . $percentSign;
    }

    /**
     * @return TaxAddressZone|null
     */
    public function getTaxZone()
    {
        if (null === $this->_taxZone) {
            $this->_taxZone = Plugin::getInstance()->getTaxZones()->getTaxZoneById($this->taxZoneId);
        }

        return $this->_taxZone;
    }

    /**
     * @return TaxCategory|null
     */
    public function getTaxCategory()
    {
        if (null === $this->_taxCategory) {
            $this->_taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
        }

        return $this->_taxCategory;
    }
}
