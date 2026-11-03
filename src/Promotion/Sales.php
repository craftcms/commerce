<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion;

use Carbon\Carbon;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Promotion\Data\Sale;
use CraftCms\Commerce\Promotion\Events\SaleEvent;
use CraftCms\Commerce\Promotion\Events\SaleMatchEvent;
use CraftCms\Commerce\Promotion\Models\Sale as SaleRecord;
use CraftCms\Commerce\Promotion\Models\SaleEntry as SaleEntryRecord;
use CraftCms\Commerce\Promotion\Models\SalePurchasable as SalePurchasableRecord;
use CraftCms\Commerce\Promotion\Models\SaleUserGroup as SaleUserGroupRecord;
use CraftCms\Commerce\Purchasable\Contracts\PurchasableInterface;
use CraftCms\Commerce\Store\Stores;
use DateTime;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

#[Scoped]
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
                'sales.allEntries',
                'sales.sortOrder',
                'sales.entryRelationshipType',
                'sales.enabled',
                'sales.dateCreated',
                'sales.dateUpdated',
                'sp.purchasableId',
                'spt.entryId',
                'sug.userGroupId',
            ])
            ->leftJoin(Table::SALE_PURCHASABLES . ' as sp', 'sp.saleId', '=', 'sales.id')
            ->leftJoin(Table::SALE_ENTRIES . ' as spt', 'spt.saleId', '=', 'sales.id')
            ->leftJoin(Table::SALE_USERGROUPS . ' as sug', 'sug.saleId', '=', 'sales.id')
            ->orderBy('sales.sortOrder')
            ->get()
            ->all();

        $allSalesById = [];
        $purchasables = [];
        $entries = [];
        $groups = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $id = $row['id'];

            if ($row['purchasableId']) {
                $purchasables[$id][] = $row['purchasableId'];
            }
            if ($row['entryId']) {
                $entries[$id][] = $row['entryId'];
            }
            if ($row['userGroupId']) {
                $groups[$id][] = $row['userGroupId'];
            }

            unset($row['purchasableId'], $row['userGroupId'], $row['entryId']);

            if (!isset($allSalesById[$id])) {
                $allSalesById[$id] = new Sale($row);
            }
        }

        foreach ($allSalesById as $id => $sale) {
            $sale->setPurchasableIds($purchasables[$id] ?? []);
            $sale->setEntryIds($entries[$id] ?? []);
            $sale->setUserGroupIds($groups[$id] ?? []);
        }

        $this->allSales = $allSalesById;

        return $this->allSales;
    }

    /**
     * Returns sales that match the purchasable.
     *
     * @return Sale[]
     */
    public function getSalesForPurchasable(PurchasableInterface $purchasable, ?Order $order = null): array
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
     * @return Sale[]
     */
    public function getSalesRelatedToPurchasable(PurchasableInterface $purchasable): array
    {
        $sales = [];
        $id = $purchasable->getId();

        if ($id) {
            foreach ($this->getAllSales() as $sale) {
                $purchasableIds = $sale->getPurchasableIds();

                $relatedTo = [$sale->entryRelationshipType => $purchasable->getPromotionRelationSource()];
                $relatedEntries = Entry::find()->id($sale->getEntryIds())->relatedTo($relatedTo)->siteId($purchasable->siteId)->ids();

                if (in_array($id, $purchasableIds, false) || !empty($relatedEntries)) {
                    $sales[] = $sale;
                }
            }
        }

        return $sales;
    }

    /**
     * Returns the sale price of the purchasable based on all matched sales.
     */
    public function getSalePriceForPurchasable(PurchasableInterface $purchasable, ?Order $order = null): float
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

        return Currency::round($salePrice);
    }

    /**
     * Match a purchasable and sale and return the result.
     */
    public function matchPurchasableAndSale(PurchasableInterface $purchasable, Sale $sale, ?Order $order = null): bool
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
            $date = $order->isCompleted ? $order->dateOrdered : $date;
        }

        if ($sale->dateFrom && $sale->dateFrom >= $date) {
            return false;
        }

        if ($sale->dateTo && $sale->dateTo <= $date) {
            return false;
        }

        if ($order) {
            $user = $order->getCustomer();

            if (!$sale->allGroups) {
                if (null === $user) {
                    return false;
                }
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

        if (!$sale->allEntries) {
            $relatedTo = [$sale->entryRelationshipType => $purchasable->getPromotionRelationSource()];
            $relatedEntries = Entry::find()->id($sale->getEntryIds())->relatedTo($relatedTo)->siteId($purchasable->siteId)->ids();

            if (empty($relatedEntries)) {
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
                throw new \RuntimeException(t('No sale exists with the ID “{id}”', ['id' => $model->id], category: 'commerce'));
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
        $record->entryRelationshipType = $model->entryRelationshipType;
        $record->enabled = $model->enabled;

        if ($record->allGroups = $model->allGroups) {
            $model->setUserGroupIds([]);
        }
        if ($record->allEntries = $model->allEntries) {
            $model->setEntryIds([]);
        }
        if ($record->allPurchasables = $model->allPurchasables) {
            $model->setPurchasableIds([]);
        }

        if (!$isNew) {
            /** @phpstan-ignore-next-line */
            $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated);
            /** @phpstan-ignore-next-line */
            $model->dateUpdated = DateTimeHelper::toDateTime($record->dateUpdated);
        }

        DB::beginTransaction();

        try {
            $record->save();
            $model->id = $record->id;

            /** @phpstan-ignore-next-line */
            $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated);
            /** @phpstan-ignore-next-line */
            $model->dateUpdated = DateTimeHelper::toDateTime($record->dateUpdated);

            SaleUserGroupRecord::where('saleId', $model->id)->delete();
            SalePurchasableRecord::where('saleId', $model->id)->delete();
            SaleEntryRecord::where('saleId', $model->id)->delete();

            foreach ($model->getUserGroupIds() as $groupId) {
                $relation = new SaleUserGroupRecord();
                $relation->userGroupId = $groupId;
                $relation->saleId = $model->id;
                $relation->save();
            }

            foreach ($model->getEntryIds() as $entryId) {
                $relation = new SaleEntryRecord();
                $relation->entryId = $entryId;
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
