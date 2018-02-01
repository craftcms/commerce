<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\SaleMatchEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Sale;
use craft\commerce\Plugin;
use craft\commerce\records\Sale as SaleRecord;
use craft\commerce\records\SaleCategory as SaleCategoryRecord;
use craft\commerce\records\SalePurchasable as SalePurchasableRecord;
use craft\commerce\records\SaleUserGroup as SaleUserGroupRecord;
use craft\db\Query;
use craft\elements\Category;
use yii\base\Component;
use yii\base\Exception;

/**
 * Sale service.
 *
 * @property Sale[] $allSales
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class Sales extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event SaleMatchEvent This event is raised after a sale has matched all other conditions
     */
    const EVENT_BEFORE_MATCH_PURCHASABLE_SALE = 'beforeMatchPurchasableSale';

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

    // Public Methods
    // =========================================================================

    /**
     * Apply applicable sales to a purchasable
     *
     * @param PurchasableInterface|PurchasableInterface[] Purchasables
     *
     * @return void
     */
    public function applySales($purchasables)
    {
        if (!is_array($purchasables)) {
            $purchasables = [$purchasables];
        }

        // reset the salePrice to be the same as price, and clear any sales applied.
        foreach ($purchasables as $purchasable) {
            $purchasable->setSales([]);
            $purchasable->setSalePrice(Currency::round($purchasable->getPrice()));
        }

        /** @var $purchasable PurchasableInterface */
        // Only bother calculating if the purchasable is persisted and promotable.
        if ($purchasable->getPurchasableId() && $purchasable->getIsPromotable()) {
            $sales = $this->getSalesForPurchasable($purchasable);
            $purchasable->setSales($sales);
            foreach ($sales as $sale) {
                $purchasable->setSalePrice(Currency::round($purchasable->getSalePrice() + $sale->calculateTakeoff($purchasable->getPrice())));

                // Cannot have a sale that makes the price negative.
                if ($purchasable->getSalePrice() < 0) {
                    $purchasable->setSalePrice(0);
                }
            }
        }
    }

    /**
     * @param int $id
     *
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
                sales.discountType,
                sales.discountAmount,
                sales.allGroups,
                sales.allPurchasables,
                sales.allCategories,
                sales.enabled,
                sp.purchasableId,
                spt.categoryId,
                sug.userGroupId')
                ->from('{{%commerce_sales}} sales')
                ->leftJoin('{{%commerce_sale_purchasables}} sp', '[[sp.saleId]] = [[sales.id]]')
                ->leftJoin('{{%commerce_sale_categories}} spt', '[[spt.saleId]] = [[sales.id]]')
                ->leftJoin('{{%commerce_sale_usergroups}} sug', '[[sug.saleId]] = [[sales.id]]')
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
     * Populate a sale's relations.
     *
     * @param Sale $sale
     *
     * @return void
     */
    public function populateSaleRelations(Sale $sale)
    {
        $rows = (new Query())->select(
            'sp.purchasableId,
            spt.categoryId,
            sug.userGroupId')
            ->from('{{%commerce_sales}} sales')
            ->leftJoin('{{%commerce_sale_purchasables}} sp', '[[sp.saleId]]=[[sales.id]]')
            ->leftJoin('{{%commerce_sale_categories}} spt', '[[spt.saleId]]=[[sales.id]]')
            ->leftJoin('{{%commerce_sale_usergroups}} sug', '[[sug.saleId]]=[[sales.id]]')
            ->where(['[[sales.id]]' => $sale->id])
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
     * @param PurchasableInterface $purchasable
     *
     * @return array
     */
    public function getSalesForPurchasable(PurchasableInterface $purchasable): array
    {
        $matchedSales = [];

        foreach ($this->_getAllActiveSales() as $sale) {
            if ($this->matchPurchasableAndSale($purchasable, $sale)) {
                $matchedSales[] = $sale;
            }
        }

        return $matchedSales;
    }


    public function matchPurchasableAndSale(PurchasableInterface $purchasable, Sale $sale): bool
    {
        // can't match something not promotable
        if (!$purchasable->getIsPromotable()) {
            return false;
        }

        // Purchsable ID match
        if (!$sale->allPurchasables && !\in_array($purchasable->getPurchasableId(), $sale->getPurchasableIds(), false)) {
            return false;
        }

        // Category match
        $relatedTo = ['sourceElement' => $purchasable->getPromotionRelationSource()];
        $relatedCategories = Category::find()->relatedTo($relatedTo)->ids();
        $saleCategories = $sale->getCategoryIds();
        $purchasableIsRelateToOneOrMoreCategories = (bool)array_intersect($relatedCategories, $saleCategories);
        if (!$sale->allCategories && !$purchasableIsRelateToOneOrMoreCategories) {
            return false;
        }

        if (!$sale->allGroups) {
            // User Group match
            $userGroups = Plugin::getInstance()->getDiscounts()->getCurrentUserGroupIds();
            if (!$userGroups || !array_intersect($userGroups, $sale->getUserGroupIds())) {
                return false;
            }
        }

        $saleMatchEvent = new SaleMatchEvent(['sale' => $this]);

        // Raising the 'beforeMatchPurchasableSale' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_MATCH_PURCHASABLE_SALE)) {
            $this->trigger(self::EVENT_BEFORE_MATCH_PURCHASABLE_SALE, $saleMatchEvent);
        }

        return $saleMatchEvent->isValid;
    }

    /**
     * @param PurchasableInterface $purchasable
     * @param Order $order
     *
     * @return Sale[]
     */
    public function getSalesForPurchasableInOrder(PurchasableInterface $purchasable, Order $order): array
    {
        $matchedSales = [];
        foreach ($this->_getAllActiveSales() as $sale) {
            if ($this->matchPurchasableAndSale($purchasable, $sale, $order->getCustomer())) {
                $matchedSales[] = $sale;
            }
        }

        return $matchedSales;
    }

    /**
     * @param Sale  $model
     * @param array $groups       ids
     * @param array $categories   ids
     * @param array $purchasables ids
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveSale(Sale $model, array $groups, array $categories, array $purchasables): bool
    {
        if ($model->id) {
            $record = SaleRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No sale exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new SaleRecord();
        }

        $fields = [
            'name',
            'description',
            'dateFrom',
            'dateTo',
            'discountType',
            'discountAmount',
            'enabled'
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->allGroups = $model->allGroups = empty($groups);
        $record->allCategories = $model->allCategories = empty($categories);
        $record->allPurchasables = $model->allPurchasables = empty($purchasables);

        $record->validate();
        $model->addErrors($record->getErrors());

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            if (!$model->hasErrors()) {
                $record->save(false);
                $model->id = $record->id;

                SaleUserGroupRecord::deleteAll(['saleId' => $model->id]);
                SalePurchasableRecord::deleteAll(['saleId' => $model->id]);
                SaleCategoryRecord::deleteAll(['saleId' => $model->id]);

                foreach ($groups as $groupId) {
                    $relation = new SaleUserGroupRecord();
                    $relation->userGroupId = $groupId;
                    $relation->saleId = $model->id;
                    $relation->save();
                }

                foreach ($categories as $categoryId) {
                    $relation = new SaleCategoryRecord;
                    $relation->categoryId = $categoryId;
                    $relation->saleId = $model->id;
                    $relation->save();
                }

                foreach ($purchasables as $purchasableId) {
                    $relation = new SalePurchasableRecord();
                    $relation->purchasableId = $purchasableId;
                    $purchasable = Craft::$app->getElements()->getElementById($purchasableId);
                    $relation->purchasableType = \get_class($purchasable);
                    $relation->saleId = $model->id;
                    $relation->save();
                }

                $transaction->commit();

                return true;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->rollBack();

        return false;
    }

    /**
     * @param $id
     *
     * @return bool
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
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
     * @return array|Sale[]
     */
    private function _getAllActiveSales(): array
    {
        if (null === $this->_allActiveSales) {
            $sales = $this->getAllSales();
            $activeSales = [];
            foreach ($sales as $sale) {
                if ($sale->enabled) {
                    $from = $sale->dateFrom;
                    $to = $sale->dateTo;
                    $now = new \DateTime();
                    if (($from == null || $from < $now) && ($to == null || $to > $now)) {
                        $activeSales[] = $sale;
                    }
                }
            }

            $this->_allActiveSales = $activeSales;
        }

        return $this->_allActiveSales;
    }
}
