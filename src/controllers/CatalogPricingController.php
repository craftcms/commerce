<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\behaviors\StoreBehavior;
use craft\commerce\elements\conditions\purchasables\CatalogPricingCondition;
use craft\commerce\elements\conditions\purchasables\CatalogPricingPurchasableConditionRule;
use craft\commerce\helpers\Purchasable;
use craft\commerce\models\CatalogPricing;
use craft\commerce\Plugin;
use craft\commerce\web\assets\catalogpricing\CatalogPricingAsset;
use craft\errors\SiteNotFoundException;
use craft\helpers\Html;
use craft\models\Site;
use craft\web\assets\htmx\HtmxAsset;
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

    /**
     * @return Response
     * @throws InvalidConfigException
     * @throws NotFoundHttpException
     * @throws SiteNotFoundException
     */
    public function actionIndex(): Response
    {
        $siteHandle = Craft::$app->getRequest()->getQueryParam('site');
        $site = $siteHandle === null ? Craft::$app->getSites()->getPrimarySite() : Craft::$app->getSites()->getSiteByHandle($siteHandle);
        if ($site === null) {
            throw new NotFoundHttpException('Site not found');
        }

        /** @var Site|StoreBehavior $site */
        $store = $site->getStore();

        $purchasableId = Craft::$app->getRequest()->getQueryParam('purchasableId');
        $conditionBuilder = Craft::$app->getConditions()->createCondition([
            'class' => CatalogPricingCondition::class,
            'allPrices' => true,
        ]);

        if ($purchasableId && $purchasableElementType = Craft::$app->getElements()->getElementTypeById($purchasableId)) {
            $purchasableConditionRule = Craft::$app->getConditions()->createConditionRule([
                'class' => CatalogPricingPurchasableConditionRule::class,
                'elementIds' => [$purchasableElementType => [$purchasableId]],
            ]);

            $conditionBuilder->addConditionRule($purchasableConditionRule);
        }

        $catalogPrices = Plugin::getInstance()->getCatalogPricing()->getCatalogPrices($store->id, $conditionBuilder, limit: 100, offset: 0);
        $pageInfo = Plugin::getInstance()->getCatalogPricing()->getCatalogPricesPageInfo($store->id, $conditionBuilder);

        Craft::$app->getView()->registerAssetBundle(HtmxAsset::class);
        Craft::$app->getView()->registerAssetBundle(CatalogPricingAsset::class);

        return $this->renderTemplate('commerce/prices/_index', [
            'catalogPrices' => $catalogPrices->all(),
            'pageInfo' => $pageInfo,
            'condition' => $conditionBuilder,
            'areCatalogPricingJobsRunning' => Plugin::getInstance()->getCatalogPricing()->areCatalogPricingJobsRunning(),
        ]);
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionFilter(): Response
    {
        $condition = $this->request->getBodyParam('condition') ?? ['class' => CatalogPricingCondition::class];
        $conditionBuilder = Craft::$app->getConditions()->createCondition($condition);
        $conditionBuilderHtml = $conditionBuilder->getBuilderHtml();

        $view = Craft::$app->getView();

        return $this->asJson([
            'condition' => $conditionBuilder->getConfig(),
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
        $limit = $this->request->getBodyParam('limit');
        $offset = $this->request->getBodyParam('offset', 0);
        $includeBasePrices = $this->request->getBodyParam('includeBasePrices', true);
        $forPurchasable = $this->request->getBodyParam('forPurchasable', false);
        $isPriceRecalculation = array_key_exists('basePrice', $this->request->getBodyParams()) || array_key_exists('basePromotionalPrice', $this->request->getBodyParams());

        $conditionBuilder = null;
        if ($condition && isset($condition['condition'])) {
            /** @var CatalogPricingCondition $conditionBuilder */
            $conditionBuilder = Craft::$app->getConditions()->createCondition($condition['condition']);
        }

        /** @var Site|null|StoreBehavior $site */
        if (!$site = Craft::$app->getSites()->getSiteById($siteId)) {
            throw new InvalidArgumentException('Invalid site ID: ' . $siteId);
        }

        $catalogPrices = Plugin::getInstance()->getCatalogPricing()->getCatalogPrices($site->getStore()->id, $conditionBuilder, $includeBasePrices, $searchText, $limit, $offset);
        $catalogPricesPageInfo = Plugin::getInstance()->getCatalogPricing()->getCatalogPricesPageInfo($site->getStore()->id, $conditionBuilder, $includeBasePrices, $searchText, $limit, $offset);

        $view = Craft::$app->getView();

        $tableHtml = $view->renderTemplate('commerce/prices/_table', [
            'catalogPrices' => $catalogPrices->all(),
            'showPurchasable' => !$forPurchasable,
            'removeMargin' => $forPurchasable,
        ]);

        return $this->asJson([
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
            'tableHtml' => $tableHtml,
            'pageInfo' => $catalogPricesPageInfo,
        ]);
    }

    public function actionQueueStatus(): Response
    {
        return $this->renderTemplate('commerce/prices/_polling', [
            'areCatalogPricingJobsRunning' => Plugin::getInstance()->getCatalogPricing()->areCatalogPricingJobsRunning(),
        ]);
    }

    /**
     * @return string|null
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    public function actionGetCatalogPrices(): ?string
    {
        // @TODO remove this after taking out after refactor
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
