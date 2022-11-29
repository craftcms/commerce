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
use craft\commerce\records\CatalogPricingRule as CatalogPricingRuleRecord;
use craft\commerce\records\CatalogPricingRuleUser;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;
use function get_class;

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
     * @var CatalogPricingRule[]|null
     */
    private ?array $_allCatalogPricingRules = null;

    /**
     * @var CatalogPricingRule[]|null
     */
    private ?array $_allActiveCatalogPricingRules = null;

    /**
     * Get a catalog pricing rule by its ID.
     *
     * @param int $id
     * @return CatalogPricingRule|null
     * @throws InvalidConfigException
     */
    public function getCatalogPricingRuleById(int $id): ?CatalogPricingRule
    {
        return ArrayHelper::firstWhere($this->getAllCatalogPricingRules(), 'id', $id);
    }

    /**
     * Get all catalog pricing rules.
     *
     * @return Collection<CatalogPricingRule>
     */
    public function getAllCatalogPricingRules(): Collection
    {
        if (!isset($this->_allCatalogPricingRules)) {
            $catalogPricingRules = $this->_createCatalogPricingRuleQuery()->all();

            $this->_allCatalogPricingRules = collect($catalogPricingRules)->map(function($row) {
                $row['customerCondition'] = $row['customerCondition'] ?? '';
                $row['purchasableCondition'] = $row['purchasableCondition'] ?? '';

                return Craft::createObject(CatalogPricingRule::class, ['config' => ['attributes' => $pcr]]);
            })->keyBy('id');
        }

        return $this->_allCatalogPricingRules;
    }

    /**
     * @return Collection<CatalogPricingRule>
     * @throws InvalidConfigException
     */
    public function getAllEnabledCatalogPricingRules(): Collection
    {
        return $this->getAllCatalogPricingRules()->map(fn(CatalogPricingRule $pcr) => $pcr->enabled);
    }

    /**
     * @return CatalogPricingRule[]
     */
    public function getAllActiveCatalogPricingRules(): array
    {
        if ($this->_allActiveCatalogPricingRules === null) {
            $this->_allActiveCatalogPricingRules = $this->getAllEnabledCatalogPricingRules()->where(function(CatalogPricingRule $pcr) {
                // If there are no dates or rule is currently in the date range add it to the active list
                return (($pcr->dateFrom === null || $pcr->dateFrom->getTimestamp() <= time()) && ($pcr->dateTo === null || $pcr->dateTo->getTimestamp() >= time()));
            });
        }

        return $this->_allActiveCatalogPricingRules ?? [];
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    public function getAllCatalogPricingRulesWithUserConditions(): array
    {
        return $this->getAllCatalogPricingRules()->where(function(CatalogPricingRule $pcr) {
            return $pcr->getCustomerCondition() && !empty($pcr->getCustomerCondition()->rules());
        });
    }

    /**
     * Save a Catalog Pricing Rule.
     *
     * @param bool $runValidation should we validate this before saving.
     * @throws Exception
     * @throws \Exception
     */
    public function saveCatalogPricingRule(CatalogPricingRule $model, bool $runValidation = true): bool
    {
        $isNew = !$model->id;

        if ($isNew) {
            $record = new CatalogPricingRuleRecord();
        } else {
            $record = CatalogPricingRuleRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No catalog pricing rule exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Catalog pricing rule not saved due to validation error.', __METHOD__);

            return false;
        }

        $fields = [
            'apply',
            'applyAmount',
            'dateFrom',
            'dateTo',
            'description',
            'enabled',
            'isPromotionalPrice',
            'name',
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->customerCondition = $model->getCustomerCondition()->getConfig();
        $record->purchasableCondition = $model->getPurchasableCondition()->getConfig();

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $model->id = $record->id;

            CatalogPricingRuleUser::deleteAll(['catalogPricingRuleId' => $model->id]);

            // Batch insert user relationships in case we are dealing with a large number
            $userIds = $model->getUserIds() ?? [];
            foreach (array_chunk($userIds, 1000) as $userIdsChunk) {
                $userRecords = [];
                foreach ($userIdsChunk as $userId) {
                    $userRecords[] = [$model->id, $userId];
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
                'id',
                'name',
                'description',
                'dateFrom',
                'dateTo',
                'apply',
                'applyAmount',
                'customerCondition',
                'purchasableCondition',
                'enabled',
                'isPromotionalPrice',
                'dateCreated',
                'dateUpdated',
            ])
            ->from(Table::CATALOG_PRICING_RULES);
    }

    /**
     * Clear memoization caches
     */
    private function _clearCaches(): void
    {
        $this->_allActiveCatalogPricingRules = null;
        $this->_allCatalogPricingRules = null;
    }
}
