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
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use DateTime;
use yii\base\InvalidConfigException;

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
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Code
     * @since 2.2
     */
    public ?string $code = null;

    /**
     * @var float Rate
     */
    public float $rate = .00;

    /**
     * @var bool Include
     */
    public bool $include = false;

    /**
     * @var bool Remove the included tax rate
     * @since 3.4
     */
    public bool $removeIncluded = false;

    /**
     * @var bool Remove the included vat tax rate
     * @since 3.4
     */
    public bool $removeVatIncluded = false;

    /**
     * @var bool Is VAT
     */
    public bool $isVat = false;

    /**
     * @var string taxable
     */
    public string $taxable = 'price';

    /**
     * @var int|null Tax category ID
     */
    public ?int $taxCategoryId = null;

    /**
     * @var bool Is this the tax rate for the lite edition
     */
    public bool $isLite = false;

    /**
     * @var int|null Tax zone ID
     */
    public ?int $taxZoneId = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var TaxCategory|null
     */
    private ?TaxCategory $_taxCategory = null;

    /**
     * @var TaxAddressZone|null
     */
    private ?TaxAddressZone $_taxZone = null;


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
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'taxCategory';
        $fields[] = 'taxZone';
        $fields[] = 'rateAsPercent';
        $fields[] = 'isEverywhere';

        return $fields;
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
        $percentSign = Craft::$app->getLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);

        return $this->rate * 100 . '' . $percentSign;
    }

    /**
     * @return TaxAddressZone|null
     * @throws InvalidConfigException
     */
    public function getTaxZone(): ?TaxAddressZone
    {
        if ($this->_taxZone === null && $this->taxZoneId) {
            $this->_taxZone = Plugin::getInstance()->getTaxZones()->getTaxZoneById($this->taxZoneId);
        }

        return $this->_taxZone;
    }

    /**
     * @return TaxCategory|null
     * @throws InvalidConfigException
     */
    public function getTaxCategory(): ?TaxCategory
    {
        if (!isset($this->_taxCategory) && $this->taxCategoryId) {
            $this->_taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId);
        }

        return $this->_taxCategory;
    }

    /**
     * @return bool Does this tax rate apply everywhere
     * @throws InvalidConfigException
     */
    public function getIsEverywhere(): bool
    {
        return !$this->getTaxZone();
    }
}
