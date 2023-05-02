<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\db\Table;
use craft\commerce\elements\conditions\purchasables\CatalogPricingCondition;
use craft\commerce\helpers\Purchasable;
use craft\commerce\models\CatalogPricing;
use craft\commerce\Plugin;
use craft\commerce\web\assets\catalogpricing\CatalogPricingAsset;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\errors\SiteNotFoundException;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\Site;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\base\InvalidConfigException;

/**
 * Class Catalog Pricing Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CatalogPricingController extends BaseStoreSettingsController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('commerce-managePromotions');

        return true;
    }

    public function actionIndex(?string $storeHandle = null): Response
    {
        $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        if ($storeHandle && !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            throw new NotFoundHttpException('Store not found');
        }

        $allPrices = true;
        $catalogPricingQuery = Plugin::getInstance()->getCatalogPricing()->createCatalogPricingQuery(storeId: $store->id, allPrices: $allPrices)
            ->select([
                'id', 'price', 'purchasableId', 'storeId', 'isPromotionalPrice', 'catalogPricingRuleId', 'dateFrom', 'dateTo', 'uid'
            ]);

        // Temp limit
        $catalogPricingQuery->limit(100);
        $results = $catalogPricingQuery->all();

        $catalogPrices = [];
        foreach ($results as $result) {
            $catalogPrices[] = Craft::createObject([
                'class' => CatalogPricing::class,
                'attributes' => $result,
            ]);
        }

        Craft::$app->getView()->registerAssetBundle(CatalogPricingAsset::class);

        $conditionBuilder = Craft::$app->getConditions()->createCondition([
            'class' => CatalogPricingCondition::class,
        ]);


        return $this->renderTemplate('commerce/store-settings/catalog-pricing/_index', [
            'catalogPrices' => $catalogPrices,
            'condition' => $conditionBuilder->getConfig(),
        ]);
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionFilter(): Response
    {
        $condition = $this->request->getBodyParam('condition');
        $conditionBuilder = Craft::$app->getConditions()->createCondition([
            'class' => CatalogPricingCondition::class,
        ]);
        $conditionBuilderHtml = $conditionBuilder->getBuilderHtml();

        $view = Craft::$app->getView();

        return $this->asSuccess('Filtering', [
            'hudHtml' => $conditionBuilderHtml,
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
        ]);
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionPrices(): Response
    {
        $siteId = $this->request->getRequiredBodyParam('siteId');
        $condition = $this->request->getBodyParam('condition');
        $searchText = $this->request->getBodyParam('searchText');

        parse_str($condition, $condition);
        $conditionBuilder = null;
        if ($condition && isset($condition['condition'])) {
            /** @var CatalogPricingCondition $conditionBuilder */
            $conditionBuilder = Craft::$app->getConditions()->createCondition($condition['condition']);
        }


        /** @var Site|null|StoreBehavior $site */
        if (!$site = Craft::$app->getSites()->getSiteById($siteId)) {
            throw new InvalidArgumentException('Invalid site ID: ' . $siteId);
        }

        // @TODO change this depending on condition rules
        $allPrices = true;

        $query = Plugin::getInstance()->getCatalogPricing()->createCatalogPricingQuery(storeId: $site->getStore()->id, allPrices: $allPrices)
            ->select([
                'price', 'purchasableId', 'storeId', 'isPromotionalPrice', 'catalogPricingRuleId', 'dateFrom', 'dateTo', 'cp.uid'
            ]);

        if ($searchText) {
            $query->innerJoin(Table::PURCHASABLES . ' purchasables', 'cp.purchasableId = purchasables.id');
            $likeOperator = Craft::$app->getDb()->getIsPgsql() ? 'ilike' : 'like';
            $query->andWhere([$likeOperator, 'purchasables.description', $searchText]);
        }

        // If there is a condition builder, modify the query
        $conditionBuilder?->modifyQuery($query);

        // @TODO pagination/limit
        $query->limit(100);
        $results = $query->all();
        $catalogPrices = [];
        foreach ($results as $result) {
            $catalogPrices[] = Craft::createObject([
                'class' => CatalogPricing::class,
                'attributes' => $result,
            ]);
        }

        $view = Craft::$app->getView();
        // $conditionBuilderHtml = $conditionBuilder->getBuilderHtml();
        $tableHtml = $view->renderTemplate('commerce/store-settings/catalog-pricing/_table', compact('catalogPrices'));

        return $this->asJson([
            // 'hudHtml' => $conditionBuilderHtml,
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
            'tableHtml' => $tableHtml,
        ]);
    }

    /**
     * @return string|null
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    public function actionGetCatalogPrices(): ?string
    {
        $purchasableId = $this->request->getBodyParam('purchasableId');
        $storeId = $this->request->getBodyParam('storeId');

        if ($purchasableId === null) {
            return Html::tag('div', Craft::t('commerce', 'Purchasable ID is required.'), ['class' => 'error']);
        }

        if ($storeId === null) {
            return Html::tag('div', Craft::t('commerce', 'Purchasable ID is required.'), ['class' => 'error']);
        }

        $isPriceRecalculation = array_key_exists('basePrice', $this->request->getBodyParams()) || array_key_exists('basePromotionalPrice', $this->request->getBodyParams());

        if (!$isPriceRecalculation) {
            // No need to generate prices if we are just getting the standard price list
            return Purchasable::catalogPricingRulesTableByPurchasableId($purchasableId, $storeId);
        }

        $basePrice = $this->request->getBodyParam('basePrice');
        $basePromotionalPrice = $this->request->getBodyParam('basePromotionalPrice');

        $basePrice = $basePrice ? (float)$basePrice : null;
        $basePromotionalPrice = $basePromotionalPrice ? (float)$basePromotionalPrice : null;

        $allPurchasableRules = Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);
        $catalogPricing = Plugin::getInstance()->getCatalogPricing()->getCatalogPricesByPurchasableId($purchasableId);

        $catalogPricing->each(function(CatalogPricing $cp) use ($basePrice, $basePromotionalPrice, $allPurchasableRules) {
            $rule = $allPurchasableRules->firstWhere('id', $cp->catalogPricingRuleId);
            if (!$rule) {
                return;
            }

            $cp->price = Plugin::getInstance()->getCatalogPricingRules()->generateRulePriceFromPrice($basePrice, $basePromotionalPrice, $rule);
        });

        return Purchasable::catalogPricingRulesTableByPurchasableId($purchasableId, $storeId, $catalogPricing);
    }
}
