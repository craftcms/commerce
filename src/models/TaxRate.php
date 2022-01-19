<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\helpers\Localization;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\helpers\UrlHelper;
use DateTime;

/**
 * Tax rate model.
 *
 * @property string $cpEditUrl
 * @property string $rateAsPercent
 * @property-read bool $isEverywhere
 * @property TaxAddressZone|null $taxZone
 * @property TaxCategory|null $taxCategory
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
     * @var string Code
     * @since 2.2
     */
    public $code;

    /**
     * @var float Rate
     */
    public $rate = .00;

    /**
     * @var bool Include
     */
    public $include;

    /**
     * @var bool Remove the included tax rate
     * @since 3.4
     */
    public $removeIncluded;

    /**
     * @var bool Remove the included vat tax rate
     * @since 3.4
     */
    public $removeVatIncluded;

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
     * @var int Is this the tax rate for the lite edition
     */
    public $isLite;

    /**
     * @var int Tax zone ID
     */
    public $taxZoneId;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public $dateCreated;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public $dateUpdated;

    /**
     * @var TaxCategory
     */
    private $_taxCategory;

    /**
     * @var TaxAddressZone
     */
    private $_taxZone;


    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name'], 'required'],
            [
                ['taxCategoryId'],
                'required',
                'when' => function($model): bool {
                    return !in_array($model->taxable, TaxRateRecord::ORDER_TAXABALES, true);
                },
            ],
        ];
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/tax/taxrates/' . $this->id);
    }

    /**
     * @return string
     */
    public function getRateAsPercent(): string
    {
        return Localization::formatAsPercentage($this->rate);
    }

    /**
     * @return TaxAddressZone|null
     */
    public function getTaxZone()
    {
        if (null === $this->_taxZone && $this->taxZoneId) {
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

    /**
     * @return bool Does this tax rate apply everywhere
     */
    public function getIsEverywhere(): bool
    {
        return !$this->getTaxZone();
    }
}
