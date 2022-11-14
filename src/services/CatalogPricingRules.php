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
use craft\commerce\records\CatalogPricingRulePurchasable;
use craft\commerce\records\CatalogPricingRuleUserGrup;
use craft\db\Query;
use craft\helpers\ArrayHelper;
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
     * @return CatalogPricingRule[]
     * @throws InvalidConfigException
     */
    public function getAllCatalogPricingRules(): array
    {
        if (!isset($this->_allCatalogPricingRules)) {
            $catalogPricingRules = $this->_createCatalogPricingRuleQuery()->all();

            $allPricingCatalogRulesById = [];
            $purchasables = [];
            $groups = [];

            foreach ($catalogPricingRules as $pcr) {
                $id = $pcr['id'];
                if ($pcr['purchasableId']) {
                    $purchasables[$id][] = $pcr['purchasableId'];
                }

                if ($pcr['userGroupId']) {
                    $groups[$id][] = $pcr['userGroupId'];
                }

                unset($pcr['purchasableId'], $pcr['userGroupId']);

                if (!isset($allPricingCatalogRulesById[$id])) {
                    $allPricingCatalogRulesById[$id] = Craft::createObject(CatalogPricingRule::class, ['config' => ['attributes' => $pcr]]);
                }
            }

            foreach ($allPricingCatalogRulesById as $id => $pcr) {
                $pcr->setPurchasableIds($purchasables[$id] ?? []);
                $pcr->setUserGroupIds($groups[$id] ?? []);
            }

            $this->_allCatalogPricingRules = $allPricingCatalogRulesById;
        }

        return $this->_allCatalogPricingRules;
    }

    /**
     * @return CatalogPricingRule[]|null
     * @throws InvalidConfigException
     */
    public function getAllEnabledCatalogPricingRules(): ?array
    {
        return array_filter($this->getAllCatalogPricingRules(), fn(CatalogPricingRule $pcr) => $pcr->enabled);
    }

    /**
     * @return CatalogPricingRule[]
     */
    public function getAllActiveCatalogPricingRules(): array
    {
        if ($this->_allActiveCatalogPricingRules === null) {
            $this->_allActiveCatalogPricingRules = [];
            foreach ($this->getAllEnabledCatalogPricingRules() as $pcr) {
                // If there are no dates or rule is currently in the date range add it to the active list
                if (($pcr->dateFrom === null || $pcr->dateFrom->getTimestamp() <= time()) && ($pcr->dateTo === null || $pcr->dateTo->getTimestamp() >= time())) {
                    $this->_allActiveCatalogPricingRules[] = $pcr;
                }
            }
        }

        return $this->_allActiveCatalogPricingRules ?? [];
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
            'name',
            'description',
            'dateFrom',
            'dateTo',
            'apply',
            'applyAmount',
            'enabled',
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        if ($record->allGroups = $model->allGroups) {
            $model->setUserGroupIds([]);
        }
        if ($record->allPurchasables = $model->allPurchasables) {
            $model->setPurchasableIds([]);
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $record->save(false);
            $model->id = $record->id;

            CatalogPricingRuleUserGrup::deleteAll(['catalogPricingRuleId' => $model->id]);
            CatalogPricingRulePurchasable::deleteAll(['catalogPricingRuleId' => $model->id]);

            foreach ($model->getUserGroupIds() as $groupId) {
                $relation = new CatalogPricingRuleUserGrup();
                $relation->userGroupId = $groupId;
                $relation->catalogPricingRuleId = $model->id;
                $relation->save();
            }

            foreach ($model->getPurchasableIds() as $purchasableId) {
                $relation = new CatalogPricingRulePurchasable();
                $relation->purchasableId = $purchasableId;
                $purchasable = Craft::$app->getElements()->getElementById($purchasableId, null, null, ['trashed' => null]);
                $relation->purchasableType = get_class($purchasable);
                $relation->catalogPricingRuleId = $model->id;
                $relation->save();

                Craft::$app->getElements()->invalidateCachesForElement($purchasable);
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
                'pcr.id',
                'pcr.name',
                'pcr.description',
                'pcr.dateFrom',
                'pcr.dateTo',
                'pcr.apply',
                'pcr.applyAmount',
                'pcr.allGroups',
                'pcr.allPurchasables',
                'pcr.enabled',
                'pcr.dateCreated',
                'pcr.dateUpdated',
                'pcrp.purchasableId',
                'pcru.userGroupId',
            ])
            ->from(Table::CATALOG_PRICING_RULES . ' pcr')
            ->leftJoin(Table::CATALOG_PRICING_RULES_PURCHASABLES . ' pcrp', '[[pcrp.catalogPricingRuleId]] = [[pcr.id]]')
            ->leftJoin(Table::CATALOG_PRICING_RULES_USERS . ' pcru', '[[pcru.catalogPricingRuleId]] = [[pcr.id]]');
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
