<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\db\Table;
use craft\commerce\records\CatalogPricingRule as PricingCatalogRuleRecord;
use craft\db\Query;
use craft\helpers\UrlHelper;
use DateTime;

/**
 * Catalog Pricing Rule model.
 *
 * @property string|false $cpEditUrl
 * @property string $applyAmountAsFlat
 * @property string $applyAmountAsPercent
 * @property array $purchasableIds
 * @property array $userGroupIds
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRule extends Model
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
     * @var string|null Description
     */
    public ?string $description = null;

    /**
     * @var DateTime|null Date From
     */
    public ?DateTime $dateFrom = null;

    /**
     * @var DateTime|null Date To
     */
    public ?DateTime $dateTo = null;

    /**
     * @var string How the sale should be applied
     */
    public string $apply = PricingCatalogRuleRecord::APPLY_BY_PERCENT;

    /**
     * @var float|null The amount field used by the apply option
     */
    public ?float $applyAmount = null;

    /**
     * @var bool Match all groups
     */
    public bool $allGroups = false;

    /**
     * @var bool Match all purchasables
     */
    public bool $allPurchasables = false;

    /**
     * @var bool Enabled
     */
    public bool $enabled = true;

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
     * @var int[]|null Product Ids
     */
    private ?array $_purchasableIds = null;

    /**
     * @var int[]|null Group IDs
     */
    private ?array $_userGroupIds = null;

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['apply'], 'in', 'range' => ['toPercent', 'toFlat', 'byPercent', 'byFlat']],
            [['enabled'], 'boolean'],
            [['name', 'apply', 'allGroups', 'allPurchasables'], 'required'],
            [['id', 'applyAmount', 'dateFrom', 'dateTo'], 'safe'],
        ];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/promotions/catalog-pricing-rules/' . $this->id);
    }

    /**
     * @return array
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'purchasableIds';

        return $fields;
    }

    public function getApplyAmountAsPercent(): string
    {
        return Craft::$app->getFormatter()->asPercent(-($this->applyAmount ?? 0.0));
    }

    public function getApplyAmountAsFlat(): string
    {
        return $this->applyAmount !== null ? (string)($this->applyAmount * -1) : '0';
    }

    public function getPurchasableIds(): array
    {
        if (!isset($this->_purchasableIds)) {
            $purchasableIds = [];
            if ($this->id) {
                $purchasableIds = (new Query())->select(
                    'sp.purchasableId')
                    ->from(Table::CATALOG_PRICING_RULES . ' pcr')
                    ->leftJoin(Table::CATALOG_PRICING_RULES_PURCHASABLES . ' pcrs', '[[pcrs.catalogPricingRuleId]]=[[pcr.id]]')
                    ->where(['pcr.id' => $this->id])
                    ->column();

                $purchasableIds = array_filter($purchasableIds);
            }

            $this->_purchasableIds = $purchasableIds;
        }

        return $this->_purchasableIds;
    }

    public function getUserGroupIds(): array
    {
        if (!isset($this->_userGroupIds)) {
            $userGroupIds = [];
            if ($this->id) {
                $userGroupIds = (new Query())->select(
                    'pcru.userGroupId')
                    ->from(Table::CATALOG_PRICING_RULES . ' pcr')
                    ->leftJoin(Table::CATALOG_PRICING_RULES_USERS . ' pcru', '[[pcru.catalogPricingRuleId]]=[[pcr.id]]')
                    ->where(['pcr.id' => $this->id])
                    ->column();
                $userGroupIds = array_filter($userGroupIds);
            }

            $this->_userGroupIds = $userGroupIds;
        }

        return $this->_userGroupIds;
    }

    /**
     * Sets the related purchasable ids
     */
    public function setPurchasableIds(array $purchasableIds): void
    {
        $this->_purchasableIds = array_unique($purchasableIds);
    }

    /**
     * Sets the related user group ids
     */
    public function setUserGroupIds(array $userGroupIds): void
    {
        $this->_userGroupIds = array_unique($userGroupIds);
    }

    /**
     * @param float $price
     * @return float
     */
    public function getRulePriceFromPrice(float $price): float
    {
        $price = match ($this->apply) {
            PricingCatalogRuleRecord::APPLY_BY_PERCENT => $price * (1 + $this->applyAmount),
            PricingCatalogRuleRecord::APPLY_BY_FLAT => $price + $this->applyAmount,
            PricingCatalogRuleRecord::APPLY_TO_PERCENT => $price * -$this->applyAmount,
            PricingCatalogRuleRecord::APPLY_TO_FLAT => -$this->applyAmount,
            default => $price,
        };

        return max($price, 0);
    }
}
