<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\helpers\Cp as CommerceCp;
use craft\commerce\helpers\Localization;
use craft\helpers\Cp;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html as NewHtml;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Translation\Locale;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Tax\Models\TaxRate;
use CraftCms\Commerce\Tax\Records\TaxRate as TaxRateRecord;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tax\Taxes;
use CraftCms\Commerce\Tax\TaxRates;

use CraftCms\Commerce\Tax\TaxZones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class TaxRatesController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        $taxRates = app(TaxRates::class)->getAllTaxRates($store->id);

        // Preload all zone and category data for listing.
        app(TaxZones::class)->getAllTaxZones($store->id);
        app(TaxCategories::class)->getAllTaxCategories();

        $tableData = [];
        foreach ($taxRates as $taxRate) {
            $label = NewHtml::encode(t($taxRate->name, category: 'site'));
            $tableData[] = [
                'id' => $taxRate->id,
                'status' => $taxRate->enabled,
                'title' => NewHtml::a($label, $taxRate->getCpEditUrl()),
                'url' => $taxRate->getCpEditUrl(),
                'rate' => $taxRate->getRateAsPercent(),
                'included' => $taxRate->include,
                'removeIncluded' => $taxRate->removeIncluded,
                'vat' => $taxRate->isVat,
                'zone' => $taxRate->isEverywhere ? t('Everywhere', category: 'commerce') : ($taxRate->taxZone ? NewHtml::encode($taxRate->taxZone->name) : ''),
                'category' => $taxRate->taxCategory ? Cp::chipHtml($taxRate->taxCategory) : '',
            ];
        }

        $buttonsHtml = app(Taxes::class)->taxRateActionHtml();

        if (app(Taxes::class)->createTaxRates()) {
            $buttonsHtml .= NewHtml::a(t('New tax rate', category: 'commerce'), "commerce/store-management/$storeHandle/taxrates/new", [
                'class' => 'btn submit add icon',
            ]);
        }

        $tableData = Json::encode($tableData, JSON_UNESCAPED_UNICODE);
        $deleteAction = app(Taxes::class)->deleteTaxRates() ? 'commerce/tax-rates/delete' : null;

        $js = <<<JS
var columns = [
    { name: 'title', title: Craft.t('commerce', 'Name') },
    { name: 'rate', title: Craft.t('commerce', 'Rate') },
    { name: 'included', title: Craft.t('commerce', 'Include in price?'), callback: function(value) {
      if (value) {
          return '<span data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce', 'Yes'))+'"></span>';
      }
    } },
    { name: 'removeIncluded', title: Craft.t('commerce', 'Remove from price?'), callback: function(value) {
            if (value) {
                return '<span data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce', 'Yes'))+'"></span>';
            }
        } },
    { name: 'zone', title: Craft.t('commerce', 'Tax Zone') },
    { name: 'category', title: Craft.t('commerce', 'Tax Category') }
];

var actions = [
  {
    label: Craft.t('commerce', 'Set status'),
    actions: [
      {
        label: Craft.t('commerce', 'Enabled'),
        action: 'commerce/tax-rates/update-status',
        param: 'status',
        value: 'enabled',
        status: 'enabled'
      },
      {
        label: Craft.t('commerce', 'Disabled'),
        action: 'commerce/tax-rates/update-status',
        param: 'status',
        value: 'disabled',
        status: 'disabled'
      }
    ]
  }
];

new Craft.VueAdminTable({
    columns: columns,
    actions: actions,
    checkboxes: true,
    container: '#taxrate-vue-admin-table',
    deleteAction: '{$deleteAction}',
    tableData: {$tableData},
});
JS;

        HtmlStack::js($js, Position::BodyEnd);

        return $this->storeManagementCpScreen($storeHandle)
            ->additionalButtonsHtml($buttonsHtml)
            ->contentHtml(NewHtml::tag('div', '', ['id' => 'taxrate-vue-admin-table']));
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        abort_unless(app(Taxes::class)->viewTaxRates(), 403, 'Tax engine does not permit you to perform this action');

        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;
        $percentSymbol = I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);


        if ($id) {
            $taxRate = app(TaxRates::class)->getTaxRateById($id, $store->id);
            abort_if($taxRate === null, 404);
        } else {
            $taxRate = \Craft::createObject([
                'class' => TaxRate::class,
                'storeId' => $store->id,
            ]);
        }

        $title = $taxRate->id ? $taxRate->name : t('Create a new tax rate', category: 'commerce');

        $variables = compact('taxRate', 'store', 'storeHandle', 'percentSymbol');

        $taxZone = null;
        if ($taxRate->taxZoneId) {
            $taxZone = app(TaxZones::class)->getTaxZoneById($taxRate->taxZoneId, $store->id);
        }

        $taxCategory = null;
        if ($taxRate->taxCategoryId) {
            $taxCategory = app(TaxCategories::class)->getTaxCategoryById($taxRate->taxCategoryId);
        }

        $variables['taxZoneField'] = CommerceCp::taxZoneFieldHtml([
            'label' => t('Tax Zone', category: 'commerce'),
            'instructions' => t('Select a tax zone. If empty, this rate will match anywhere.', category: 'commerce'),
            'id' => 'taxZoneId',
            'name' => 'taxZoneId',
            'value' => $taxZone,
            'errors' => $taxRate->getErrors('taxZoneId'),
            'required' => false,
            'limit' => 1,
            'storeId' => $store->id,
            'storeHandle' => $storeHandle,
        ]);

        $variables['taxCategoryField'] = CommerceCp::taxCategoryFieldHtml([
            'label' => t('Tax Category', category: 'commerce'),
            'instructions' => t('Select a tax category.', category: 'commerce'),
            'id' => 'taxCategoryId',
            'name' => 'taxCategoryId',
            'value' => $taxCategory,
            'errors' => $taxRate->getErrors('taxCategoryId'),
            'required' => true,
            'limit' => 1,
            'storeHandle' => $storeHandle,
        ]);

        $taxable = [];
        $taxable[TaxRateRecord::TAXABLE_PURCHASABLE] = t('Unit price (minus discounts)', category: 'commerce');
        $taxable[TaxRateRecord::TAXABLE_PRICE] = t('Line item price (minus discounts)', category: 'commerce');
        $taxable[TaxRateRecord::TAXABLE_SHIPPING] = t('Line item shipping cost', category: 'commerce');
        $taxable[TaxRateRecord::TAXABLE_PRICE_SHIPPING] = t('Both (Line item price + Line item shipping costs)', category: 'commerce');
        $taxable[TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING] = t('Order total shipping cost', category: 'commerce');
        $taxable[TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE] = t('Order total taxable price (Line item subtotal + Total discounts + Total shipping)', category: 'commerce');
        $variables['taxables'] = $taxable;
        $variables['taxablesNoTaxCategory'] = TaxRateRecord::ORDER_TAXABALES;

        $variables['hideTaxCategory'] = false;
        if ($variables['taxRate']->id && in_array($variables['taxRate']->taxable, $variables['taxablesNoTaxCategory'], false)) {
            $variables['hideTaxCategory'] = true;
        }

        $variables['taxIdValidators'] = [];
        $taxIdValidators = app(Taxes::class)->getEnabledTaxIdValidators();
        foreach ($taxIdValidators as $validator) {
            $variables['taxIdValidators'][] = $validator;
        }

        return $this->storeManagementCpScreen($storeHandle, false)
            ->title($title)
            ->addCrumb(t('Tax Rates', category: 'commerce'), $store->getStoreSettingsUrl('taxrates'))
            ->selectedSubnavItem('store-management')
            ->action('commerce/tax-rates/save')
            ->redirectUrl($store->getStoreSettingsUrl('taxrates'))
            ->metaSidebarTemplate('commerce/store-management/tax/taxrates/_sidebar', $variables)
            ->contentTemplate('commerce/store-management/tax/taxrates/_edit', $variables);
    }

    public function save(Request $request): Response
    {
        abort_unless(app(Taxes::class)->editTaxRates(), 403, 'Tax engine does not permit you to perform this action');

        $taxRate = new TaxRate();

        $taxRate->id = $request->input('taxRateId') ? (int)$request->input('taxRateId') : null;
        $taxRate->storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $taxRate->name = $request->input('name');
        $taxRate->code = $request->input('code');
        $taxRate->include = (bool)$request->input('include');
        $taxRate->removeIncluded = (bool)$request->input('removeIncluded');
        $taxRate->removeVatIncluded = (bool)$request->input('removeVatIncluded');
        $taxRate->taxable = $request->input('taxable');
        $taxRate->taxCategoryId = (int)$request->input('taxCategoryId') ?: null;
        $taxRate->taxZoneId = (int)$request->input('taxZoneId') ?: null;
        $taxRate->rate = Localization::normalizePercentage($request->input('rate'));
        $taxRate->enabled = (bool)$request->input('enabled');

        $validators = collect($request->input('taxIdValidators'))->filter(fn($enabled) => (bool)$enabled)->keys();
        $taxRate->taxIdValidators = $validators->toArray();

        if (!app(TaxRates::class)->saveTaxRate($taxRate)) {
            return $this->asModelFailure($taxRate, t('Couldn\'t save tax rate.', category: 'commerce'), 'taxRate');
        }

        return $this->asModelSuccess($taxRate, t('Tax rate saved.', category: 'commerce'), 'taxRate');
    }

    public function delete(Request $request): Response
    {
        abort_unless(app(Taxes::class)->deleteTaxRates(), 403, 'Tax engine does not permit you to perform this action');
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing tax rate id');

        app(TaxRates::class)->deleteTaxRateById((int)$id);
        return $this->asSuccess();
    }

    public function updateStatus(Request $request): Response
    {
        $ids = $request->input('ids');
        $status = $request->input('status');

        abort_if(empty($ids), 400, 'Missing ids');

        DB::transaction(function() use ($ids, $status) {
            $taxRates = TaxRateRecord::whereIn('id', $ids)->get();

            foreach ($taxRates as $taxRate) {
                $taxRate->enabled = ($status == 'enabled');
                $taxRate->save();
            }
        });

        return $this->asSuccess(t('Tax rates updated.', category: 'commerce'));
    }
}
