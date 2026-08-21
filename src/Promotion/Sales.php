<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion;

use Carbon\Carbon;
use craft\elements\Category;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Promotion\Events\SaleEvent;
use CraftCms\Commerce\Promotion\Events\SaleMatchEvent;
use CraftCms\Commerce\Promotion\Models\Sale;
use CraftCms\Commerce\Promotion\Records\Sale as SaleRecord;
use CraftCms\Commerce\Promotion\Records\SaleCategory as SaleCategoryRecord;
use CraftCms\Commerce\Promotion\Records\SalePurchasable as SalePurchasableRecord;
use CraftCms\Commerce\Promotion\Records\SaleUserGroup as SaleUserGroupRecord;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Store\Stores;
use DateTime;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

#[Singleton]
class Sales
{
    /** @var Sale[]|null */
    private ?array $allSales = null;

    /** @var Sale[]|null */
    private ?array $allActiveSales = null;

    /** @var array<int, array<int, bool|null>> */
    private array $purchasableSaleMatch = [];

    public function canUseSales(): bool
    {
        // TODO: migrate to app(Stores::class)->getAllStores() once Stores service migrated
        $singleStore = app(Stores::class)->getAllStores()->count() === 1;
        $noCatalogPricingRules = app(\CraftCms\Commerce\CatalogPricing\CatalogPricingRules::class)->getAllCatalogPricingRules()->isEmpty();

        return $singleStore && $noCatalogPricingRules;
    }

    public function getSaleById(int $id): ?Sale
    {
        foreach ($this->getAllSales() as $sale) {
            if ($sale->id == $id) {
                return $sale;
            }
        }

        return null;
    }

    /**
     * @return Sale[]
     */
    public function getAllSales(): array
    {
        if ($this->allSales !== null) {
            return $this->allSales;
        }

        $rows = DB::table(Table::SALES . ' as sales')
            ->select([
                'sales.id',
                'sales.name',
                'sales.description',
                'sales.dateFrom',
                'sales.dateTo',
                'sales.apply',
                'sales.applyAmount',
                'sales.stopProcessing',
                'sales.ignorePrevious',
                'sales.allGroups',
                'sales.allPurchasables',
                'sales.allCategories',
                'sales.sortOrder',
                'sales.categoryRelationshipType',
                'sales.enabled',
                'sales.dateCreated',
                'sales.dateUpdated',
                'sp.purchasableId',
                'spt.categoryId',
                'sug.userGroupId',
            ])
            ->leftJoin(Table::SALE_PURCHASABLES . ' as sp', 'sp.saleId', '=', 'sales.id')
            ->leftJoin(Table::SALE_CATEGORIES . ' as spt', 'spt.saleId', '=', 'sales.id')
            ->leftJoin(Table::SALE_USERGROUPS . ' as sug', 'sug.saleId', '=', 'sales.id')
            ->orderBy('sales.sortOrder')
            ->get()
            ->all();

        $allSalesById = [];
        $purchasables = [];
        $categories = [];
        $groups = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $id = $row['id'];

            if ($row['purchasableId']) {
                $purchasables[$id][] = $row['purchasableId'];
            }
            if ($row['categoryId']) {
                $categories[$id][] = $row['categoryId'];
            }
            if ($row['userGroupId']) {
                $groups[$id][] = $row['userGroupId'];
            }

            unset($row['purchasableId'], $row['userGroupId'], $row['categoryId']);

            if (!isset($allSalesById[$id])) {
                $allSalesById[$id] = new Sale($row);
            }
        }

        foreach ($allSalesById as $id => $sale) {
            $sale->setPurchasableIds($purchasables[$id] ?? []);
            $sale->setCategoryIds($categories[$id] ?? []);
            $sale->setUserGroupIds($groups[$id] ?? []);
        }

        $this->allSales = $allSalesById;

        return $this->allSales;
    }

    /**
     * Returns sales that match the purchasable.
     *
     * TODO: update Order type hint when Order element migrated to src/
     *
     * @return Sale[]
     */
    public function getSalesForPurchasable(PurchasableInterface $purchasable, mixed $order = null): array
    {
        $matchedSales = [];

        foreach ($this->getAllEnabledSales() as $sale) {
            if ($this->matchPurchasableAndSale($purchasable, $sale, $order)) {
                $matchedSales[] = $sale;

                if ($sale->stopProcessing) {
                    break;
                }
            }
        }

        return $matchedSales;
    }

    /**
     * TODO: update PurchasableInterface type hint when fully migrated
     *
     * @return Sale[]
     */
    public function getSalesRelatedToPurchasable(PurchasableInterface $purchasable): array
    {
        $sales = [];
        $id = $purchasable->getId();

        if ($id) {
            foreach ($this->getAllSales() as $sale) {
                $purchasableIds = $sale->getPurchasableIds();

                $relatedTo = [$sale->categoryRelationshipType => $purchasable->getPromotionRelationSource()];
                $saleCategories = $sale->getCategoryIds();

                // TODO: update Category/Entry element calls when migrated
                $relatedCategories = Category::find()->id($saleCategories)->relatedTo($relatedTo)->siteId($purchasable->siteId)->ids();
                $relatedEntries = Entry::find()->id($saleCategories)->relatedTo($relatedTo)->siteId($purchasable->siteId)->ids();
                $relatedCategoriesOrEntries = array_merge($relatedCategories, $relatedEntries);

                if (in_array($id, $purchasableIds, false) || !empty($relatedCategoriesOrEntries)) {
                    $sales[] = $sale;
                }
            }
        }

        return $sales;
    }

    /**
     * Returns the sale price of the purchasable based on all matched sales.
     *
     * TODO: update Order type hint when Order element migrated to src/
     */
    public function getSalePriceForPurchasable(PurchasableInterface $purchasable, mixed $order = null): float
    {
        $sales = $this->getSalesForPurchasable($purchasable, $order);
        $originalPrice = $purchasable->getPrice();

        $takeOffAmount = 0;
        $newPrice = null;

        foreach ($sales as $sale) {
            switch ($sale->apply) {
                case SaleRecord::APPLY_BY_PERCENT:
                    $takeOffAmount += ($sale->applyAmount * $originalPrice);
                    if ($sale->ignorePrevious) {
                        $newPrice = $originalPrice + ($sale->applyAmount * $originalPrice);
                    }
                    break;
                case SaleRecord::APPLY_TO_PERCENT:
                    $newPrice = (-$sale->applyAmount * $originalPrice);
                    break;
                case SaleRecord::APPLY_BY_FLAT:
                    $takeOffAmount += $sale->applyAmount;
                    if ($sale->ignorePrevious) {
                        $newPrice = $originalPrice + $sale->applyAmount;
                    }
                    break;
                case SaleRecord::APPLY_TO_FLAT:
                    $newPrice = -$sale->applyAmount;
                    break;
            }

            if ($sale->stopProcessing) {
                break;
            }
        }

        $salePrice = $originalPrice + $takeOffAmount;

        if ($newPrice !== null) {
            $salePrice = $newPrice;
        }

        if ($salePrice < 0) {
            $salePrice = 0;
        }

        // TODO: migrate to app(Currency::class)->round() once Currency service migrated
        return Currency::round($salePrice);
    }

    /**
     * Match a purchasable and sale and return the result.
     *
     * TODO: update Order type hint when Order element migrated to src/
     */
    public function matchPurchasableAndSale(PurchasableInterface $purchasable, Sale $sale, mixed $order = null): bool
    {
        $purchasableId = $purchasable->getId();
        $saleId = $sale->id;

        $this->purchasableSaleMatch[$purchasableId] ??= [];
        $this->purchasableSaleMatch[$purchasableId][$saleId] ??= null;

        if (!$order && $this->purchasableSaleMatch[$purchasableId][$saleId] !== null) {
            return $this->purchasableSaleMatch[$purchasableId][$saleId];
        }

        $this->purchasableSaleMatch[$purchasableId][$saleId] = false;

        if (!$purchasable->getIsPromotable()) {
            return false;
        }

        if (!$sale->allPurchasables && !in_array($purchasable->getId(), $sale->getPurchasableIds(), false)) {
            return false;
        }

        $date = new DateTime();

        if ($order) {
            // TODO: update isCompleted/dateOrdered when Order is migrated
            $date = $order->isCompleted ? $order->dateOrdered : $date;
        }

        if ($sale->dateFrom && $sale->dateFrom >= $date) {
            return false;
        }

        if ($sale->dateTo && $sale->dateTo <= $date) {
            return false;
        }

        if ($order) {
            // TODO: update getCustomer() when Order/User is migrated
            $user = $order->getCustomer();

            if (!$sale->allGroups) {
                if (null === $user) {
                    return false;
                }
                // TODO: update getGroups() when User element is migrated
                $userGroups = array_column($user->getGroups(), 'id');
                if (!$userGroups || !array_intersect($userGroups, $sale->getUserGroupIds())) {
                    return false;
                }
            }
        }

        if (!$order && !$sale->allGroups) {
            $userGroups = null;
            if ($currentUser = currentUserElement()) {
                $userGroups = array_column($currentUser->getGroups(), 'id');
            }

            if (!$userGroups || !array_intersect($userGroups, $sale->getUserGroupIds())) {
                return false;
            }
        }

        if (!$sale->allCategories) {
            $relatedTo = [$sale->categoryRelationshipType => $purchasable->getPromotionRelationSource()];
            $saleCategories = $sale->getCategoryIds();

            // TODO: update Category/Entry element calls when migrated
            $relatedCategories = Category::find()->id($saleCategories)->relatedTo($relatedTo)->siteId($purchasable->siteId)->ids();
            $relatedEntries = Entry::find()->id($saleCategories)->relatedTo($relatedTo)->siteId($purchasable->siteId)->ids();
            $relatedCategoriesOrEntries = array_merge($relatedCategories, $relatedEntries);

            if (empty($relatedCategoriesOrEntries)) {
                return false;
            }
        }

        $event = new SaleMatchEvent(sale: $sale, purchasable: $purchasable, isNew: false);
        event($event);

        if ($order) {
            unset($this->purchasableSaleMatch[$purchasableId][$saleId]);
            return $event->isValid;
        }

        $this->purchasableSaleMatch[$purchasableId][$saleId] = $event->isValid;

        return $event->isValid;
    }

    public function saveSale(Sale $model, bool $runValidation = true): bool
    {
        $isNew = !$model->id;

        if ($isNew) {
            $record = new SaleRecord();
        } else {
            $record = SaleRecord::find($model->id);

            if (!$record) {
                throw new \RuntimeException(t('No sale exists with the ID "{id}"', ['id' => $model->id], category: 'commerce'));
            }
        }

        if ($runValidation && !$model->validate()) {
            Log::info('Sale not saved due to validation error.');
            return false;
        }

        $beforeEv = new SaleEvent(sale: $model, isNew: $isNew);
        event($beforeEv);

        $record->name = $model->name;
        $record->description = $model->description;
        $record->dateFrom = $model->dateFrom ? Carbon::instance($model->dateFrom) : null;
        $record->dateTo = $model->dateTo ? Carbon::instance($model->dateTo) : null;
        $record->apply = $model->apply;
        $record->applyAmount = $model->applyAmount;
        $record->stopProcessing = $model->stopProcessing;
        $record->ignorePrevious = $model->ignorePrevious;
        $record->categoryRelationshipType = $model->categoryRelationshipType;
        $record->enabled = $model->enabled;

        if ($record->allGroups = $model->allGroups) {
            $model->setUserGroupIds([]);
        }
        if ($record->allCategories = $model->allCategories) {
            $model->setCategoryIds([]);
        }
        if ($record->allPurchasables = $model->allPurchasables) {
            $model->setPurchasableIds([]);
        }

        if (!$isNew) {
            // TODO: update to new date helper once migrated
            /** @phpstan-ignore-next-line */
            $model->dateCreated = \CraftCms\Cms\Support\DateTimeHelper::toDateTime($record->dateCreated);
            /** @phpstan-ignore-next-line */
            $model->dateUpdated = \CraftCms\Cms\Support\DateTimeHelper::toDateTime($record->dateUpdated);
        }

        DB::beginTransaction();

        try {
            $record->save();
            $model->id = $record->id;

            // TODO: update to new date helper once migrated
            /** @phpstan-ignore-next-line */
            $model->dateCreated = \CraftCms\Cms\Support\DateTimeHelper::toDateTime($record->dateCreated);
            /** @phpstan-ignore-next-line */
            $model->dateUpdated = \CraftCms\Cms\Support\DateTimeHelper::toDateTime($record->dateUpdated);

            SaleUserGroupRecord::where('saleId', $model->id)->delete();
            SalePurchasableRecord::where('saleId', $model->id)->delete();
            SaleCategoryRecord::where('saleId', $model->id)->delete();

            foreach ($model->getUserGroupIds() as $groupId) {
                $relation = new SaleUserGroupRecord();
                $relation->userGroupId = $groupId;
                $relation->saleId = $model->id;
                $relation->save();
            }

            foreach ($model->getCategoryIds() as $categoryId) {
                $relation = new SaleCategoryRecord();
                $relation->categoryId = $categoryId;
                $relation->saleId = $model->id;
                $relation->save();
            }

            foreach ($model->getPurchasableIds() as $purchasableId) {
                $relation = new SalePurchasableRecord();
                $relation->purchasableId = $purchasableId;
                $purchasable = Elements::getElementById($purchasableId, null, null, ['trashed' => null]);
                $relation->purchasableType = $purchasable::class;
                $relation->saleId = $model->id;
                $relation->save();

                ElementCaches::invalidateForElement($purchasable);
            }

            DB::commit();

            $this->clearCaches();

            $afterEv = new SaleEvent(sale: $model, isNew: $isNew);
            event($afterEv);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function reorderSales(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            DB::table(Table::SALES)->where('id', $id)->update(['sortOrder' => $sortOrder + 1]);
        }

        $this->clearCaches();

        return true;
    }

    public function deleteSaleById(int $id): bool
    {
        $record = SaleRecord::find($id);

        if (!$record) {
            return false;
        }

        $sale = $this->getSaleById($id);

        $this->clearCaches();

        $result = (bool) $record->delete();

        if ($result) {
            $ev = new SaleEvent(sale: $sale, isNew: false);
            event($ev);
        }

        return $result;
    }

    private function getAllEnabledSales(): array
    {
        if ($this->allActiveSales !== null) {
            return $this->allActiveSales;
        }

        $this->allActiveSales = array_filter($this->getAllSales(), fn(Sale $s) => $s->enabled);

        return $this->allActiveSales;
    }

    private function clearCaches(): void
    {
        $this->allSales = null;
        $this->allActiveSales = null;
        $this->purchasableSaleMatch = [];
    }
}
