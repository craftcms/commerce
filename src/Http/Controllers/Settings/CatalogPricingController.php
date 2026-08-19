<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\helpers\Purchasable;
use craft\commerce\web\assets\catalogpricing\CatalogPricingAsset;
use craft\helpers\Cp;
use craft\web\assets\htmx\HtmxAsset;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Catalog\Models\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingPurchasableConditionRule;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

readonly class CatalogPricingController
{
    private function guard(): void
    {
        abort_unless(app(CatalogPricingRules::class)->canUseCatalogPricingRules(), 403, 'Unable to use catalog pricing rules while sales exist.');
    }

    public function index(Request $request): string
    {
        $this->guard();

        $siteHandle = $request->query('site');
        $site = $siteHandle === null ? Sites::getPrimarySite() : Sites::getSiteByHandle($siteHandle);
        abort_if($site === null, 404, 'Site not found');

        /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
        $store = $site->getStore();

        $purchasableId = $request->query('purchasableId') ? (int)$request->query('purchasableId') : null;
        /** @var CatalogPricingCondition $conditionBuilder */
        $conditionBuilder = Conditions::createCondition([
            'class' => CatalogPricingCondition::class,
            'allPrices' => true,
        ]);

        if ($purchasableId && $purchasableElementType = Elements::getElementTypeById($purchasableId)) {
            $purchasableConditionRule = Conditions::createConditionRule([
                'class' => CatalogPricingPurchasableConditionRule::class,
                'elementIds' => [$purchasableElementType => [$purchasableId]],
            ]);

            $conditionBuilder->addConditionRule($purchasableConditionRule);
        }

        $catalogPrices = app(\CraftCms\Commerce\CatalogPricing\CatalogPricing::class)->getCatalogPrices($store->id, $conditionBuilder, limit: 100, offset: 0);
        $pageInfo = app(\CraftCms\Commerce\CatalogPricing\CatalogPricing::class)->getCatalogPricesPageInfo($store->id, $conditionBuilder);

        \Craft::$app->getView()->registerAssetBundle(HtmxAsset::class);
        \Craft::$app->getView()->registerAssetBundle(CatalogPricingAsset::class);

        return pageTemplate('commerce/prices/_index', [
            'catalogPrices' => $catalogPrices->all(),
            'pageInfo' => $pageInfo,
            'condition' => $conditionBuilder,
            'areCatalogPricingJobsRunning' => app(\CraftCms\Commerce\CatalogPricing\CatalogPricing::class)->areCatalogPricingJobsRunning(),
        ], TemplateMode::Cp);
    }

    public function filter(Request $request): JsonResponse
    {
        $this->guard();

        $condition = $request->input('condition') ?? ['class' => CatalogPricingCondition::class];
        $conditionBuilder = Conditions::createCondition($condition);
        $conditionBuilderHtml = $conditionBuilder->getBuilderHtml();

        $view = \Craft::$app->getView();

        return response()->json([
            'condition' => $conditionBuilder->getConfig(),
            'hudHtml' => $conditionBuilderHtml,
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
        ]);
    }

    public function prices(Request $request): JsonResponse
    {
        $this->guard();

        $siteId = $request->input('siteId');
        abort_if($siteId === null, 400, 'siteId is required');
        $siteId = (int)$siteId;
        $condition = $request->input('condition');
        $searchText = $request->input('searchText');
        $limit = $request->input('limit');
        $limit = $limit !== null ? (int)$limit : null;
        $offset = $request->input('offset', 0);
        $offset = $offset !== null ? (int)$offset : null;
        $includeBasePrices = $request->input('includeBasePrices', true);
        $forPurchasable = $request->input('forPurchasable', false);

        $conditionBuilder = null;
        if ($condition && isset($condition['condition'])) {
            /** @var CatalogPricingCondition $conditionBuilder */
            $conditionBuilder = Conditions::createCondition($condition['condition']);
        }

        $site = Sites::getSiteById($siteId);
        abort_if($site === null, 400, 'Invalid site ID: ' . $siteId);

        /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
        $catalogPrices = app(\CraftCms\Commerce\CatalogPricing\CatalogPricing::class)->getCatalogPrices($site->getStore()->id, $conditionBuilder, $includeBasePrices, $searchText, $limit, $offset);
        $catalogPricesPageInfo = null;
        if ($limit !== null && $offset !== null) {
            /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
            $catalogPricesPageInfo = app(\CraftCms\Commerce\CatalogPricing\CatalogPricing::class)->getCatalogPricesPageInfo($site->getStore()->id, $conditionBuilder, $includeBasePrices, $searchText, $limit, $offset);
        }

        $view = \Craft::$app->getView();

        $tableHtml = template('commerce/prices/_table', [
            'catalogPrices' => $catalogPrices->all(),
            'showPurchasable' => !$forPurchasable,
            'removeMargin' => $forPurchasable,
        ]);

        return response()->json([
            'headHtml' => $view->getHeadHtml(),
            'bodyHtml' => $view->getBodyHtml(),
            'tableHtml' => $tableHtml,
            'pageInfo' => $catalogPricesPageInfo,
        ]);
    }

    public function queueStatus(): string
    {
        $this->guard();

        $site = Cp::requestedSite();
        /** @phpstan-ignore-next-line method.notFound (getStore() is added to Site via a Macroable macro registered in Plugin::registerBehaviorMacros(), not visible to static analysis) */
        $storeHandle = $site?->getStore()->handle ?? null;

        return template('commerce/prices/_polling', [
            'areCatalogPricingJobsRunning' => app(\CraftCms\Commerce\CatalogPricing\CatalogPricing::class)->areCatalogPricingJobsRunning(),
            'storeHandle' => $storeHandle,
        ]);
    }

    public function getCatalogPrices(Request $request): ?string
    {
        $this->guard();

        // @TODO Remove this action once the catalog pricing UI refactor lands and no longer needs this endpoint
        $purchasableId = $request->input('purchasableId');
        $storeId = $request->input('storeId');

        if ($purchasableId === null || $storeId === null) {
            return Html::tag('div', t('Purchasable ID is required.', category: 'commerce'), ['class' => 'error']);
        }

        $purchasableId = (int)$purchasableId;
        $storeId = (int)$storeId;

        $isPriceRecalculation = $request->has('basePrice') || $request->has('basePromotionalPrice');

        if (!$isPriceRecalculation) {
            return Purchasable::catalogPricingRulesTableByPurchasableId($purchasableId, $storeId);
        }

        $basePrice = $request->input('basePrice');
        $basePromotionalPrice = $request->input('basePromotionalPrice');

        $basePrice = $basePrice ? (float)$basePrice : null;
        $basePromotionalPrice = $basePromotionalPrice ? (float)$basePromotionalPrice : null;

        $allPurchasableRules = app(CatalogPricingRules::class)->getAllCatalogPricingRulesByPurchasableId($purchasableId, $storeId);
        $catalogPricing = app(\CraftCms\Commerce\CatalogPricing\CatalogPricing::class)->getCatalogPricesByPurchasableId($purchasableId);

        $catalogPricing->each(function(CatalogPricing $cp) use ($basePrice, $basePromotionalPrice, $allPurchasableRules) {
            $rule = $allPurchasableRules->firstWhere('id', $cp->catalogPricingRuleId);
            if (!$rule) {
                return;
            }

            $cp->price = app(CatalogPricingRules::class)->generateRulePriceFromPrice($basePrice, $basePromotionalPrice, $rule);
        });

        return Purchasable::catalogPricingRulesTableByPurchasableId($purchasableId, $storeId, $catalogPricing);
    }
}
