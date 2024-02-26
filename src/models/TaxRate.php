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
use craft\errors\DeprecationException;
use craft\helpers\UrlHelper;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Tax Rate model.
 *
 * @property string $cpEditUrl
 * @property string $rateAsPercent
 * @property-read bool $isEverywhere
 * @property TaxAddressZone|null $taxZone
 * @property TaxCategory|null $taxCategory
 * @property bool $isLite
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
     * @var string|null Human-friendly name for the tax rate
     */
    public ?string $name = null;

    /**
     * @var string|null Optional code used for internal reference
     * @since 2.2
     */
    public ?string $code = null;

    /**
     * @var float Rate percentage applied to the taxable subject
     */
    public float $rate = .00;

    /**
     * @var bool Whether the tax amount should be included in the subject price
     */
    public bool $include = false;

    /**
     * @var bool Whether the included tax amount should be removed from disqualified subject prices
     * @since 3.4
     */
    public bool $removeIncluded = false;

    /**
     * @var bool Whether an included VAT tax amount should be removed from VAT-disqualified subject prices
     * @since 3.4
     */
    public bool $removeVatIncluded = false;

    /**
     * @var bool Whether this tax rate represents VAT
     */
    public bool $isVat = false;

    /**
     * @var string The subject to which `$rate` should be applied. Options:
     *             - `price` – line item price
     *             - `shipping` – line item shipping cost
     *             - `price_shipping` – line item price and shipping cost
     *             - `order_total_shipping` – order total shipping cost
     *             - `order_total_price` – order total taxable price (line item subtotal + total discounts +
     *               total shipping)
     */
    public string $taxable = 'price';

    /**
     * @var int|null Tax category ID
     */
    public ?int $taxCategoryId = null;

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
     * Returns the tax rate’s control panel edit page URL.
     *
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/tax/taxrates/' . $this->id);
    }

    /**
     * Returns `$rate` formatted as a percentage.
     *
     * @return string
     */
    public function getRateAsPercent(): string
    {
        return Craft::$app->getFormatter()->asPercent($this->rate);
    }

    /**
     * Returns the designated Tax Zone for the rate, or `null` if none has been designated.
     *
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
     * Returns the designated Tax Category for the rate, or `null` if none has been designated.
     *
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
     * Returns `true` is this tax rate isn’t limited by zone.
     *
     * @return bool Whether this tax rate applies to any zone
     * @throws InvalidConfigException
     */
    public function getIsEverywhere(): bool
    {
        return !$this->getTaxZone();
    }

    /**
     * @return bool
     * @throws DeprecationException
     * @since 4.5.0
     * @deprecated in 4.5.0.
     */
    public function getIsLite(): bool
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'TaxRate::getIsLite() is deprecated.');
        return false;
    }

    /**
     * @param bool $isLite
     * @return void
     * @throws DeprecationException
     * @since 4.5.0
     * @deprecated in 4.5.0.
     */
    public function setIsLite(bool $isLite): void
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'TaxRate::setIsLite() is deprecated.');
    }
}
