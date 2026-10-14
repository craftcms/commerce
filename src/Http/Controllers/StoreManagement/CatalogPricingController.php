<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use craft\commerce\web\assets\catalogpricing\CatalogPricingAsset;
use craft\helpers\Cp;
use craft\web\assets\htmx\HtmxAsset;
use CraftCms\Cms\Condition\ConditionBuilderRenderer;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingPurchasableConditionRule;
use CraftCms\Commerce\CatalogPricing\PurchasableCatalogPrices;
use CraftCms\Commerce\Purchasable\Elements\Purchasable as PurchasableElement;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Ui\Nodes\CatalogPrices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

use function CraftCms\Cms\pageTemplate;
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
        // Condition classes no longer self-render; ConditionBuilderRenderer replaces the old getBuilderHtml()/builderHtml().
        $conditionBuilderHtml = new ConditionBuilderRenderer($conditionBuilder)->render();

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

    public function purchasablePrices(Request $request): JsonResponse
    {
        $this->guard();

        $purchasable = Elements::getElementById($request->integer('purchasableId'));
        abort_unless($purchasable instanceof PurchasableElement, 404);
        abort_unless(Gate::allows('view', $purchasable), 403);

        $store = app(Stores::class)->getStoreById($request->integer('storeId'));
        abort_if($store === null, 404);

        $data = app(PurchasableCatalogPrices::class)->data($purchasable->id, $store);

        return response()->json([...$data, 'rows' => CatalogPrices::tableRows($data['rows'], $purchasable->id)]);
    }
}
