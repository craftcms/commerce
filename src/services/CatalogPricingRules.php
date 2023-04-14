<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craft\commerce\queue\jobs\CatalogPricing;
use craft\commerce\records\CatalogPricingRule as CatalogPricingRuleRecord;
use craft\commerce\records\CatalogPricingRuleUser;
use craft\db\Query;
use craft\elements\User;
use craft\errors\SiteNotFoundException;
use craft\events\ModelEvent;
use craft\events\UserGroupsAssignEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Queue;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Catalog Pricing Rules service.
 *
 * @property-read CatalogPricingRule[] $allActiveCatalogPricingRules
 * @property-read CatalogPricingRule[] $allCatalogPricingRules
 * @property-read CatalogPricingRule[]|null $allEnabledCatalogPricingRules
 * @property CatalogPricingRule[] $allPricingCatalogRules
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingRules extends Component
{
    /**
     * @var Collection[]|null
     */
    private ?array $_allCatalogPricingRules = null;

    /**
     * Get a catalog pricing rule by its ID.
     *
     * @param int $id
     * @param int|null $storeId
     * @return CatalogPricingRule|null
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function getCatalogPricingRuleById(int $id, ?int $storeId = null): ?CatalogPricingRule
    {
        return $this->getAllCatalogPricingRules($storeId, false)->firstWhere('id', $id);
    }

    /**
     * Get all catalog pricing rules.
     *
     * @param int|null $storeId
     * @param bool $storeOnly If true, only return rules that are store specific (not those created on purchasables)
     * @return Collection<CatalogPricingRule>
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function getAllCatalogPricingRules(?int $storeId = null, bool $storeOnly = true): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;
        $key = $storeId . '-' . (int)$storeOnly;

        if ($this->_allCatalogPricingRules === null || !isset($this->_allCatalogPricingRules[$key])) {
            $query = $this->_createCatalogPricingRuleQuery()
                ->where(['storeId' => $storeId]);

            if ($storeOnly) {
                $query->andWhere(['purchasableId' => null]);
            }

            $results = $query->all();

            if ($this->_allCatalogPricingRules === null) {
                $this->_allCatalogPricingRules = [];
            }

            $models = $this->_createCatalogPricingRuleModels($results);
            $this->_allCatalogPricingRules[$key] = collect($models);
        }

        return $this->_allCatalogPricingRules[$key] ?? collect();
    }

    /**
     * @param int $purchasableId
     * @param int|null $storeId
     * @return Collection
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     */
    public function getAllCatalogPricingRulesByPurchasableId(int $purchasableId, ?int $storeId = null): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;
        // @TODO figure out if memoization is needed here
        $catalogPricingRules = $this->_createCatalogPricingRuleQuery()
            ->andWhere([
                'or',
                ['purchasableId' => $purchasableId],
                ['id' => (new Query())
                    ->select(['catalogPricingRuleId'])
                    ->from([Table::CATALOG_PRICING])
                    ->where(['purchasableId' => $purchasableId])
                ]
            ])
            ->andWhere(['storeId' => $storeId])
            ->all();

        return $this->_createCatalogPricingRuleModels($catalogPricingRules);
    }

    /**
     * @param int|null $storeId
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getAllEnabledCatalogPricingRules(?int $storeId = null): Collection
    {
        return $this->getAllCatalogPricingRules($storeId)->where(fn(CatalogPricingRule $pcr) => $pcr->enabled);
    }

    /**
     * @param int|null $storeId
     * @return Collection<CatalogPricingRule>
     * @throws InvalidConfigException
     */
    public function getAllActiveCatalogPricingRules(?int $storeId = null): Collection
    {
        return $this->getAllEnabledCatalogPricingRules($storeId)->where(function(CatalogPricingRule $pcr) {
            // If there are no dates or rule is currently in the date range add it to the active list
            return (($pcr->dateFrom === null || $pcr->dateFrom->getTimestamp() <= time()) && ($pcr->dateTo === null || $pcr->dateTo->getTimestamp() >= time()));
        });
    }

    /**
     * @param int|null $storeId
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getAllCatalogPricingRulesWithUserConditions(?int $storeId = null): Collection
    {
        return $this->getAllCatalogPricingRules($storeId)->where(function(CatalogPricingRule $pcr) {
            return !empty($pcr->getCustomerCondition()->getConditionRules());
        });
    }

    /**
     * @param ModelEvent $event
     * @return void
     * @throws InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function afterSaveUserHandler(ModelEvent|UserGroupsAssignEvent $event): void
    {
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        foreach ($stores as $store) {
            $rules = $this->getAllCatalogPricingRulesWithUserConditions($store->id);
            if ($rules->isEmpty()) {
                continue;
            }

            /** @var User $user */
            $user = $event instanceof ModelEvent ? $event->sender : Craft::$app->getUsers()->getUserById($event->userId);
            $rules->each(function(CatalogPricingRule $rule) use ($user) {
                $customerCondition = $rule->getCustomerCondition();
                if ($customerCondition->matchElement($user)) {
                    if (!CatalogPricingRuleUser::find()->where(['userId' => $user->id, 'catalogPricingRuleId' => $rule->id])->exists()) {
                        Craft::$app->getDb()->createCommand()
                            ->insert(Table::CATALOG_PRICING_RULES_USERS, ['userId' => $user->id, 'catalogPricingRuleId' => $rule->id])
                            ->execute();
                    }
                } else {
                    CatalogPricingRuleUser::deleteAll(['userId' => $user->id, 'catalogPricingRuleId' => $rule->id]);
                }
            });

        }
    }

    /**
     * Save a Catalog Pricing Rule.
     *
     * @param bool $runValidation should we validate this before saving.
     * @throws Exception
     * @throws \Exception
     */
    public function saveCatalogPricingRule(CatalogPricingRule $catalogPricingRule, bool $runValidation = true): bool
    {
        $isNew = !$catalogPricingRule->id;

        if ($isNew) {
            $record = Craft::createObject(CatalogPricingRuleRecord::class);
        } else {
            $record = CatalogPricingRuleRecord::findOne($catalogPricingRule->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No catalog pricing rule exists with the ID “{id}”',
                    ['id' => $catalogPricingRule->id]));
            }
        }

        if ($runValidation && !$catalogPricingRule->validate()) {
            Craft::info('Catalog pricing rule not saved due to validation error.', __METHOD__);

            return false;
        }

        $attributes = [
            'apply',
            'applyAmount',
            'applyPriceType',
            'dateFrom',
            'dateTo',
            'description',
            'enabled',
            'isPromotionalPrice',
            'name',
            'storeId',
            'metadata',
            'purchasableId',
        ];
        foreach ($attributes as $attribute) {
            $record->$attribute = $catalogPricingRule->$attribute;
        }

        $record->customerCondition = $catalogPricingRule->getCustomerCondition()->getConfig();
        $record->purchasableCondition = $catalogPricingRule->getPurchasableCondition()->getConfig();

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $catalogPricingRule->id = $record->id;

            CatalogPricingRuleUser::deleteAll(['catalogPricingRuleId' => $catalogPricingRule->id]);

            // Batch insert user relationships in case we are dealing with a large number
            $userIds = $catalogPricingRule->getUserIds() ?? [];
            foreach (array_chunk($userIds, 1000) as $userIdsChunk) {
                $userRecords = [];
                foreach ($userIdsChunk as $userId) {
                    $userRecords[] = [$catalogPricingRule->id, $userId];
                }
                $db->createCommand()
                    ->batchInsert(
                        Table::CATALOG_PRICING_RULES_USERS,
                        ['catalogPricingRuleId', 'userId'],
                        $userRecords
                    )
                    ->execute();
            }

            $transaction->commit();

            Queue::push(Craft::createObject([
                'class' => CatalogPricing::class,
                'catalogPricingRuleIds' => [$catalogPricingRule->id],
                'storeId' => $catalogPricingRule->storeId,
            ]), 100);

            $this->_clearCaches();

            return true;
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a catalog pricing rule by its id.
     *
     * @param int $id
     * @return bool
     * @throws StaleObjectException
     * @throws \Throwable
     */
    public function deleteCatalogPricingRuleById(int $id): bool
    {
        $record = CatalogPricingRuleRecord::findOne($id);

        if (!$record) {
            return false;
        }

        $this->_clearCaches();
        return (bool)$record->delete();
    }

    protected function _createCatalogPricingRuleQuery(): ?Query
    {
        return (new Query())
            ->select([
                'apply',
                'applyAmount',
                'applyPriceType',
                'customerCondition',
                'dateCreated',
                'dateFrom',
                'dateTo',
                'dateUpdated',
                'description',
                'enabled',
                'id',
                'isPromotionalPrice',
                'name',
                'purchasableCondition',
                'purchasableId',
                'storeId',
            ])
            ->from(Table::CATALOG_PRICING_RULES);
    }

    /**
     * Clear memoization caches
     */
    protected function _clearCaches(): void
    {
        $this->_allCatalogPricingRules = null;
    }

    /**
     * Takes the results array from a `_createCatalogPricingRuleQuery()` call and creates a collection of CatalogPricingRule models.
     *
     * @param array $rows
     * @return Collection
     */
    protected function _createCatalogPricingRuleModels(array $rows): Collection
    {
        return collect($rows)->map(function($row) {
            $row['customerCondition'] = $row['customerCondition'] ?? '';
            $row['purchasableCondition'] = $row['purchasableCondition'] ?? '';

            return Craft::createObject(CatalogPricingRule::class, ['config' => ['attributes' => $row]]);
        })->keyBy('id');
    }
}
