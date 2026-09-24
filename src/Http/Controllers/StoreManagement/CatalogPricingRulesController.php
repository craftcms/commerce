<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use craft\helpers\Cp;
use craft\helpers\Localization;
use CraftCms\Cms\Condition\ConditionBuilderRenderer;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\UserGroups;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleProductCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleVariantCondition;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingRule as CatalogPricingRuleRecord;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableCondition;
use CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule;
use CraftCms\Commerce\Store\Data\Store;
use DateTime;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class CatalogPricingRulesController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Pricing Rules', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('pricing-rules')];
    }

    private const int RULES_PER_PAGE = 50;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $nodes = [
            Table::make('catalog-pricing-rules')
                ->columns([
                    ['key' => 'name', 'label' => t('Name'), 'sortable' => true],
                    ['key' => 'duration', 'label' => t('Duration', category: 'commerce'), 'sortable' => true],
                    ['key' => 'effect', 'label' => t('Effect', category: 'commerce')],
                    ['key' => 'isPromotionalPrice', 'label' => t('Is Promotional Price?', category: 'commerce'), 'sortable' => true],
                ])
                ->dataUrl(action([self::class, 'tableData'], ['storeHandle' => $store->handle]), self::RULES_PER_PAGE)
                ->emptyMessage(t('No catalog pricing rules exist yet.', category: 'commerce'))
                ->statusFilter()
                ->searchable()
                ->toggleableColumns()
                ->when(
                    currentUserElement()?->can('commerce-createCatalogPricingRules'),
                    fn(Table $table) => $table->createAction(t('New catalog pricing rule', category: 'commerce'), $store->getStoreSettingsUrl('pricing-rules/new')),
                )
                ->when(
                    currentUserElement()?->can('commerce-deleteCatalogPricingRules'),
                    fn(Table $table) => $table->deletable(action([self::class, 'delete']), bulk: true),
                )
                ->when(
                    currentUserElement()?->can('commerce-editCatalogPricingRules'),
                    fn(Table $table) => $table->statusActions($this->statusActions(action([self::class, 'updateStatus']))),
                ),
        ];

        return $this->cpScreenResponse($store)
            ->title(t('Pricing Rules', category: 'commerce'))
            ->crumbs($this->crumbs($store))
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
                'contentMaxWidth' => false,
            ]);
    }

    public function tableData(Request $request): JsonResponse
    {
        abort_unless($request->expectsJson(), 400);

        $store = $this->resolveStore($request->input('storeHandle'));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, $request->integer('per_page', self::RULES_PER_PAGE));
        $search = mb_strtolower(trim((string) $request->input('search', '')));
        $status = (string) $request->input('status', '');

        $rules = app(CatalogPricingRules::class)->getAllCatalogPricingRules($store->id)
            ->when($search !== '', fn(Collection $rules) => $rules->filter(
                fn(CatalogPricingRule $rule) => str_contains(mb_strtolower("{$rule->name} {$rule->description}"), $search),
            ))
            ->when($status !== '', fn(Collection $rules) => $rules->filter(
                fn(CatalogPricingRule $rule) => $rule->enabled === ($status === 'enabled'),
            ))
            ->values();
        $rules = $this->sortedRules($rules, $request->array('sort'));

        $currencyIso = app(PaymentCurrencies::class)->getPrimaryPaymentCurrency($store->id)->iso;
        $dateFormat = I18N::getFormattingLocale()->getDateTimeFormat('short', Locale::FORMAT_PHP);

        $rows = Table::prepareRows(
            $rules
                ->slice(($page - 1) * $perPage, $perPage)
                ->map(fn(CatalogPricingRule $rule) => $this->buildRuleRow($rule, $store, $currencyIso, $dateFormat))
                ->values()
                ->all(),
        );

        $paginator = new LengthAwarePaginator($rows, $rules->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
        ]);

        return new JsonResponse([
            'data' => $rows,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'next_page_url' => $paginator->nextPageUrl(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * @param Collection<int, CatalogPricingRule> $rules
     * @param array<int, mixed> $sort
     * @return Collection<int, CatalogPricingRule>
     */
    private function sortedRules(Collection $rules, array $sort): Collection
    {
        $value = match ($sort[0]['field'] ?? null) {
            'name' => fn(CatalogPricingRule $rule) => mb_strtolower(t($rule->name, category: 'site')),
            'duration' => fn(CatalogPricingRule $rule) => $rule->dateFrom?->getTimestamp() ?? PHP_INT_MIN,
            'isPromotionalPrice' => fn(CatalogPricingRule $rule) => $rule->isPromotionalPrice,
            default => null,
        };

        if ($value === null) {
            return $rules;
        }

        return $rules
            ->sortBy($value, SORT_NATURAL, ($sort[0]['direction'] ?? 'asc') === 'desc')
            ->values();
    }

    private function buildRuleRow(CatalogPricingRule $rule, Store $store, string $currencyIso, string $dateFormat): array
    {
        $dateRange = (!$rule->dateFrom && !$rule->dateTo)
            ? '∞'
            : ($rule->dateFrom?->format($dateFormat) ?? '∞') . ' - ' . ($rule->dateTo?->format($dateFormat) ?? '∞');

        $effect = match ($rule->apply) {
            CatalogPricingRuleRecord::APPLY_BY_PERCENT => $rule->applyAmountAsPercent . ' ' . t('(off original price)', category: 'commerce'),
            CatalogPricingRuleRecord::APPLY_TO_PERCENT => $rule->applyAmountAsPercent . ' ' . t('(of original price)', category: 'commerce'),
            CatalogPricingRuleRecord::APPLY_BY_FLAT => Currency::formatAsCurrency($rule->applyAmountAsFlat, $currencyIso, true) . ' ' . t('(off original price)', category: 'commerce'),
            default => Currency::formatAsCurrency($rule->applyAmountAsFlat, $currencyIso, true) . ' ' . t('(new price)', category: 'commerce'),
        };

        return [
            'id' => $rule->id,
            '_status' => $rule->enabled,
            'name' => ['label' => t($rule->name, category: 'site'), 'url' => $store->getStoreSettingsUrl('pricing-rules/' . $rule->id)],
            'duration' => $dateRange,
            'effect' => $effect,
            'isPromotionalPrice' => $rule->isPromotionalPrice ? ['icon' => 'check', 'label' => t('Yes')] : '',
        ];
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        abort_unless(currentUserElement()?->can($id === null ? 'commerce-createCatalogPricingRules' : 'commerce-editCatalogPricingRules'), 403);

        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        if ($id) {
            $catalogPricingRule = app(CatalogPricingRules::class)->getCatalogPricingRuleById($id, $store->id);
            abort_if($catalogPricingRule === null || $catalogPricingRule->storeId !== $store->id, 404);
        } else {
            $catalogPricingRule = new CatalogPricingRule(['storeId' => $store->id]);

            $purchasableId = request()->input('purchasableId') ? (int)request()->input('purchasableId') : null;
            if ($purchasableId && $purchasableType = Elements::getElementTypeById($purchasableId)) {
                $purchasable = Elements::getElementById($purchasableId, $purchasableType, Cp::requestedSite()->id);

                if ($purchasable && $purchasable->title) {
                    $catalogPricingRule->name = t('{name} catalog price', ['name' => $purchasable->title], category: 'commerce');
                }

                $rule = Conditions::createConditionRule([
                    'class' => PurchasableConditionRule::class,
                    'elementIds' => [$purchasableType => [$purchasableId]],
                ]);

                /** @var CatalogPricingRulePurchasableCondition $purchasableCondition */
                $purchasableCondition = Conditions::createCondition(CatalogPricingRulePurchasableCondition::class);
                $purchasableCondition->addConditionRule($rule);
                $catalogPricingRule->setPurchasableCondition($purchasableCondition);
            }
        }

        $variables = $this->populateVariables(['id' => $id, 'catalogPricingRule' => $catalogPricingRule, 'storeHandle' => $storeHandle]);

        return $this->storeManagementCpScreen($storeHandle, false)
            ->title(t('Catalog Pricing Rule', category: 'commerce'))
            ->addCrumb(t('Pricing Rules', category: 'commerce'), $store->getStoreSettingsUrl('pricing-rules'))
            ->action('commerce/catalog-pricing-rules/save')
            ->redirectUrl('commerce/store-management/' . $store->handle . '/pricing-rules')
            ->metaSidebarTemplate('commerce/store-management/pricing-rules/_sidebar', $variables)
            ->tabs([
                'rule' => [
                    'label' => t('Rule', category: 'commerce'),
                    'url' => '#rule',
                    'class' => array_filter([$variables['catalogPricingRule']->getErrors() ? 'error' : null]),
                ],
                'conditions' => [
                    'label' => t('Conditions', category: 'commerce'),
                    'url' => '#conditions',
                ],
                'actions' => [
                    'label' => t('Actions', category: 'commerce'),
                    'url' => '#actions',
                    'class' => array_filter([($variables['catalogPricingRule']->getErrors('applyAmount') || $variables['catalogPricingRule']->getErrors('apply')) ? 'error' : null]),
                ],
            ])
            ->contentTemplate('commerce/store-management/pricing-rules/_edit', $variables);
    }

    public function save(Request $request): Response
    {
        $id = $request->input('id') ? (int)$request->input('id') : null;
        $storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $this->requireStoreAccess($storeId);

        if ($id) {
            $catalogPricingRule = app(CatalogPricingRules::class)->getCatalogPricingRuleById($id, $storeId);
            abort_if($catalogPricingRule === null, 404, 'Catalog Pricing Rule not found');
        } else {
            $catalogPricingRule = new CatalogPricingRule();
        }

        abort_unless(currentUserElement()?->can($catalogPricingRule->id === null ? 'commerce-createCatalogPricingRules' : 'commerce-editCatalogPricingRules'), 403);

        $catalogPricingRule->storeId = $storeId;
        $catalogPricingRule->name = $request->input('name');
        $catalogPricingRule->description = $request->input('description');
        $catalogPricingRule->apply = $request->input('apply');
        $catalogPricingRule->enabled = (bool)$request->input('enabled');
        $catalogPricingRule->isPromotionalPrice = (bool)$request->input('isPromotionalPrice');
        $catalogPricingRule->applyPriceType = $request->input('applyPriceType');

        if (($date = $request->input('dateFrom')) !== null && $dateFrom = DateTimeHelper::toDateTime($date)) {
            $catalogPricingRule->dateFrom = $dateFrom instanceof DateTime ? $dateFrom : DateTime::createFromInterface($dateFrom);
        }
        if (($date = $request->input('dateTo')) !== null && $dateTo = DateTimeHelper::toDateTime($date)) {
            $catalogPricingRule->dateTo = $dateTo instanceof DateTime ? $dateTo : DateTime::createFromInterface($dateTo);
        }

        $applyAmount = $request->input('applyAmount');

        if ($catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_BY_PERCENT || $catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_TO_PERCENT) {
            $applyAmount = Localization::normalizeNumber($applyAmount);
            $catalogPricingRule->applyAmount = (float)$applyAmount / -100;
        } else {
            if (is_array($applyAmount)) {
                $applyAmount += ['currency' => $catalogPricingRule->getStore()->getCurrency()];
                $applyAmount = Money::toDecimal(Money::toMoney($applyAmount));
            }
            $catalogPricingRule->applyAmount = (float)$applyAmount * -1;
        }

        $productCondition = $request->input('productCondition') ?? Conditions::createCondition([
            'class' => CatalogPricingRuleProductCondition::class,
        ]);
        $catalogPricingRule->setProductCondition($productCondition);

        $variantCondition = $request->input('variantCondition') ?? Conditions::createCondition([
            'class' => CatalogPricingRuleVariantCondition::class,
        ]);
        $catalogPricingRule->setVariantCondition($variantCondition);

        $purchasableCondition = $request->input('purchasableCondition') ?? Conditions::createCondition([
            'class' => CatalogPricingRulePurchasableCondition::class,
        ]);
        $catalogPricingRule->setPurchasableCondition($purchasableCondition);

        $catalogPricingRule->setCustomerCondition($request->input('customerCondition'));

        if (app(CatalogPricingRules::class)->saveCatalogPricingRule($catalogPricingRule)) {
            return $this->asSuccess(t('Catalog pricing rule saved.', category: 'commerce'));
        }

        $variables = $this->populateVariables(['catalogPricingRule' => $catalogPricingRule]);

        return $this->asFailure(t('Couldn\'t save catalog pricing rule.', category: 'commerce'), $variables);
    }

    public function delete(Request $request): Response
    {
        abort_unless(currentUserElement()?->can('commerce-deleteCatalogPricingRules'), 403);

        $id = $request->input('id');
        $ids = $request->input('ids');

        abort_if((!$id && empty($ids)) || ($id && !empty($ids)), 400, 'id or ids must be specified.');

        if ($id) {
            abort_unless($request->expectsJson(), 400);
            $ids = [$id];
        }

        foreach ($ids as $deleteId) {
            $catalogPricingRule = app(CatalogPricingRules::class)->getCatalogPricingRuleById((int)$deleteId);
            if ($catalogPricingRule) {
                $this->requireStoreAccess($catalogPricingRule->storeId);
            }

            app(CatalogPricingRules::class)->deleteCatalogPricingRuleById((int)$deleteId);
        }

        if ($request->expectsJson()) {
            return $this->asSuccess();
        }

        return $this->asSuccess(t('Catalog pricing rules deleted.', category: 'commerce'), redirect: url()->previous());
    }

    public function updateStatus(Request $request): Response
    {
        abort_unless(currentUserElement()?->can('commerce-editCatalogPricingRules'), 403);

        $ids = $request->input('ids');
        $status = $request->input('status');

        abort_if(empty($ids), 400, 'Missing ids');

        $storeId = null;

        DB::transaction(function() use ($ids, $status, &$storeId) {
            $rules = CatalogPricingRuleRecord::whereIn('id', $ids)->get();

            foreach ($rules as $rule) {
                $this->requireStoreAccess($rule->storeId);
                $storeId ??= $rule->storeId;
                $rule->enabled = ($status == 'enabled');
                $rule->save();
            }
        });

        app(CatalogPricing::class)->createCatalogPricingJob([
            'catalogPricingRuleIds' => $ids,
            'storeId' => $storeId,
        ]);

        return $this->asSuccess(t('Catalog pricing rules updated.', category: 'commerce'));
    }

    private function populateVariables(array $variables): array
    {
        /** @var CatalogPricingRule $catalogPricingRule */
        $catalogPricingRule = $variables['catalogPricingRule'];

        $variables['title'] = $catalogPricingRule->id ? $catalogPricingRule->name : t('Create a new catalog pricing rule', category: 'commerce');

        $groups = UserGroups::getAllGroups();
        $variables['groups'] = $groups->mapWithKeys(fn($group) => [$group->id => $group->name])->all();

        $variables['percentSymbol'] = I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $primaryCurrencyIso = app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso();
        $variables['currencySymbol'] = I18N::getLocale()->getCurrencySymbol($primaryCurrencyIso);

        $variables['applyAmount'] = '';
        if ($catalogPricingRule->applyAmount !== null) {
            if ($catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_BY_PERCENT || $catalogPricingRule->apply == CatalogPricingRuleRecord::APPLY_TO_PERCENT) {
                $amount = -(float)$catalogPricingRule->applyAmount * 100;
                $variables['applyAmount'] = I18N::getFormatter()->asDecimal($amount);
            } else {
                $variables['applyAmount'] = I18N::getFormatter()->asDecimal(-(float)$catalogPricingRule->applyAmount);
            }
        }

        $variables['applyOptions'] = [
            ['optgroup' => t('Reduce price', category: 'commerce')],
            ['label' => t('Reduce the price by a percentage of the original price', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_BY_PERCENT],
            ['label' => t('Reduce the price by a fixed amount', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_BY_FLAT],
            ['optgroup' => t('Set price', category: 'commerce')],
            ['label' => t('Set the price to a percentage of the original price', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_TO_PERCENT],
            ['label' => t('Set the price to a flat amount', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_TO_FLAT],
        ];

        $variables['applyPriceTypeOptions'] = [
            ['label' => t('Original price', category: 'commerce'), 'value' => 'price'],
            ['label' => t('Original promotional price', category: 'commerce'), 'value' => 'promotionalPrice'],
        ];

        // Condition classes no longer self-render; ConditionBuilderRenderer replaces the old getBuilderHtml()/builderHtml().
        $variables['productConditionHtml'] = new ConditionBuilderRenderer($catalogPricingRule->getProductCondition())->render();
        $variables['variantConditionHtml'] = new ConditionBuilderRenderer($catalogPricingRule->getVariantCondition())->render();
        $variables['purchasableConditionHtml'] = new ConditionBuilderRenderer($catalogPricingRule->getPurchasableCondition())->render();
        $variables['customerConditionHtml'] = new ConditionBuilderRenderer($catalogPricingRule->getCustomerCondition())->render();

        return $variables;
    }
}
