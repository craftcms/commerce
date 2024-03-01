<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\SaleEvent;
use craft\commerce\events\SaleMatchEvent;
use craft\commerce\helpers\Currency as CurrencyHelper;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Sale as SaleRecord;
use craft\commerce\records\SaleCategory as SaleCategoryRecord;
use craft\commerce\records\SalePurchasable as SalePurchasableRecord;
use craft\commerce\records\SaleUserGroup as SaleUserGroupRecord;
use craft\db\Query;
use craft\elements\Category;
use craft\elements\Entry;
use craft\errors\SiteNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use DateTime;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use function get_class;
use function in_array;

/**
 * Sale service.
 *
 * @property Sale[] $allSales
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Sales extends Component
{
    /**
     * @event SaleMatchEvent The event that is triggered before Commerce attempts to match a sale to a purchasable.
     *
     * The `isValid` event property can be set to `false` to prevent the application of the matched sale.
     *
     * ```php
     * use craft\commerce\events\SaleMatchEvent;
     * use craft\commerce\services\Sales;
     * use craft\commerce\base\PurchasableInterface;
     * use craft\commerce\models\Sale;
     * use yii\base\Event;
     *
     * Event::on(
     *     Sales::class,
     *     Sales::EVENT_BEFORE_MATCH_PURCHASABLE_SALE,
     *     function(SaleMatchEvent $event) {
     *         // @var Sale $sale
     *         $sale = $event->sale;
     *         // @var PurchasableInterface $purchasable
     *         $purchasable = $event->purchasable;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // Use custom business logic to exclude purchasable from sale
     *         // with `$event->isValid = false`
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_MATCH_PURCHASABLE_SALE = 'beforeMatchPurchasableSale';

    /**
     * @event SaleEvent The event that is triggered before a sale is saved.
     * @since 2.2
     *
     * ```php
     * use craft\commerce\events\SaleEvent;
     * use craft\commerce\services\Sales;
     * use craft\commerce\models\Sale;
     * use yii\base\Event;
     *
     * Event::on(
     *     Sales::class,
     *     Sales::EVENT_BEFORE_SAVE_SALE,
     *     function(SaleEvent $event) {
     *         // @var Sale $sale
     *         $sale = $event->sale;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_SAVE_SALE = 'beforeSaveSale';

    /**
     * @event SaleEvent The event that is triggered after a sale is saved.
     * @since 2.2
     *
     * ```php
     * use craft\commerce\events\SaleEvent;
     * use craft\commerce\services\Sales;
     * use craft\commerce\models\Sale;
     * use yii\base\Event;
     *
     * Event::on(
     *     Sales::class,
     *     Sales::EVENT_BEFORE_SAVE_SALE,
     *     function(SaleEvent $event) {
     *         // @var Sale $sale
     *         $sale = $event->sale;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_AFTER_SAVE_SALE = 'afterSaveSale';

    /**
     * @event SaleEvent The event that is triggered after a sale is deleted.
     *
     * ```php
     * use craft\commerce\events\SaleEvent;
     * use craft\commerce\services\Sales;
     * use craft\commerce\models\Sale;
     * use yii\base\Event;
     *
     * Event::on(
     *     Sales::class,
     *     Sales::EVENT_AFTER_DELETE_SALE,
     *     function(SaleEvent $event) {
     *         // @var Sale $sale
     *         $sale = $event->sale;
     *
     *         // do something
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_AFTER_DELETE_SALE = 'afterDeleteSale';

    /**
     * @var Sale[]|null
     */
    private ?array $_allSales = null;

    /**
     * @var Sale[]|null
     */
    private ?array $_allActiveSales = null;

    /**
     * @var array
     */
    private array $_purchasableSaleMatch = [];

    /**
     * @return bool
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @since 5.0.0
     */
    public function canUseSales(): bool
    {
        return Plugin::getInstance()->getStores()->getAllStores()->count() === 1 && Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRules()->isEmpty();
    }

    /**
     * Get a sale by its ID.
     */
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
     * Get all sales.
     *
     * @return Sale[]
     */
    public function getAllSales(): array
    {
        if (!isset($this->_allSales)) {
            $sales = (new Query())->select([
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
                ->from(Table::SALES . ' sales')
                ->leftJoin(Table::SALE_PURCHASABLES . ' sp', '[[sp.saleId]] = [[sales.id]]')
                ->leftJoin(Table::SALE_CATEGORIES . ' spt', '[[spt.saleId]] = [[sales.id]]')
                ->leftJoin(Table::SALE_USERGROUPS . ' sug', '[[sug.saleId]] = [[sales.id]]')
                ->orderBy(['sales.sortOrder' => 'ASC'])
                ->all();

            $allSalesById = [];
            $purchasables = [];
            $categories = [];
            $groups = [];

            foreach ($sales as $sale) {
                $id = $sale['id'];
                if ($sale['purchasableId']) {
                    $purchasables[$id][] = $sale['purchasableId'];
                }

                if ($sale['categoryId']) {
                    $categories[$id][] = $sale['categoryId'];
                }

                if ($sale['userGroupId']) {
                    $groups[$id][] = $sale['userGroupId'];
                }

                unset($sale['purchasableId'], $sale['userGroupId'], $sale['categoryId']);

                if (!isset($allSalesById[$id])) {
                    $allSalesById[$id] = new Sale($sale);
                }
            }

            foreach ($allSalesById as $id => $sale) {
                $sale->setPurchasableIds($purchasables[$id] ?? []);
                $sale->setCategoryIds($categories[$id] ?? []);
                $sale->setUserGroupIds($groups[$id] ?? []);
            }

            $this->_allSales = $allSalesById;
        }

        return $this->_allSales;
    }

    /**
     * Returns the sales that match the purchasable.
     *
     * @param Order|null $order
     * @return Sale[]
     * @throws InvalidConfigException
     */
    public function getSalesForPurchasable(PurchasableInterface $purchasable, Order $order = null): array
    {
        $matchedSales = [];

        foreach ($this->_getAllEnabledSales() as $sale) {
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
     * @param PurchasableInterface $purchasable
     * @return array
     */
    public function getSalesRelatedToPurchasable(PurchasableInterface $purchasable): array
    {
        /** @var Purchasable $purchasable */
        $sales = [];
        $id = $purchasable->getId();

        if ($id) {
            foreach ($this->getAllSales() as $sale) {
                // Get related by product specifically
                $purchasableIds = $sale->getPurchasableIds();

                // Get related via category
                $relatedTo = [$sale->categoryRelationshipType => $purchasable->getPromotionRelationSource()];
                $saleCategories = $sale->getCategoryIds();

                $relatedCategories = Category::find()
                    ->id($saleCategories)
                    ->relatedTo($relatedTo)
                    ->siteId($purchasable->siteId)
                    ->ids();
                $relatedEntries = Entry::find()
                    ->id($saleCategories)
                    ->relatedTo($relatedTo)
                    ->siteId($purchasable->siteId)
                    ->ids();
                $relatedCategoriesOrEntries = array_merge($relatedCategories, $relatedEntries);

                if (in_array($id, $purchasableIds, false) || !empty($relatedCategoriesOrEntries)) {
                    $sales[] = $sale;
                }
            }
        }

        return $sales;
    }

    /**
     * Returns the salePrice of the purchasable based on all the sales.
     *
     * @param Order|null $order
     */
    public function getSalePriceForPurchasable(PurchasableInterface $purchasable, Order $order = null): float
    {
        $sales = $this->getSalesForPurchasable($purchasable, $order);
        $originalPrice = $purchasable->getPrice();

        $takeOffAmount = 0;
        $newPrice = null;

        /** @var Sale $sale */
        foreach ($sales as $sale) {
            switch ($sale->apply) {
                case SaleRecord::APPLY_BY_PERCENT:
                    // applyAmount is stored as a negative already
                    $takeOffAmount += ($sale->applyAmount * $originalPrice);
                    if ($sale->ignorePrevious) {
                        $newPrice = $originalPrice + ($sale->applyAmount * $originalPrice);
                    }
                    break;
                case SaleRecord::APPLY_TO_PERCENT:
                    // applyAmount needs to be reversed since it is stored as negative
                    $newPrice = (-$sale->applyAmount * $originalPrice);
                    break;
                case SaleRecord::APPLY_BY_FLAT:
                    // applyAmount is stored as a negative already
                    $takeOffAmount += $sale->applyAmount;
                    if ($sale->ignorePrevious) {
                        // applyAmount is always negative so add the negative amount to the original price for the new price.
                        $newPrice = $originalPrice + $sale->applyAmount;
                    }
                    break;
                case SaleRecord::APPLY_TO_FLAT:
                    // applyAmount needs to be reversed since it is stored as negative
                    $newPrice = -$sale->applyAmount;
                    break;
            }

            // If the stop processing flag is true, it must been the last
            // since the sales for this purchasable would have returned it last.
            if ($sale->stopProcessing) {
                break;
            }
        }

        $salePrice = ($originalPrice + $takeOffAmount);

        // A newPrice has been set so use it.
        if ($newPrice !== null) {
            $salePrice = $newPrice;
        }

        if ($salePrice < 0) {
            $salePrice = 0;
        }

        return CurrencyHelper::round($salePrice);
    }

    /**
     * Match a product and a sale and return the result.
     *
     * @param Order|null $order
     * @throws InvalidConfigException
     */
    public function matchPurchasableAndSale(PurchasableInterface $purchasable, Sale $sale, Order $order = null): bool
    {
        /** @var Purchasable $purchasable */
        $purchasableId = $purchasable->id;
        $saleId = $sale->id;

        if (!isset($this->_purchasableSaleMatch[$purchasableId])) {
            $this->_purchasableSaleMatch[$purchasableId] = [];
        }

        if (!isset($this->_purchasableSaleMatch[$purchasableId][$saleId])) {
            $this->_purchasableSaleMatch[$purchasableId][$saleId] = null;
        }

        // Only use memoized data if we are matching outside of the context of an order
        if (!$order && $this->_purchasableSaleMatch[$purchasableId][$saleId] !== null) {
            return $this->_purchasableSaleMatch[$purchasableId][$saleId];
        }

        // default response is no match
        $this->_purchasableSaleMatch[$purchasableId][$saleId] = false;

        // can't match something not promotable
        if (!$purchasable->getIsPromotable()) {
            return false;
        }

        // Purchasable ID match
        if (!$sale->allPurchasables && !in_array($purchasable->getId(), $sale->getPurchasableIds(), false)) {
            return false;
        }

        $date = new DateTime();

        if ($order) {
            // Date we care about in the context of an order is the date the order was placed.
            // If the order is still a cart, use the current date time.
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
                // User group condition means we have to have a real user
                if (null === $user) {
                    return false;
                }
                // User groups of the order's user
                $userGroups = ArrayHelper::getColumn($user->getGroups(), 'id');
                if (!$userGroups || !array_intersect($userGroups, $sale->getUserGroupIds())) {
                    return false;
                }
            }
        }

        // Are we dealing with the current session outside of any cart/order context
        if (!$order && !$sale->allGroups) {
            // User groups of the currently logged in user
            $userGroups = null;
            if ($currentUser = Craft::$app->getUser()->getIdentity()) {
                $userGroups = ArrayHelper::getColumn($currentUser->getGroups(), 'id');
            }

            if (!$userGroups || !array_intersect($userGroups, $sale->getUserGroupIds())) {
                return false;
            }
        }

        // Category match
        if (!$sale->allCategories) {
            $relatedTo = [$sale->categoryRelationshipType => $purchasable->getPromotionRelationSource()];
            $saleCategories = $sale->getCategoryIds();
            $relatedCategories = Category::find()
                ->id($saleCategories)
                ->relatedTo($relatedTo)
                ->siteId($purchasable->siteId)
                ->ids();
            $relatedEntries = Entry::find()
                ->id($saleCategories)
                ->relatedTo($relatedTo)
                ->siteId($purchasable->siteId)
                ->ids();
            $relatedCategoriesOrEntries = array_merge($relatedCategories, $relatedEntries);
            if (empty($relatedCategoriesOrEntries)) {
                return false;
            }
        }

        $saleMatchEvent = new SaleMatchEvent(compact('sale', 'purchasable'));

        // Raising the 'beforeMatchPurchasableSale' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_MATCH_PURCHASABLE_SALE)) {
            $this->trigger(self::EVENT_BEFORE_MATCH_PURCHASABLE_SALE, $saleMatchEvent);
        }

        // If an order has been supplied we do not want to memoize the match
        if ($order) {
            unset($this->_purchasableSaleMatch[$purchasableId][$saleId]);
            return $saleMatchEvent->isValid;
        }

        $this->_purchasableSaleMatch[$purchasableId][$saleId] = $saleMatchEvent->isValid;
        return $this->_purchasableSaleMatch[$purchasableId][$saleId];
    }

    /**
     * Save a Sale.
     *
     * @param bool $runValidation should we validate this before saving.
     * @throws Exception
     * @throws \Exception
     */
    public function saveSale(Sale $model, bool $runValidation = true): bool
    {
        $isNewSale = !$model->id;

        if ($isNewSale) {
            $record = new SaleRecord();
        } else {
            $record = SaleRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No sale exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Sale not saved due to validation error.', __METHOD__);

            return false;
        }

        $fields = [
            'name',
            'description',
            'dateFrom',
            'dateTo',
            'apply',
            'applyAmount',
            'stopProcessing',
            'ignorePrevious',
            'categoryRelationshipType',
            'enabled',
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        if ($record->allGroups = $model->allGroups) {
            $model->setUserGroupIds([]);
        }
        if ($record->allCategories = $model->allCategories) {
            $model->setCategoryIds([]);
        }
        if ($record->allPurchasables = $model->allPurchasables) {
            $model->setPurchasableIds([]);
        }

        // Make sure `dateCreated` and `dateUpdated` are set on the model
        if (!$isNewSale) {
            $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated);
            $model->dateUpdated = DateTimeHelper::toDateTime($record->dateUpdated);
        }

        // Fire an 'beforeSaveSection' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_SALE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_SALE, new SaleEvent([
                'sale' => $model,
                'isNew' => $isNewSale,
            ]));
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $model->id = $record->id;

            // Update datetime attributes
            $model->dateCreated = DateTimeHelper::toDateTime($record->dateCreated);
            $model->dateUpdated = DateTimeHelper::toDateTime($record->dateUpdated);

            SaleUserGroupRecord::deleteAll(['saleId' => $model->id]);
            SalePurchasableRecord::deleteAll(['saleId' => $model->id]);
            SaleCategoryRecord::deleteAll(['saleId' => $model->id]);

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
                $purchasable = Craft::$app->getElements()->getElementById($purchasableId, null, null, ['trashed' => null]);
                $relation->purchasableType = get_class($purchasable);
                $relation->saleId = $model->id;
                $relation->save();

                Craft::$app->getElements()->invalidateCachesForElement($purchasable);
            }

            $transaction->commit();

            $this->_clearCaches();

            // Fire an 'beforeSaveSection' event
            if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_SALE)) {
                $this->trigger(self::EVENT_AFTER_SAVE_SALE, new SaleEvent([
                    'sale' => $model,
                    'isNew' => $isNewSale,
                ]));
            }

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Reorder Sales based on a list of ids.
     *
     * @param int[] $ids
     * @return bool
     * @throws \yii\db\Exception
     */
    public function reorderSales(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()
                ->update(Table::SALES, ['sortOrder' => $sortOrder + 1], ['id' => $id])
                ->execute();
        }

        $this->_clearCaches();

        return true;
    }

    /**
     * Delete a sale by its id.
     *
     * @param int $id
     * @return bool
     * @throws StaleObjectException
     */
    public function deleteSaleById(int $id): bool
    {
        $saleRecord = SaleRecord::findOne($id);

        if (!$saleRecord) {
            return false;
        }

        $sale = $this->getSaleById($saleRecord->id);

        $this->_clearCaches();
        $result = (bool)$saleRecord->delete();

        //Raise the afterDeleteSale event
        if ($result && $this->hasEventHandlers(self::EVENT_AFTER_DELETE_SALE)) {
            $this->trigger(self::EVENT_AFTER_DELETE_SALE, new SaleEvent([
                'sale' => $sale,
                'isNew' => false,
            ]));
        }


        return $result;
    }

    /**
     * Get all enabled sales.
     *
     * @return array
     */
    private function _getAllEnabledSales(): array
    {
        if (!isset($this->_allActiveSales)) {
            $sales = $this->getAllSales();
            $activeSales = [];
            foreach ($sales as $sale) {
                if ($sale->enabled) {
                    $activeSales[] = $sale;
                }
            }

            $this->_allActiveSales = $activeSales;
        }

        return $this->_allActiveSales;
    }

    /**
     * Clear memoization caches
     *
     * @since 3.1.4
     */
    private function _clearCaches(): void
    {
        $this->_allActiveSales = null;
        $this->_allSales = null;
        $this->_purchasableSaleMatch = [];
    }
}
