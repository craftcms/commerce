<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use craft\helpers\Localization;
use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Form\Controls\ConditionBuilder;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Money as MoneyControl;
use CraftCms\Cms\Form\Controls\Table as TableControl;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Controls\Textarea;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Callout;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Group;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\MarkdownContent;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Commerce\Customer\Conditions\ShippingRuleCustomerCondition;
use CraftCms\Commerce\Order\Conditions\ShippingRuleOrderCondition;
use CraftCms\Commerce\Shipping\Data\ShippingMethod;
use CraftCms\Commerce\Shipping\Data\ShippingRule;
use CraftCms\Commerce\Shipping\Data\ShippingRuleCategory;
use CraftCms\Commerce\Shipping\Models\ShippingRuleCategory as ShippingRuleCategoryRecord;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Shipping\ShippingMethods;
use CraftCms\Commerce\Shipping\ShippingRules;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class ShippingRulesController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Shipping Methods', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('shippingmethods')];
    }

    public function edit(?string $storeHandle = null, ?int $methodId = null, ?int $ruleId = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $shippingMethod = app(ShippingMethods::class)->getShippingMethodById($methodId, $store->id);
        abort_if($shippingMethod === null, 404);

        if ($ruleId) {
            $shippingRule = app(ShippingRules::class)->getShippingRuleById($ruleId);
            abort_if($shippingRule === null, 404);
        } else {
            $shippingRule = new ShippingRule();
            $shippingRule->methodId = $shippingMethod->id;
            $shippingRule->storeId = $shippingMethod->storeId;
        }

        $title = $shippingRule->id ? $shippingRule->name : t('Create a new shipping rule', category: 'commerce');

        $formatter = app(Formatter::class);
        $metadataHtml = $shippingRule->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($shippingRule->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($shippingRule->dateUpdated, 'short'),
        ]) : null;

        $values = $this->initialValues($shippingMethod, $shippingRule, $store);

        $form = $this->formResolver->resolve(
            $this->buildForm($shippingMethod, $shippingRule, $values, $store),
            new FormContext(values: $values, refreshable: true),
        );

        $redirectUrl = $store->getStoreSettingsUrl("shippingmethods/{$shippingMethod->id}#rules");

        $response = $this->cpScreenResponse($store, subnav: false)
            ->title($title)
            ->crumbs($this->crumbs(
                $store,
                ['label' => $shippingMethod->getName(), 'href' => $shippingMethod->getCpEditUrl()],
                ...($shippingRule->id ? [['label' => $title]] : []),
            ))
            ->redirectUrl($redirectUrl);

        if ($shippingRule->id) {
            $response
                ->addAltAction(t('Save as a new rule', category: 'commerce'), [
                    'action' => action([self::class, 'duplicate']),
                    'confirm' => t('Are you sure you want to save this as a new shipping rule?', category: 'commerce'),
                ])
                ->addAltAction(t('Delete', category: 'commerce'), [
                    'destructive' => true,
                    'action' => action([self::class, 'delete']),
                    'confirm' => t('Are you sure you want to delete this shipping rule?', category: 'commerce'),
                ]);
        }

        return $response->inertiaPage('Form', [
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
     * client, so changing a shipping category's condition can hide or reveal its rate-override
     * row on the Costs tab without a full page reload.
     */
    public function renderForm(Request $request): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
            'values.storeId' => ['required', 'integer'],
            'values.methodId' => ['required', 'integer'],
            'values.id' => ['nullable', 'integer'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');
        $store = app(Stores::class)->getStoreById((int) $values['storeId']);
        abort_if($store === null, 404);

        $shippingMethod = app(ShippingMethods::class)->getShippingMethodById((int) $values['methodId'], $store->id);
        abort_if($shippingMethod === null, 404);

        $ruleId = $values['id'] ?? null;
        if ($ruleId) {
            $shippingRule = app(ShippingRules::class)->getShippingRuleById((int) $ruleId);
            abort_if($shippingRule === null, 404);
        } else {
            $shippingRule = new ShippingRule();
            $shippingRule->methodId = $shippingMethod->id;
            $shippingRule->storeId = $shippingMethod->storeId;
        }

        $values = array_replace($this->initialValues($shippingMethod, $shippingRule, $store), $values);

        $form = $this->formResolver->resolve(
            $this->buildForm($shippingMethod, $shippingRule, $values, $store),
            new FormContext(values: $values, refreshable: true),
        );

        return new JsonResponse(['form' => $form]);
    }

    /** @return array<string, mixed> */
    private function initialValues(ShippingMethod $shippingMethod, ShippingRule $shippingRule, Store $store): array
    {
        // Every shipping category gets a fixed row in each of the two tables built in
        // buildForm(), keyed by its own id — see how save() recombines them, and how
        // buildForm() reads shippingCategoryConditions to hide a disallowed category's
        // override row.
        $shippingCategories = app(ShippingCategories::class)->getAllShippingCategories($store->id);
        $existingRuleCategories = collect($shippingRule->getShippingRuleCategories())->keyBy('shippingCategoryId');

        $shippingCategoryConditionRows = [];
        $ruleCategoryRows = [];
        foreach ($shippingCategories as $shippingCategory) {
            $ruleCategory = $existingRuleCategories->get($shippingCategory->id);

            $shippingCategoryConditionRows[$shippingCategory->id] = [
                'name' => Html::encode($shippingCategory->name),
                'condition' => $ruleCategory->condition ?? ShippingRuleCategoryRecord::CONDITION_ALLOW,
            ];

            $ruleCategoryRows[$shippingCategory->id] = [
                'name' => Html::encode($shippingCategory->name),
                'perItemRate' => $ruleCategory?->perItemRate,
                'weightRate' => $ruleCategory?->weightRate,
                'percentageRate' => $ruleCategory?->percentageRate,
            ];
        }

        return [
            'storeId' => $store->id,
            'methodId' => $shippingMethod->id,
            'id' => $shippingRule->id,
            'name' => $shippingRule->name,
            'description' => $shippingRule->description,
            'enabled' => $shippingRule->enabled,
            'orderCondition' => $shippingRule->getOrderCondition()->getConfig(),
            'customerCondition' => $shippingRule->getCustomerCondition()->getConfig(),
            'orderConditionFormula' => $shippingRule->orderConditionFormula,
            'baseRate' => $shippingRule->baseRate,
            'minRate' => $shippingRule->minRate,
            'maxRate' => $shippingRule->maxRate,
            'defaultRates' => [[
                'name' => '',
                'perItemRate' => $shippingRule->perItemRate,
                'weightRate' => $shippingRule->weightRate,
                'percentageRate' => $shippingRule->percentageRate,
            ]],
            'shippingCategoryConditions' => $shippingCategoryConditionRows,
            'ruleCategories' => $ruleCategoryRows,
        ];
    }

    /** @param array<string, mixed> $values */
    private function buildForm(ShippingMethod $shippingMethod, ShippingRule $shippingRule, array $values, Store $store): Form
    {
        $currency = $store->getCurrency()?->getCode() ?? 'USD';

        $categoryConditionOptions = [
            ['label' => t('Allow', category: 'commerce'), 'value' => ShippingRuleCategoryRecord::CONDITION_ALLOW],
            ['label' => t('Disallow', category: 'commerce'), 'value' => ShippingRuleCategoryRecord::CONDITION_DISALLOW],
            ['label' => t('Require', category: 'commerce'), 'value' => ShippingRuleCategoryRecord::CONDITION_REQUIRE],
        ];

        $hasShippingCategories = !empty($values['ruleCategories']);

        $formNodes = [
            HiddenField::make('storeId'),
            HiddenField::make('methodId'),
        ];

        if ($shippingRule->id) {
            $formNodes[] = HiddenField::make('id');
        }

        $conditionsTabFields = [
            // Plain text in 5.x — a bare `<p>`, not a boxed callout (which `Callout::make()`
            // would render as, unlike this).
            MarkdownContent::make('conditions-intro', t('Filtering conditions which describe to which orders this rule is applicable to. Write 0 to skip a condition.', category: 'commerce'))
                ->displayInPane(false),

            // Neither condition is project-config-tracked (both hardcode forProjectConfig
            // = false in their setters, same as the shipping method's own conditions), so
            // no ->forProjectConfig() here either.
            Field::make(t('Match Order', category: 'commerce'), ConditionBuilder::make('orderCondition')
                ->conditionClass(ShippingRuleOrderCondition::class)
                ->value($shippingRule->getOrderCondition()->getConfig())),

            Group::make('order-condition-formula-advanced', [
                Field::make(t('Order Condition Formula', category: 'commerce'), Textarea::make('orderConditionFormula')->rows(5)->monospace())
                    ->instructions(t('Specify a <a href="{url}">Twig condition</a> that determines whether the shipping rule should apply to a given order. (The order can be referenced via an `order` variable.)', [
                        'url' => 'https://twig.symfony.com/doc/2.x/templates.html#expressions',
                    ], category: 'commerce')),
            ])
                ->label(t('Advanced', category: 'commerce'))
                ->collapsible()
                ->expanded((bool) ($values['orderConditionFormula'] ?? '')),

            Field::make(t('Match Customer', category: 'commerce'), ConditionBuilder::make('customerCondition')
                ->conditionClass(ShippingRuleCustomerCondition::class)
                ->value($shippingRule->getCustomerCondition()->getConfig())),
        ];

        $costsTabFields = [
            Field::make(t('Base Rate', category: 'commerce'), MoneyControl::make('baseRate')->currency($currency))
                ->instructions(t('Shipping costs added to the order as a whole before percentage, item, and weight rates are applied. Set to zero to disable this rate. The whole rule, including this base rate, will not match and apply if the cart only contains non-shippable items like digital products.', category: 'commerce'))
                ->required(),
            Field::make(t('Minimum Total Shipping Cost', category: 'commerce'), MoneyControl::make('minRate')->currency($currency))
                ->instructions(t('The minimum the customer should spend on shipping. Set to zero to disable.', category: 'commerce'))
                ->required(),
            Field::make(t('Maximum Total Shipping Cost', category: 'commerce'), MoneyControl::make('maxRate')->currency($currency))
                ->instructions(t('The maximum the customer should spend on shipping. Set to zero to disable.', category: 'commerce'))
                ->required(),
            Heading::make('item-rates-heading', t('Item Rates', category: 'commerce')),
            // A single, fixed (non-addable/removable) row — matches 5.x's own layout, where
            // these defaults were the first row of the very table the category overrides
            // continue in below, rather than three standalone fields. The row's own values
            // are read back out of `defaultRates.0` in save() into the plain `perItemRate`/
            // `weightRate`/`percentageRate` properties; the table itself is purely a layout
            // device; note this drops the individual fields' own `required` enforcement
            // (the editable table has no per-cell concept of it) — matching 5.x's own table
            // row, which had no visible asterisks either; `ShippingRule::getRules()` still
            // enforces it server-side regardless.
            Field::make(control: TableControl::make('defaultRates')
                ->columns([
                    'name' => ['type' => 'heading', 'heading' => t('Name')],
                    'perItemRate' => ['type' => 'money', 'heading' => t('Per Item Rate', category: 'commerce'), 'currency' => $currency],
                    'weightRate' => ['type' => 'money', 'heading' => t('Weight Rate', category: 'commerce'), 'currency' => $currency],
                    'percentageRate' => ['type' => 'number', 'heading' => t('Percentage Rate', category: 'commerce')],
                ])),
        ];

        // Legacy hides both the conditions table and the overrides table entirely when the
        // store has no shipping categories at all — matched here rather than rendering an
        // empty table.
        if ($hasShippingCategories) {
            $conditionsTabFields[] = Heading::make('shipping-category-conditions-heading', t('Shipping Category Conditions', category: 'commerce'));
            // Reactive: changing any category's condition here refreshes the whole Form, which
            // recomputes $hiddenRuleCategoryIds below and re-hides/reveals the matching row in
            // the Costs tab's `ruleCategories` table — the cross-tab equivalent of legacy's own
            // jQuery-driven row hide/show, just server-recomputed on each change instead of
            // client-scripted.
            $conditionsTabFields[] = Field::make(control: TableControl::make('shippingCategoryConditions')
                ->keyed()
                ->columns([
                    'name' => ['type' => 'heading', 'heading' => t('Name')],
                    'condition' => ['type' => 'select', 'heading' => t('Condition', category: 'commerce'), 'options' => $categoryConditionOptions],
                ])
                ->reactive());

            // 5.x rendered the tip below this heading as a `craft-field`-less tip — its own
            // `<craft-callout variant="info" appearance="plain" padding="none">`, the exact
            // same markup a Field's own `tip()` produces (confirmed in `field.ts`'s
            // `_noticeTemplate()`) but a Field always places its tip *after* the input, not
            // before — so building it directly as a standalone Callout node instead
            // reproduces both the position and the styling.
            $costsTabFields[] = Heading::make('category-rate-overrides-heading', t('Category Rate Overrides', category: 'commerce'));
            $costsTabFields[] = Callout::make('category-rate-overrides-tip', t('Leave a category rate override blank to use the rate from above.', category: 'commerce'))
                ->variant('info')
                ->appearance('plain')
                ->padding('none');

            // A disallowed category's override rate is inert either way (it can never match
            // an order), so its row is hidden — matching 5.x's own cross-tab jQuery hide/show.
            // Computed fresh from the *current* (possibly just-posted, on a reactive refresh)
            // shippingCategoryConditions rather than the row's own stored condition, and passed
            // as a Control prop rather than baked into the row's value: props are always
            // reapplied fresh on a refresh, whereas row values only ever get merged in where
            // missing (so an already-known row's data can't be updated this way).
            $hiddenRuleCategoryIds = array_map('strval', array_keys(array_filter(
                (array) ($values['shippingCategoryConditions'] ?? []),
                fn(array $row): bool => ($row['condition'] ?? ShippingRuleCategoryRecord::CONDITION_ALLOW) === ShippingRuleCategoryRecord::CONDITION_DISALLOW,
            )));

            $costsTabFields[] = Field::make(control: TableControl::make('ruleCategories')
                ->keyed()
                ->columns([
                    'name' => ['type' => 'heading', 'heading' => t('Name')],
                    'perItemRate' => ['type' => 'money', 'heading' => t('Per Item Rate', category: 'commerce'), 'currency' => $currency],
                    'weightRate' => ['type' => 'money', 'heading' => t('Weight Rate', category: 'commerce'), 'currency' => $currency],
                    'percentageRate' => ['type' => 'number', 'heading' => t('Percentage Rate', category: 'commerce')],
                ])
                ->hiddenRows($hiddenRuleCategoryIds));
        }

        return Form::make($formNodes)
            ->addTab(t('Rule', category: 'commerce'), [
                Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
                    ->instructions(t('What this shipping rule will be called in the control panel.', category: 'commerce'))
                    ->required(),
                Field::make(t('Description', category: 'commerce'), Text::make('description'))
                    ->instructions(t('Describe this rule.', category: 'commerce')),
                Field::make(t('Enable this shipping rule', category: 'commerce'), Lightswitch::make('enabled')),
            ])
            ->addTab(t('Conditions', category: 'commerce'), $conditionsTabFields)
            ->addTab(t('Costs', category: 'commerce'), $costsTabFields);
    }

    public function duplicate(Request $request): Response
    {
        return $this->save($request, duplicate: true);
    }

    public function save(Request $request, bool $duplicate = false): Response
    {
        $shippingRule = new ShippingRule();

        if (!$duplicate) {
            $shippingRule->id = $request->input('id') ? (int)$request->input('id') : null;
        }
        $shippingRule->storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $this->requireStoreAccess($shippingRule->storeId);

        $moneyInputs = [
            'baseRate',
            'maxRate',
            'minRate',
        ];

        foreach ($moneyInputs as $moneyInput) {
            // The `Money` Form Control's canonical value stays a plain scalar (whatever
            // shape the initial `values` array supplied) until it's actually edited, at
            // which point it becomes `{value, locale}` — same duality
            // `Money::renderHtml()` itself already handles for display. `locale` is only
            // ever present once a real edit happened, so it's fine to omit here for an
            // untouched field: the value is already the plain decimal `ShippingRule`
            // property it started as, not a locale-formatted string needing parsing.
            $input = $request->input($moneyInput);
            $input = is_array($input) ? $input : ['value' => $input];
            $input += [
                'currency' => $shippingRule->getStore()->getCurrency(),
            ];
            $shippingRule->$moneyInput = (float)Money::toDecimal(Money::toMoney($input));
        }

        $shippingRule->name = $request->input('name');
        $shippingRule->description = $request->input('description');
        $shippingRule->methodId = $request->input('methodId') ? (int)$request->input('methodId') : null;
        $shippingRule->enabled = (bool)$request->input('enabled');
        $shippingRule->orderConditionFormula = trim((string)$request->input('orderConditionFormula', ''));

        // `defaultRates` is the single fixed row of the Item Rates table built in edit() —
        // a layout device matching 5.x's own combined table, not a real per-category row. Its
        // three cells map straight onto these plain properties.
        $defaultRates = (array) ($request->input('defaultRates')[0] ?? []);
        foreach (['perItemRate', 'weightRate'] as $moneyInput) {
            $input = $defaultRates[$moneyInput] ?? null;
            $input = is_array($input) ? $input : ['value' => $input];
            $input += [
                'currency' => $shippingRule->getStore()->getCurrency(),
            ];
            $shippingRule->$moneyInput = (float)Money::toDecimal(Money::toMoney($input));
        }
        $shippingRule->percentageRate = (float)Localization::normalizeNumber($defaultRates['percentageRate'] ?? 0);

        $shippingRule->setOrderCondition($request->input('orderCondition'));
        $shippingRule->setCustomerCondition($request->input('customerCondition'));

        // The Conditions and Costs tabs each own a separate keyed table (see edit()) —
        // recombine them here into the single per-category model save() actually needs.
        $conditions = (array)$request->input('shippingCategoryConditions');
        $rates = (array)$request->input('ruleCategories');

        $ruleCategories = [];
        foreach (array_unique([...array_keys($conditions), ...array_keys($rates)]) as $key) {
            $rateRow = $rates[$key] ?? [];
            $perItemRate = $rateRow['perItemRate'] ?? null;
            $weightRate = $rateRow['weightRate'] ?? null;
            $percentageRate = $rateRow['percentageRate'] ?? null;

            $ruleCategory = [
                'condition' => $conditions[$key]['condition'] ?? ShippingRuleCategoryRecord::CONDITION_ALLOW,
                'perItemRate' => (!isset($perItemRate) || trim((string)($perItemRate['value'] ?? '')) === '')
                    ? null
                    : Money::toDecimal(Money::toMoney(array_merge([
                        'currency' => $shippingRule->getStore()->getCurrency(),
                    ], $perItemRate))),
                'weightRate' => (!isset($weightRate) || trim((string)($weightRate['value'] ?? '')) === '')
                    ? null
                    : Money::toDecimal(Money::toMoney(array_merge([
                        'currency' => $shippingRule->getStore()->getCurrency(),
                    ], $weightRate))),
                'percentageRate' => (!isset($percentageRate) || trim((string)$percentageRate) === '') ? null : Localization::normalizeNumber($percentageRate),
            ];

            $ruleCategories[$key] = new ShippingRuleCategory($ruleCategory);
            $ruleCategories[$key]->shippingCategoryId = (int)$key;
        }

        $shippingRule->setShippingRuleCategories($ruleCategories);

        if (!app(ShippingRules::class)->saveShippingRule($shippingRule)) {
            return $this->asModelFailure($shippingRule, t('Couldn’t save shipping rule.', category: 'commerce'), 'shippingRule');
        }

        return $this->asModelSuccess($shippingRule, t('Shipping rule saved.', category: 'commerce'), 'shippingRule');
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = Json::decode($request->input('ids'));
        app(ShippingRules::class)->reorderShippingRules($ids);

        return $this->asSuccess();
    }

    public function delete(Request $request): Response
    {
        $id = $request->input('id');
        abort_if(!$id, 400, 'Shipping rule ID not submitted');
        $id = (int)$id;

        $rule = app(ShippingRules::class)->getShippingRuleById($id);
        abort_if($rule === null, 400, 'Cannot find shipping rule to delete');
        $this->requireStoreAccess($rule->storeId);

        if (!app(ShippingRules::class)->deleteShippingRuleById($id)) {
            return $this->asFailure(t('Could not delete shipping rule', category: 'commerce'));
        }

        return $this->asSuccess(t('Shipping rule deleted.', category: 'commerce'));
    }

    private function percentSymbol(): string
    {
        return I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
    }
}
