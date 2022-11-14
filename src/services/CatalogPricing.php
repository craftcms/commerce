<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\records\UserGroup_User;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Catalog Pricing service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricing extends Component
{
    /**
     * @param array|null $purchasables
     * @param array|null $catalogPricingRules
     * @param bool $showConsoleOutput
     * @return void
     * @throws InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function generateCatalogPrices(?array $purchasables = null, ?array $catalogPricingRules = null, bool $showConsoleOutput = false): void
    {
        /** @var UserGroup_User[]|null $allUsersAndGroups */
        $allUsersAndGroups = UserGroup_User::find()->all();
        $usersByUserGroupId = [];
        foreach ($allUsersAndGroups as $row) {
            if (!isset($usersByUserGroupId[$row->groupId])) {
                $usersByUserGroupId[$row->groupId] = [];
            }

            $usersByUserGroupId[$row->groupId][] = $row->userId;
        }

        if ($purchasables === null) {
            $purchasableElementTypes = Plugin::getInstance()->getPurchasables()->getAllPurchasableElementTypes();
            if (empty($purchasableElementTypes)) {
                return;
            }

            $purchasables = [];
            foreach ($purchasableElementTypes as $purchasableElementType) {
                $query = Craft::$app->getElements()->createElementQuery($purchasableElementType);

                $foundPurchasables = $query->all();
                if (empty($foundPurchasables)) {
                    continue;
                }

                $purchasables = [...$purchasables, ...$foundPurchasables];
            }
        }

        if (empty($purchasables)) {
            return;
        }

        // Generate all standard catalog pricing rules
        $catalogPricing = [];
        $priceByPurchasableId = [];
        foreach ($purchasables as $purchasable) {
            /** @var Purchasable $purchasable */
            $id = $purchasable->getId();
            $price = $purchasable->getPrice();
            $catalogPricing[] = [
                $id,
                $price,
                1,
                null,
                0,
                null,
                null,
                null,
            ];
            $priceByPurchasableId[$id] = $price;
        }

        $catalogPricingRules = $catalogPricingRules ?? Plugin::getInstance()->getCatalogPricingRules()->getAllActiveCatalogPricingRules();

        foreach ($catalogPricingRules as $catalogPricingRule) {
            $purchasableIds = $catalogPricingRule->allPurchasables ? ArrayHelper::getColumn($purchasables, 'id') : [];
            if (!$catalogPricingRule->allPurchasables && !empty($catalogPricingRule->getPurchasableIds())) {
                $purchasableIds = array_intersect($catalogPricingRule->getPurchasableIds(), ArrayHelper::getColumn($purchasables, 'id'));
            }

            if (empty($purchasableIds)) {
                continue;
            }


            $userIds = [null];
            if (!$catalogPricingRule->allGroups) {
                $userIds = [];
                foreach ($catalogPricingRule->getUserGroupIds() as $userGroupId) {
                    if (isset($usersByUserGroupId[$userGroupId])) {
                        $userIds = [...$userIds, ...$usersByUserGroupId[$userGroupId]];
                    }
                }
                $userIds = array_unique($userIds);
            }

            foreach ($userIds as $userId) {
                foreach ($purchasableIds as $purchasableId) {
                    $catalogPricing[] = [
                        $purchasableId,
                        $catalogPricingRule->getRulePriceFromPrice($priceByPurchasableId[$purchasableId]),
                        1,
                        $userId,
                        1,
                        $catalogPricingRule->id,
                        $catalogPricingRule->dateFrom ? Db::prepareDateForDb($catalogPricingRule->dateFrom) : null,
                        $catalogPricingRule->dateTo ? Db::prepareDateForDb($catalogPricingRule->dateTo) : null,
                    ];
                }
            }
        }

        // Truncate the catalog pricing table
        Craft::$app->getDb()->createCommand()->delete(Table::CATALOG_PRICING, ['not', ['id' => null]])->execute();

        if (!empty($catalogPricing)) {
            // Bath through `$catalogPricing` and insert into the catalog pricing table
            $count = 1;
            foreach (array_chunk($catalogPricing, 2000) as $catalogPricingChunk) {
                if ($showConsoleOutput) {
                    $this->stdout('Generating prices for ' . $count . ' - ' . (($count + 1999) > count($catalogPricing)) ? count($catalogPricing) : ($count + 1999) . ' of ' . count($catalogPricing) . '... ');
                }
                Craft::$app->getDb()->createCommand()->batchInsert(Table::CATALOG_PRICING, [
                    'purchasableId',
                    'price',
                    'storeId',
                    'userId',
                    'salePrice',
                    'catalogPricingRuleId',
                    'dateFrom',
                    'dateTo',
                ], $catalogPricingChunk)->execute();
                $count += 2000;
            }
        }
    }
}