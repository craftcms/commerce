<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Condition\ConditionBuilder;
use CraftCms\Cms\Http\Requests\TableRequest;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingPurchasableConditionRule;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricing as CatalogPrice;

use function CraftCms\Cms\t;

readonly class CatalogPricingController
{
    public function index(TableRequest $request): CpScreenResponse
    {
        abort_unless(app(CatalogPricingRules::class)->canUseCatalogPricingRules(), 403, 'Unable to use catalog pricing rules while sales exist.');

        $site = $this->site($request);
        $storeId = $site->getStore()->id;
        $condition = $this->condition($request);
        $search = $request->search() ?: null;
        $perPage = max(1, $request->limit());
        $page = max(1, $request->page());
        $offset = ($page - 1) * $perPage;

        $catalogPricing = app(CatalogPricing::class);
        $total = $catalogPricing->getCatalogPricesPageInfo($storeId, $condition, true, $search)['total'];
        $lastPage = max(1, (int)ceil($total / $perPage));

        $url = Url::cpUrl('commerce/catalog-pricing', ['site' => $site->handle]);

        return new CpScreenResponse()
            ->title(t('Prices', category: 'commerce'))
            ->inertiaPage('commerce::prices/Index', [
                'url' => $url,
                'prices' => fn() => $this->rows($catalogPricing->getCatalogPrices($storeId, $condition, true, $search, $perPage, $offset)->all()),
                'pagination' => fn() => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'next_page_url' => null,
                    'prev_page_url' => null,
                    'from' => $total ? $offset + 1 : null,
                    'to' => $total ? min($offset + $perPage, $total) : null,
                ],
                'searchTerm' => $search,
                'condition' => $condition->getConfig(),
                'conditionBuilder' => fn() => app(ConditionBuilder::class)->resolve($condition),
                'conditionRuleCount' => count($condition->getConditionRules()->getRules()),
                'jobsRunning' => fn() => $catalogPricing->areCatalogPricingJobsRunning(),
                'sites' => Sites::getEditableSites()->map(fn(Site $editableSite) => [
                    'label' => t($editableSite->getName(), category: 'site'),
                    'href' => Url::cpUrl('commerce/catalog-pricing', ['site' => $editableSite->handle]),
                    'selected' => $editableSite->id === $site->id,
                ])->values()->all(),
            ]);
    }

    private function site(TableRequest $request): Site
    {
        $siteHandle = $request->query('site');
        $site = is_string($siteHandle) ? Sites::getSiteByHandle($siteHandle) : Sites::getPrimarySite();
        abort_if($site === null, 404, 'Site not found');

        return $site;
    }

    /**
     * The price filter, from the `condition` query param, optionally narrowed to a `purchasableId`.
     */
    private function condition(TableRequest $request): CatalogPricingCondition
    {
        $config = $request->query('condition');
        $config = is_string($config) && $config !== '' ? Json::decodeIfJson($config) : null;

        /** @var CatalogPricingCondition $condition */
        $condition = Conditions::createCondition([
            ...(is_array($config) ? $config : []),
            'class' => CatalogPricingCondition::class,
            'allPrices' => true,
        ]);

        $purchasableId = $request->integer('purchasableId') ?: null;

        if ($purchasableId && $purchasableElementType = Elements::getElementTypeById($purchasableId)) {
            $condition->addConditionRule(Conditions::createConditionRule([
                'class' => CatalogPricingPurchasableConditionRule::class,
                'elementIds' => [$purchasableElementType => [$purchasableId]],
            ]));
        }

        return $condition;
    }

    /**
     * Each purchasable's details are only included on its first price, so the table groups prices by purchasable.
     *
     * @param CatalogPrice[] $prices
     * @return list<array<string, mixed>>
     */
    private function rows(array $prices): array
    {
        $rows = [];
        $lastPurchasableId = null;

        foreach ($prices as $price) {
            $purchasable = $price->purchasableId !== $lastPurchasableId ? $price->getPurchasable() : null;
            $rule = $price->getCatalogPricingRule();

            $rows[] = [
                'id' => $price->uid ?? implode('-', [$price->purchasableId, $price->catalogPricingRuleId, (int)$price->isPromotionalPrice]),
                'purchasable' => $purchasable ? [
                    'title' => (string)$purchasable->title,
                    'url' => $purchasable->getCpEditUrl(),
                    'status' => $purchasable->getStatus(),
                ] : null,
                'rule' => $rule ? ['name' => $rule->name, 'url' => $rule->getCpEditUrl()] : null,
                'source' => $rule ? null : ($price->isPromotionalPrice ? t('Base Promotional Price', category: 'commerce') : t('Base Price', category: 'commerce')),
                'price' => $price->getPriceAsCurrency(),
                'isPromotional' => $price->isPromotionalPrice,
            ];

            $lastPurchasableId = $price->purchasableId;
        }

        return $rows;
    }
}
