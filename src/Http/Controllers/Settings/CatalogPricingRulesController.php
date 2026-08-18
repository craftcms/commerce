<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\elements\conditions\products\CatalogPricingRuleProductCondition;
use craft\commerce\elements\conditions\purchasables\CatalogPricingRulePurchasableCondition;
use craft\commerce\elements\conditions\purchasables\PurchasableConditionRule;
use craft\commerce\elements\conditions\variants\CatalogPricingRuleVariantCondition;
use craft\commerce\helpers\Currency;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use CraftCms\Cms\Support\DateTimeHelper;
use craft\helpers\Html;
use CraftCms\Cms\Support\Json;
use craft\helpers\Localization;
use CraftCms\Cms\Support\Money;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Facades\UserGroups;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Commerce\CatalogPricing\Records\CatalogPricingRule as CatalogPricingRuleRecord;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\t;

readonly class CatalogPricingRulesController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $catalogPricingRules = Plugin::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRules($store->id);

        $actionButtonHtml = currentUserElement()?->can('commerce-createCatalogPricingRules') ?
            Html::a(t('New catalog pricing rule', category: 'commerce'),
                $store->getStoreSettingsUrl('pricing-rules/new'),
                ['class' => 'btn submit add icon'])
            : '';

        $tableData = [];
        $catalogPricingRules->each(function(CatalogPricingRule $pcr) use (&$tableData, $store) {
            $effect = $pcr->apply === CatalogPricingRuleRecord::APPLY_BY_PERCENT || $pcr->apply === CatalogPricingRuleRecord::APPLY_TO_PERCENT
                ? $pcr->applyAmountAsPercent . ' ' . ($pcr->apply === CatalogPricingRuleRecord::APPLY_BY_PERCENT
                    ? t('(off original price)', category: 'commerce')
                    : t('(of original price)', category: 'commerce'))
                : Currency::formatAsCurrency($pcr->applyAmountAsFlat, Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency($store->id)->iso, true) . ' ' . ($pcr->apply === CatalogPricingRuleRecord::APPLY_BY_FLAT
                    ? t('(off original price)', category: 'commerce')
                    : t('(new price)', category: 'commerce'));

            $dateRange = ($pcr->dateFrom ? I18N::getFormatter()->asDatetime($pcr->dateFrom, 'short') : '∞') . ' - ' . ($pcr->dateTo ? I18N::getFormatter()->asDatetime($pcr->dateTo, 'short') : '∞');
            $dateRange = !$pcr->dateFrom && !$pcr->dateTo ? '∞' : $dateRange;

            $tableData[] = [
                'id' => $pcr->id,
                'title' => t($pcr->name, category: 'site'),
                'url' => $pcr->getCpEditUrl(),
                'status' => $pcr->enabled ? true : false,
                'duration' => $dateRange,
                'effect' => $effect,
                'isPromotionalPrice' => $pcr->isPromotionalPrice,
            ];
        });

        $tableData = Json::encode($tableData);

        $actions = [];
        if (currentUserElement()?->can('commerce-editCatalogPricingRules')) {
            $actions[] = [
                'label' => t('Set status', category: 'commerce'),
                'actions' => [
                    [
                        'label' => t('Enabled', category: 'commerce'),
                        'action' => 'commerce/catalog-pricing-rules/update-status',
                        'param' => 'status',
                        'value' => 'enabled',
                        'status' => 'enabled',
                    ],
                    [
                        'label' => t('Disabled', category: 'commerce'),
                        'action' => 'commerce/catalog-pricing-rules/update-status',
                        'param' => 'status',
                        'value' => 'disabled',
                        'status' => 'disabled',
                    ],
                ],
            ];
        }

        $deleteAction = null;
        if (currentUserElement()?->can('commerce-deleteCatalogPricingRules')) {
            $actions[] = [
                'label' => t('Delete', category: 'commerce'),
                'action' => 'commerce/catalog-pricing-rules/delete',
                'error' => true,
            ];
            $deleteAction = '"commerce/catalog-pricing-rules/delete"';
        }

        $actions = Json::encode($actions);

        $js = <<<JS
var actions = {$actions};

var columns = [
    { name: '__slot:title', title: Craft.t('commerce', 'Name') },
    { name: 'duration', title: Craft.t('commerce', 'Duration') },
    { name: 'effect', title: Craft.t('commerce', 'Effect') },
    { name: 'isPromotionalPrice', title: Craft.t('commerce', 'Is Promotional Price?'),
        callback: function(value) {
            if (value) {
                return '<span data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce', 'Yes'))+'"></span>';
            }
        }
    },
];

new Craft.VueAdminTable({
  actions: actions,
  checkboxes: true,
  columns: columns,
  fullPane: false,
  container: '#pcr-vue-admin-table',
  deleteAction: {$deleteAction},
  emptyMessage: Craft.t('commerce', 'No catalog pricing rules exist yet.'),
  padded: true,
  tableData: {$tableData}
});
JS;

        HtmlStack::js($js, Position::BodyEnd);

        return $this->storeManagementCpScreen($storeHandle)
            ->additionalButtonsHtml($actionButtonHtml)
            ->contentTemplate('commerce/store-management/pricing-rules/index');
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        abort_unless(currentUserElement()?->can($id === null ? 'commerce-createCatalogPricingRules' : 'commerce-editCatalogPricingRules'), 403);

        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        if ($id) {
            $catalogPricingRule = Plugin::getInstance()->getCatalogPricingRules()->getCatalogPricingRuleById($id, $store->id);
            abort_if($catalogPricingRule === null || $catalogPricingRule->storeId !== $store->id, 404);
        } else {
            $catalogPricingRule = \Craft::createObject([
                'class' => CatalogPricingRule::class,
                'storeId' => $store->id,
            ]);

            $purchasableId = request()->input('purchasableId');
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
                [
                    'label' => t('Rule', category: 'commerce'),
                    'url' => '#rule',
                    'class' => array_filter([$variables['catalogPricingRule']->getErrors() ? 'error' : null]),
                ],
                [
                    'label' => t('Conditions', category: 'commerce'),
                    'url' => '#conditions',
                ],
                [
                    'label' => t('Actions', category: 'commerce'),
                    'url' => '#actions',
                    'class' => array_filter([($variables['catalogPricingRule']->getErrors('applyAmount') || $variables['catalogPricingRule']->getErrors('apply')) ? 'error' : null]),
                ],
            ])
            ->contentTemplate('commerce/store-management/pricing-rules/_edit', $variables);
    }

    public function save(Request $request): Response
    {
        $id = $request->input('id');
        $storeId = $request->input('storeId');

        if ($id) {
            $catalogPricingRule = Plugin::getInstance()->getCatalogPricingRules()->getCatalogPricingRuleById($id, $storeId);
            abort_if($catalogPricingRule === null, 404, 'Catalog Pricing Rule not found');
        } else {
            $catalogPricingRule = \Craft::createObject(CatalogPricingRule::class);
        }

        abort_unless(currentUserElement()?->can($catalogPricingRule->id === null ? 'commerce-createCatalogPricingRules' : 'commerce-editCatalogPricingRules'), 403);

        $catalogPricingRule->storeId = $storeId;
        $catalogPricingRule->name = $request->input('name');
        $catalogPricingRule->description = $request->input('description');
        $catalogPricingRule->apply = $request->input('apply');
        $catalogPricingRule->enabled = (bool)$request->input('enabled');
        $catalogPricingRule->isPromotionalPrice = (bool)$request->input('isPromotionalPrice');
        $catalogPricingRule->applyPriceType = $request->input('applyPriceType');

        if (($date = $request->input('dateFrom')) !== null) {
            $catalogPricingRule->dateFrom = DateTimeHelper::toDateTime($date) ?: null;
        }
        if (($date = $request->input('dateTo')) !== null) {
            $catalogPricingRule->dateTo = DateTimeHelper::toDateTime($date) ?: null;
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

        if (Plugin::getInstance()->getCatalogPricingRules()->saveCatalogPricingRule($catalogPricingRule)) {
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
            Plugin::getInstance()->getCatalogPricingRules()->deleteCatalogPricingRuleById($deleteId);
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
                $storeId ??= $rule->storeId;
                $rule->enabled = ($status == 'enabled');
                $rule->save();
            }
        });

        Plugin::getInstance()->getCatalogPricing()->createCatalogPricingJob([
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
        $primaryCurrencyIso = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
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

        return $variables;
    }
}
