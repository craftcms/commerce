<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Models;

use craft\commerce\Plugin;
use craft\commerce\records\Sale as SaleRecord;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Commerce\Database\Table;
use DateTime;
use Illuminate\Support\Facades\DB;

class Sale extends Component
{
    public ?int $id = null;

    public ?string $name = null;

    public ?string $description = null;

    public ?DateTime $dateFrom = null;

    public ?DateTime $dateTo = null;

    public string $apply = SaleRecord::APPLY_BY_PERCENT;

    public ?float $applyAmount = null;

    public bool $ignorePrevious = false;

    public bool $stopProcessing = false;

    public bool $allGroups = false;

    public bool $allPurchasables = false;

    public bool $allCategories = false;

    public string $categoryRelationshipType = SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH;

    public bool $enabled = true;

    public ?int $sortOrder = null;

    public ?DateTime $dateCreated = null;

    public ?DateTime $dateUpdated = null;

    private ?array $_purchasableIds = null;

    private ?array $_categoryIds = null;

    private ?array $_userGroupIds = null;

    #[\Override]
    public function getRules(): array
    {
        return [
            'apply' => ['required', 'in:toPercent,toFlat,byPercent,byFlat'],
            'categoryRelationshipType' => ['required', 'in:' . implode(',', [
                SaleRecord::CATEGORY_RELATIONSHIP_TYPE_SOURCE,
                SaleRecord::CATEGORY_RELATIONSHIP_TYPE_TARGET,
                SaleRecord::CATEGORY_RELATIONSHIP_TYPE_BOTH,
            ])],
            'enabled' => ['boolean'],
            'name' => ['required', 'string'],
            'allGroups' => ['required', 'boolean'],
            'allPurchasables' => ['required', 'boolean'],
            'allCategories' => ['required', 'boolean'],
        ];
    }

    public function getCpEditUrl(): string
    {
        // TODO: migrate to app(Stores::class)->getPrimaryStore() once service migrated to src/
        /** @phpstan-ignore-next-line */
        $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        return $store->getStoreSettingsUrl('sales/' . $this->id);
    }

    public function getApplyAmountAsPercent(): string
    {
        return I18N::getFormatter()->asPercent(-($this->applyAmount ?? 0.0));
    }

    public function getApplyAmountAsFlat(): string
    {
        return $this->applyAmount !== null ? (string)($this->applyAmount * -1) : '0';
    }

    public function getCategoryIds(): array
    {
        if (!isset($this->_categoryIds)) {
            $categoryIds = [];
            if ($this->id) {
                $categoryIds = array_filter(
                    DB::table(Table::SALES . ' sales')
                        ->leftJoin(Table::SALE_CATEGORIES . ' spt', 'spt.saleId', '=', 'sales.id')
                        ->where('sales.id', $this->id)
                        ->pluck('spt.categoryId')
                        ->all()
                );
            }
            $this->_categoryIds = $categoryIds;
        }

        return $this->_categoryIds;
    }

    public function getPurchasableIds(): array
    {
        if (!isset($this->_purchasableIds)) {
            $purchasableIds = [];
            if ($this->id) {
                $purchasableIds = array_filter(
                    DB::table(Table::SALES . ' sales')
                        ->leftJoin(Table::SALE_PURCHASABLES . ' sp', 'sp.saleId', '=', 'sales.id')
                        ->where('sales.id', $this->id)
                        ->pluck('sp.purchasableId')
                        ->all()
                );
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
                $userGroupIds = array_filter(
                    DB::table(Table::SALES . ' sales')
                        ->leftJoin(Table::SALE_USERGROUPS . ' sug', 'sug.saleId', '=', 'sales.id')
                        ->where('sales.id', $this->id)
                        ->pluck('sug.userGroupId')
                        ->all()
                );
            }
            $this->_userGroupIds = $userGroupIds;
        }

        return $this->_userGroupIds;
    }

    public function setCategoryIds(array $ids): void
    {
        $this->_categoryIds = array_unique($ids);
    }

    public function setPurchasableIds(array $purchasableIds): void
    {
        $this->_purchasableIds = array_unique($purchasableIds);
    }

    public function setUserGroupIds(array $userGroupIds): void
    {
        $this->_userGroupIds = array_unique($userGroupIds);
    }
}
