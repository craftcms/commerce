<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Combobox;
use CraftCms\Cms\Form\Controls\ConditionBuilder;
use CraftCms\Cms\Form\Controls\DateTime as DateTimeControl;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Money as MoneyControl;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Group;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Commerce\CatalogPricing\CatalogPricing;
use CraftCms\Commerce\CatalogPricing\CatalogPricingRules;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleProductCondition;
use CraftCms\Commerce\CatalogPricing\Conditions\CatalogPricingRuleVariantCondition;
use CraftCms\Commerce\CatalogPricing\Data\CatalogPricingRule;
use CraftCms\Commerce\CatalogPricing\Models\CatalogPricingRule as CatalogPricingRuleRecord;
use CraftCms\Commerce\Customer\Conditions\CatalogPricingRuleCustomerCondition;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Helpers\Localization;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Purchasable\Conditions\CatalogPricingRulePurchasableCondition;
use CraftCms\Commerce\Purchasable\Conditions\PurchasableConditionRule;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
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
        $catalogPricingRule = $this->resolveCatalogPricingRule($id, $store);
        abort_if($id !== null && $catalogPricingRule === null, 404);
        $catalogPricingRule ??= $this->newCatalogPricingRule($store, request()->integer('purchasableId') ?: null);

        $title = $catalogPricingRule->id ? $catalogPricingRule->name : t('Create a new catalog pricing rule', category: 'commerce');

        $formatter = app(Formatter::class);
        $metadataHtml = $catalogPricingRule->id ? app(ContentHtml::class)->metadataHtml([
            t('ID', category: 'commerce') => (string) $catalogPricingRule->id,
            t('Created at') => $formatter->asDateTime($catalogPricingRule->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($catalogPricingRule->dateUpdated, 'short'),
        ]) : null;

        $values = $this->initialValues($catalogPricingRule, $store);

        $form = $this->formResolver->resolve(
            $this->buildForm($values, $store),
            new FormContext(values: $values, refreshable: true),
        );

        return $this->cpScreenResponse($store, subnav: false)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($catalogPricingRule->id ? [['label' => $title]] : [])))
            ->action('commerce/catalog-pricing-rules/save')
            ->redirectUrl($store->getStoreSettingsUrl('pricing-rules'))
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'refreshUrl' => action([self::class, 'renderForm']),
                'metadataHtml' => $metadataHtml,
            ]);
    }

    /**
     * Re-resolves the {@see edit()} Form tree for the values currently in progress on the
     * client, so changing the effect can swap between the percentage and money inputs and
     * show or hide the price type.
     */
    public function renderForm(Request $request): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
            'values.storeId' => ['required', 'integer'],
            'values.id' => ['nullable', 'integer'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');
        $store = app(Stores::class)->getStoreById((int) $values['storeId']);
        abort_if($store === null, 404);
        $this->requireStoreAccess($store->id);

        $id = isset($values['id']) ? (int) $values['id'] : null;
        abort_unless(currentUserElement()?->can($id === null ? 'commerce-createCatalogPricingRules' : 'commerce-editCatalogPricingRules'), 403);

        $catalogPricingRule = $this->resolveCatalogPricingRule($id, $store);
        abort_if($id !== null && $catalogPricingRule === null, 404);
        $catalogPricingRule ??= $this->newCatalogPricingRule($store);

        $values = array_replace($this->initialValues($catalogPricingRule, $store), $values);

        $form = $this->formResolver->resolve(
            $this->buildForm($values, $store),
            new FormContext(values: $values, refreshable: true),
        );

        return new JsonResponse(['form' => $form]);
    }

    private function resolveCatalogPricingRule(?int $id, Store $store): ?CatalogPricingRule
    {
        $catalogPricingRule = $id ? app(CatalogPricingRules::class)->getCatalogPricingRuleById($id, $store->id) : null;

        return $catalogPricingRule?->storeId === $store->id ? $catalogPricingRule : null;
    }

    /** A `purchasableId` (from a purchasable's own edit screen) prefills the name and a purchasable condition matching just that purchasable. */
    private function newCatalogPricingRule(Store $store, ?int $purchasableId = null): CatalogPricingRule
    {
        $catalogPricingRule = new CatalogPricingRule(['storeId' => $store->id]);

        if (!$purchasableId || !$purchasableType = Elements::getElementTypeById($purchasableId)) {
            return $catalogPricingRule;
        }

        $purchasable = Elements::getElementById($purchasableId, $purchasableType, $store->getSites()->pluck('id')->all());

        if ($purchasable?->title) {
            $catalogPricingRule->name = t('{name} catalog price', ['name' => $purchasable->title], category: 'commerce');
        }

        /** @var CatalogPricingRulePurchasableCondition $purchasableCondition */
        $purchasableCondition = Conditions::createCondition(CatalogPricingRulePurchasableCondition::class);
        $purchasableCondition->addConditionRule(Conditions::createConditionRule([
            'class' => PurchasableConditionRule::class,
            'elementIds' => [$purchasableType => [$purchasableId]],
        ]));
        $catalogPricingRule->setPurchasableCondition($purchasableCondition);

        return $catalogPricingRule;
    }

    /** @return array<string, mixed> */
    private function initialValues(CatalogPricingRule $catalogPricingRule, Store $store): array
    {
        $apply = $catalogPricingRule->apply;

        // Stored negative (an adjustment subtracted from the original price) and as a fraction
        // for the percentage types — shown to the editor as a plain positive amount/percentage,
        // flipped back in save().
        $applyAmount = match (true) {
            $catalogPricingRule->applyAmount === null => null,
            $this->isPercentApply($apply) => round(-$catalogPricingRule->applyAmount * 100, 6),
            default => -$catalogPricingRule->applyAmount,
        };

        return [
            'id' => $catalogPricingRule->id,
            'storeId' => $store->id,
            'name' => $catalogPricingRule->name,
            'description' => $catalogPricingRule->description,
            'enabled' => $catalogPricingRule->enabled,
            'dateFrom' => $this->dateTimeControlValue($catalogPricingRule->dateFrom),
            'dateTo' => $this->dateTimeControlValue($catalogPricingRule->dateTo),
            'productCondition' => $catalogPricingRule->getProductCondition()->getConfig(),
            'variantCondition' => $catalogPricingRule->getVariantCondition()->getConfig(),
            'purchasableCondition' => $catalogPricingRule->getPurchasableCondition()->getConfig(),
            'customerCondition' => $catalogPricingRule->getCustomerCondition()->getConfig(),
            'apply' => $apply,
            'applyPriceType' => $catalogPricingRule->applyPriceType,
            'applyAmount' => $applyAmount,
            'isPromotionalPrice' => $catalogPricingRule->isPromotionalPrice,
        ];
    }

    /** @param array<string, mixed> $values */
    private function buildForm(array $values, Store $store): Form
    {
        $apply = (string) ($values['apply'] ?? '');
        $hasPurchasableRules = !empty($values['purchasableCondition']['conditionRules']['rules'] ?? []);

        $applyAmountControl = $this->isPercentApply($apply)
            ? Number::make('applyAmount')->step('any')->size(5)->suffix(I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT))
            : MoneyControl::make('applyAmount')->currency($store->getCurrency()?->getCode() ?? 'USD')->size(5)->showCurrency();

        $actionsFields = [
            Field::make(t('Effect', category: 'commerce'), Combobox::make('apply')
                ->options([
                    ['type' => 'optgroup', 'label' => t('Reduce price', category: 'commerce'), 'options' => [
                        ['label' => t('Reduce the price by a percentage of the original price', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_BY_PERCENT],
                        ['label' => t('Reduce the price by a fixed amount', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_BY_FLAT],
                    ]],
                    ['type' => 'optgroup', 'label' => t('Set price', category: 'commerce'), 'options' => [
                        ['label' => t('Set the price to a percentage of the original price', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_TO_PERCENT],
                        ['label' => t('Set the price to a flat amount', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_TO_FLAT],
                    ]],
                ])
                ->requireOptionMatch()
                ->showAllOnEmpty()
                ->reactive())
                ->instructions(t('Select how the catalog pricing rule will be applied to the purchasable(s).', category: 'commerce'))
                ->required(),
        ];

        if ($apply !== CatalogPricingRuleRecord::APPLY_TO_FLAT) {
            $actionsFields[] = Field::make(t('Price Type', category: 'commerce'), Choice::make('applyPriceType')
                ->options([
                    ['label' => t('Original price', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE],
                    ['label' => t('Original promotional price', category: 'commerce'), 'value' => CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PROMOTIONAL_PRICE],
                ])
                ->withoutPlaceholder());
        } else {
            $actionsFields[] = HiddenField::make('applyPriceType');
        }

        $actionsFields[] = Field::make(t('Amount', category: 'commerce'), $applyAmountControl);
        $actionsFields[] = Field::make(t('Is Promotional Price?', category: 'commerce'), Lightswitch::make('isPromotionalPrice'));

        return Form::make([
            HiddenField::make('id'),
            HiddenField::make('storeId'),
        ])
            ->addTab(t('Rule', category: 'commerce'), [
                Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
                    ->instructions(t('What this catalog pricing rule will be called in the control panel.', category: 'commerce'))
                    ->required(),
                Field::make(t('Description', category: 'commerce'), Text::make('description'))
                    ->instructions(t('Catalog pricing rule description.', category: 'commerce')),
                Field::make(t('Enable this rule', category: 'commerce'), Lightswitch::make('enabled'))
                    ->instructions(t('Whether this catalog pricing rule should be available for use, regardless of other conditions.', category: 'commerce')),
            ])
            ->addTab(t('Conditions', category: 'commerce'), [
                Field::make(t('Start Date', category: 'commerce'), DateTimeControl::make('dateFrom')->showTime())
                    ->instructions(t('Date from which the catalog pricing rule will be active. Leave blank for unlimited start date', category: 'commerce')),
                Field::make(t('End Date', category: 'commerce'), DateTimeControl::make('dateTo')->showTime())
                    ->instructions(t('Date when the catalog pricing rule will be finished. Leave blank for unlimited end date', category: 'commerce')),
                Field::make(t('Match Product', category: 'commerce'), ConditionBuilder::make('productCondition')
                    ->conditionClass(CatalogPricingRuleProductCondition::class)),
                Field::make(t('Match Variant', category: 'commerce'), ConditionBuilder::make('variantCondition')
                    ->conditionClass(CatalogPricingRuleVariantCondition::class)),
                Group::make('purchasable-condition-advanced', [
                    Field::make(t('Match Purchasable', category: 'commerce'), ConditionBuilder::make('purchasableCondition')
                        ->conditionClass(CatalogPricingRulePurchasableCondition::class)),
                ])
                    ->label(t('Advanced', category: 'commerce'))
                    ->collapsible()
                    ->expanded($hasPurchasableRules),
                Field::make(t('Match Customer', category: 'commerce'), ConditionBuilder::make('customerCondition')
                    ->conditionClass(CatalogPricingRuleCustomerCondition::class)),
            ])
            ->addTab(t('Actions', category: 'commerce'), $actionsFields);
    }

    private function isPercentApply(string $apply): bool
    {
        return in_array($apply, [CatalogPricingRuleRecord::APPLY_BY_PERCENT, CatalogPricingRuleRecord::APPLY_TO_PERCENT], true);
    }

    public function save(Request $request): Response
    {
        $id = $request->input('id') ? (int) $request->input('id') : null;
        $storeId = (int) $request->input('storeId');
        $this->requireStoreAccess($storeId);

        abort_unless(currentUserElement()?->can($id === null ? 'commerce-createCatalogPricingRules' : 'commerce-editCatalogPricingRules'), 403);

        if ($id) {
            $catalogPricingRule = app(CatalogPricingRules::class)->getCatalogPricingRuleById($id, $storeId);
            abort_if($catalogPricingRule === null, 404, 'Catalog Pricing Rule not found');
        } else {
            $catalogPricingRule = new CatalogPricingRule();
        }

        $catalogPricingRule->storeId = $storeId;
        $catalogPricingRule->name = $request->input('name');
        $catalogPricingRule->description = $request->input('description');
        $catalogPricingRule->apply = (string) $request->input('apply');
        $catalogPricingRule->enabled = (bool) $request->input('enabled');
        $catalogPricingRule->isPromotionalPrice = (bool) $request->input('isPromotionalPrice');
        $catalogPricingRule->applyPriceType = $request->input('applyPriceType') ?: CatalogPricingRuleRecord::APPLY_PRICE_TYPE_PRICE;
        $catalogPricingRule->dateFrom = $this->dateTimeInput($request->input('dateFrom'));
        $catalogPricingRule->dateTo = $this->dateTimeInput($request->input('dateTo'));

        // A value left over from before the effect was switched can arrive in the other
        // control's shape, so either shape is accepted for either type.
        $applyAmount = $request->input('applyAmount');
        $applyAmount = is_array($applyAmount) ? $applyAmount : ['value' => $applyAmount];

        if ($this->isPercentApply($catalogPricingRule->apply)) {
            $catalogPricingRule->applyAmount = -Localization::normalizePercentage($applyAmount['value'] ?? null);
        } else {
            $applyAmount += ['currency' => $catalogPricingRule->getStore()->getCurrency()];
            $catalogPricingRule->applyAmount = (float) Money::toDecimal(Money::toMoney($applyAmount)) * -1;
        }

        $catalogPricingRule->setProductCondition($request->input('productCondition') ?? Conditions::createCondition([
            'class' => CatalogPricingRuleProductCondition::class,
        ]));
        $catalogPricingRule->setVariantCondition($request->input('variantCondition') ?? Conditions::createCondition([
            'class' => CatalogPricingRuleVariantCondition::class,
        ]));
        $catalogPricingRule->setPurchasableCondition($request->input('purchasableCondition') ?? Conditions::createCondition([
            'class' => CatalogPricingRulePurchasableCondition::class,
        ]));
        $catalogPricingRule->setCustomerCondition($request->input('customerCondition') ?? Conditions::createCondition([
            'class' => CatalogPricingRuleCustomerCondition::class,
        ]));

        if (app(CatalogPricingRules::class)->saveCatalogPricingRule($catalogPricingRule)) {
            return $this->asModelSuccess($catalogPricingRule, t('Catalog pricing rule saved.', category: 'commerce'), 'catalogPricingRule');
        }

        return $this->asModelFailure($catalogPricingRule, t('Couldn’t save catalog pricing rule.', category: 'commerce'), 'catalogPricingRule');
    }

    /** An empty date clears it — the rule is loaded from the database first, so leaving it unset would keep the old date. */
    private function dateTimeInput(mixed $value): ?DateTime
    {
        if (!$value || (is_array($value) && empty($value['date']))) {
            return null;
        }

        $dateTime = DateTimeHelper::toDateTime($value);

        if (!$dateTime) {
            return null;
        }

        return $dateTime instanceof DateTime ? $dateTime : DateTime::createFromInterface($dateTime);
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
}
