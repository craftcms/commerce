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
use craft\commerce\records\Sale as SaleRecord;
use craft\db\Query;
use craft\helpers\UrlHelper;
use DateTime;

/**
 * Sale model.
 *
 * @property array $categoryIds
 * @property string|false $cpEditUrl
 * @property string $applyAmountAsFlat
 * @property string $applyAmountAsPercent
 * @property array $purchasableIds
 * @property array $userGroupIds
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Sale extends Model
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
    public string $apply = SaleRecord::APPLY_BY_PERCENT;

    /**
     * @var float|null The amount field used by the apply option
     */
    public ?float $applyAmount = null;

    /**
     * @var bool ignore the previous sales that affect the purchasable
     */
    public bool $ignorePrevious = false;

    /**
     * @var bool should the sales system stop processing other sales after this one
     */
    public bool $stopProcessing = false;

    /**
     * @var bool Match all groups
     */
    public bool $allGroups = false;

    /**
     * @var bool Match all purchasables
     */
    public bool $allPurchasables = false;

    /**
     * @var bool Match all categories
     */
    public bool $allCategories = false;

    /**
     * @var string Type of relationship between Categories and Products
     */
    public string $categoryRelationshipType = SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH;

    /**
     * @var bool Enabled
     */
    public bool $enabled = true;

    /**
     * @var int|null The order index of the application of the sale
     */
    public ?int $sortOrder = null;

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
     * @var int[]|null Product Type IDs
     */
    private ?array $_categoryIds = null;

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
            [
                ['categoryRelationshipType'],
                'in',
                'range' => [
                    SaleRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE,
                    SaleRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET,
                    SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH,
                ],
            ],
            [['enabled'], 'boolean'],
            [['name', 'apply', 'allGroups', 'allPurchasables', 'allCategories'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function datetimeAttributes(): array
    {
        $attributes = parent::datetimeAttributes();
        $attributes[] = 'dateFrom';
        $attributes[] = 'dateTo';
        return $attributes;
    }

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/promotions/sales/' . $this->id);
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

    /**
     * @return string
     */
    public function getApplyAmountAsPercent(): string
    {
        if ($this->applyAmount) {
            return Craft::$app->formatter->asPercent(-$this->applyAmount, 2);
        }

        return Craft::$app->formatter->asPercent(0);
    }

    /**
     * @return string
     */
    public function getApplyAmountAsFlat(): string
    {
        return $this->applyAmount !== 0 ? (string)($this->applyAmount * -1) : '0';
    }

    /**
     * @return array
     */
    public function getCategoryIds(): array
    {
        if (!isset($this->_categoryIds)) {
            $categoryIds = [];
            if ($this->id) {
                $categoryIds = (new Query())->select(
                    'spt.categoryId')
                    ->from(Table::SALES . ' sales')
                    ->leftJoin(Table::SALE_CATEGORIES . ' spt', '[[spt.saleId]]=[[sales.id]]')
                    ->where(['sales.id' => $this->id])
                    ->column();

                $categoryIds = array_filter($categoryIds);
            }

            $this->_categoryIds = $categoryIds;
        }

        return $this->_categoryIds;
    }

    /**
     * @return array
     */
    public function getPurchasableIds(): array
    {
        if (!isset($this->_purchasableIds)) {
            $purchasableIds = [];
            if ($this->id) {
                $purchasableIds = (new Query())->select(
                    'sp.purchasableId')
                    ->from(Table::SALES . ' sales')
                    ->leftJoin(Table::SALE_PURCHASABLES . ' sp', '[[sp.saleId]]=[[sales.id]]')
                    ->where(['sales.id' => $this->id])
                    ->column();

                $purchasableIds = array_filter($purchasableIds);
            }

            $this->_purchasableIds = $purchasableIds;
        }

        return $this->_purchasableIds;
    }

    /**
     * @return array
     */
    public function getUserGroupIds(): array
    {
        if (!isset($this->_userGroupIds)) {
            $userGroupIds = [];
            if ($this->id) {
                $userGroupIds = (new Query())->select(
                    'sug.userGroupId')
                    ->from(Table::SALES . ' sales')
                    ->leftJoin(Table::SALE_USERGROUPS . ' sug', '[[sug.saleId]]=[[sales.id]]')
                    ->where(['sales.id' => $this->id])
                    ->column();
                $userGroupIds = array_filter($userGroupIds);
            }

            $this->_userGroupIds = $userGroupIds;
        }

        return $this->_userGroupIds;
    }

    /**
     * Sets the related category ids
     *
     * @param array $ids
     */
    public function setCategoryIds(array $ids): void
    {
        $this->_categoryIds = array_unique($ids);
    }

    /**
     * Sets the related purchasable ids
     *
     * @param array $purchasableIds
     */
    public function setPurchasableIds(array $purchasableIds): void
    {
        $this->_purchasableIds = array_unique($purchasableIds);
    }

    /**
     * Sets the related user group ids
     *
     * @param array $userGroupIds
     */
    public function setUserGroupIds(array $userGroupIds): void
    {
        $this->_userGroupIds = array_unique($userGroupIds);
    }
}
