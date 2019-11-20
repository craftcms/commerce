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
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Sale as SaleRecord;
use craft\commerce\records\SaleCategory as SaleCategoryRecord;
use craft\commerce\records\SalePurchasable as SalePurchasableRecord;
use craft\commerce\records\SaleUserGroup as SaleUserGroupRecord;
use craft\db\Query;
use craft\elements\Category;
use DateTime;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
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
    // Constants
    // =========================================================================

    /**
     * @event SaleMatchEvent This event is raised after a sale has matched all other conditions
     * You may set [[SaleMatchEvent::isValid]] to `false` to prevent the application of the matched sale.
     *
     * Plugins can get notified when a purchasable matches a sale.
     *
     * ```php
     * use craft\commerce\events\SaleMatchEvent;
     * use craft\commerce\services\Sales;
     * use yii\base\Event;
     *
     * Event::on(Sales::class, Sales::EVENT_BEFORE_MATCH_PURCHASABLE_SALE, function(SaleMatchEvent $e) {
     *      // Perhaps prevent the purchasable match with sale based on some business logic.
     * });
     * ```
     */
    const EVENT_BEFORE_MATCH_PURCHASABLE_SALE = 'beforeMatchPurchasableSale';

    /**
     * @event SaleEvent The event that is triggered before a sale is saved.
     * @since 2.2
     */
    const EVENT_BEFORE_SAVE_SALE = 'beforeSaveSale';

    /**
     * @event SaleEvent The event that is triggered after a sale is saved.
     * @since 2.2
     */
    const EVENT_AFTER_SAVE_SALE = 'afterSaveSale';

    // Properties
    // =========================================================================

    /**
     * @var Sale[]
     */
    private $_allSales;

    /**
     * @var Sale[]
     */
    private $_allActiveSales;

    /**
     * @var array
     */
    private $_purchasableSaleMatch = [];

    // Public Methods
    // =========================================================================

    /**
     * Get a sale by its ID.
     *
     * @param int $id
     * @return Sale|null
     */
    public function getSaleById($id)
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
        if (null === $this->_allSales) {
            $sales = (new Query())->select(
                'sales.id,
                sales.name,
                sales.description,
                sales.dateFrom,
                sales.dateTo,
                sales.apply,
                sales.applyAmount,
                sales.stopProcessing,
                sales.ignorePrevious,
                sales.allGroups,
                sales.allPurchasables,
                sales.allCategories,
                sales.enabled,
                sp.purchasableId,
                spt.categoryId,
                sug.userGroupId')
                ->from(Table::SALES . ' sales')
                ->leftJoin(Table::SALE_PURCHASABLES . ' sp', '[[sp.saleId]] = [[sales.id]]')
                ->leftJoin(Table::SALE_CATEGORIES . ' spt', '[[spt.saleId]] = [[sales.id]]')
                ->leftJoin(Table::SALE_USERGROUPS . ' sug', '[[sug.saleId]] = [[sales.id]]')
                ->orderBy('sortOrder asc')
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
     * Populates a sale's relations.
     *
     * @param Sale $sale
     */
    public function populateSaleRelations(Sale $sale)
    {
        $rows = (new Query())->select(
            'sp.purchasableId,
            spt.categoryId,
            sug.userGroupId')
            ->from(Table::SALES . ' sales')
            ->leftJoin(Table::SALE_PURCHASABLES . ' sp', '[[sp.saleId]]=[[sales.id]]')
            ->leftJoin(Table::SALE_CATEGORIES . ' spt', '[[spt.saleId]]=[[sales.id]]')
            ->leftJoin(Table::SALE_USERGROUPS . ' sug', '[[sug.saleId]]=[[sales.id]]')
            ->where(['sales.id' => $sale->id])
            ->all();

        $purchasableIds = [];
        $categoryIds = [];
        $userGroupIds = [];

        foreach ($rows as $row) {
            if ($row['purchasableId']) {
                $purchasableIds[] = $row['purchasableId'];
            }

            if ($row['categoryId']) {
                $categoryIds[] = $row['categoryId'];
            }

            if ($row['userGroupId']) {
                $userGroupIds[] = $row['userGroupId'];
            }
        }

        $sale->setPurchasableIds($purchasableIds);
        $sale->setCategoryIds($categoryIds);
        $sale->setUserGroupIds($userGroupIds);
    }

    /**
     * Returns the sales that match the purchasable.
     *
     * @param PurchasableInterface $purchasable
     * @param Order|null $order
     * @return Sales[]
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
        $sales = [];

        if ($purchasable->getId()) {
            foreach ($this->getAllSales() as $sale) {
                // Get related by product specifically
                $purchasableIds = $sale->getPurchasableIds();
                $id = $purchasable->getId();

                // Get related via category
                $relatedTo = ['sourceElement' => $purchasable->getPromotionRelationSource()];
                $saleCategories = $sale->getCategoryIds();
                $relatedCategories = Category::find()->id($saleCategories)->relatedTo($relatedTo)->ids();

                if (in_array($id, $purchasableIds, false) || !empty($relatedCategories)) {
                    $sales[] = $sale;
                }
            }
        }

        return $sales;
    }

    /**
     * Returns the salePrice of the purchasable based on all the sales.
     *
     * @param PurchasableInterface $purchasable
     * @param Order|null $order
     * @return float
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
        if (null !== $newPrice) {
            $salePrice = $newPrice;
        }

        if ($salePrice < 0) {
            $salePrice = 0;
        }

        return $salePrice;
    }

    /**
     * Match a product and a sale and return the result.
     *
     * @param PurchasableInterface $purchasable
     * @param Sale $sale
     * @param Order $order
     * @return bool
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

        if ($this->_purchasableSaleMatch[$purchasableId][$saleId] !== null) {
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

        // Category match
        if (!$sale->allCategories) {
            $relatedTo = ['sourceElement' => $purchasable->getPromotionRelationSource()];
            $saleCategories = $sale->getCategoryIds();
            $relatedCategories = Category::find()->id($saleCategories)->relatedTo($relatedTo)->ids();

            if (empty($relatedCategories)) {
                return false;
            }
        }

        if ($order) {
            $user = $order->getUser();

            if (!$sale->allGroups) {
                // We must pass a real user to getCurrentUserGroupIds, otherwise the current user is used.
                if (null === $user) {
                    return false;
                }
                // User groups of the order's user
                $userGroups = Plugin::getInstance()->getCustomers()->getUserGroupIdsForUser($user);
                if (!$userGroups || !array_intersect($userGroups, $sale->getUserGroupIds())) {
                    return false;
                }
            }
        }

        // Are we dealing with the current session outside of any cart/order context
        if (!$order && !$sale->allGroups) {
            // User groups of the currently logged in user
            $userGroups = Plugin::getInstance()->getCustomers()->getUserGroupIdsForUser();
            if (!$userGroups || !array_intersect($userGroups, $sale->getUserGroupIds())) {
                return false;
            }
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

        $saleMatchEvent = new SaleMatchEvent(compact('sale', 'purchasable'));

        // Raising the 'beforeMatchPurchasableSale' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_MATCH_PURCHASABLE_SALE)) {
            $this->trigger(self::EVENT_BEFORE_MATCH_PURCHASABLE_SALE, $saleMatchEvent);
        }

        $this->_purchasableSaleMatch[$purchasableId][$saleId] = $saleMatchEvent->isValid;
        return $this->_purchasableSaleMatch[$purchasableId][$saleId];
    }

    /**
     * Save a Sale.
     *
     * @param Sale $model
     * @param bool $runValidation should we validate this before saving.
     * @return bool
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
                throw new Exception(Plugin::t('No sale exists with the ID “{id}”',
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
            'enabled'
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->allGroups = $model->allGroups = empty($model->getUserGroupIds());
        $record->allCategories = $model->allCategories = empty($model->getCategoryIds());
        $record->allPurchasables = $model->allPurchasables = empty($model->getPurchasableIds());

        // Fire an 'beforeSaveSection' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_SALE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_SALE, new SaleEvent([
                'sale' => $model,
                'isNew' => $isNewSale
            ]));
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $model->id = $record->id;

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
                $relation = new SaleCategoryRecord;
                $relation->categoryId = $categoryId;
                $relation->saleId = $model->id;
                $relation->save();
            }

            foreach ($model->getPurchasableIds() as $purchasableId) {
                $relation = new SalePurchasableRecord();
                $relation->purchasableId = $purchasableId;
                $purchasable = Craft::$app->getElements()->getElementById($purchasableId);
                $relation->purchasableType = get_class($purchasable);
                $relation->saleId = $model->id;
                $relation->save();
            }

            $transaction->commit();

            // Fire an 'beforeSaveSection' event
            if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_SALE)) {
                $this->trigger(self::EVENT_AFTER_SAVE_SALE, new SaleEvent([
                    'sale' => $model,
                    'isNew' => $isNewSale
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
     * @param $ids
     * @return bool
     */
    public function reorderSales($ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()
                ->update(Table::SALES, ['sortOrder' => $sortOrder + 1], ['id' => $id])
                ->execute();
        }

        return true;
    }

    /**
     * Delete a sale by its id.
     *
     * @param $id
     * @return bool
     * @throws \Exception
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function deleteSaleById($id): bool
    {
        $sale = SaleRecord::findOne($id);

        if ($sale) {
            return $sale->delete();
        }

        return false;
    }

    // Private Methods
    // =========================================================================

    /**
     * Get all enabled sales.
     *
     * @return array|Sale[]
     */
    private function _getAllEnabledSales(): array
    {
        if (null === $this->_allActiveSales) {
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
}
