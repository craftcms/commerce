<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\helpers\Cp;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html as NewHtml;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\Data\TaxCategory;

use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tax\Taxes;
use CraftCms\Commerce\Tax\TaxRates;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class TaxCategoriesController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $taxCategories = app(TaxCategories::class)->getAllTaxCategories();

        $tableData = [];
        foreach ($taxCategories as $taxCategory) {
            $label = NewHtml::encode(t($taxCategory->name, category: 'site'));
            $taxRates = $taxCategory->getTaxRates($store->id);
            $tableData[] = [
                'id' => $taxCategory->id,
                'title' => $label,
                'chip' => Cp::chipHtml($taxCategory, [
                    'labelHtml' => NewHtml::a($label, $taxCategory->getCpEditUrl($store->id), [
                        'class' => ['chip-label', 'cell-bold'],
                    ]),
                ]),
                'url' => $taxCategory->getCpEditUrl($store->id),
                'handle' => $taxCategory->handle,
                'description' => NewHtml::encode(t($taxCategory->description, category: 'site')),
                'default' => $taxCategory->default,
                '_showDelete' => $taxRates->isEmpty() && (count($taxCategories) > 1 && !$taxCategory->default),
            ];
        }

        $buttons = app(Taxes::class)->taxCategoryActionHtml();
        if (app(Taxes::class)->createTaxCategories()) {
            $buttons .= NewHtml::a(t('New tax category', category: 'commerce'), $store->getStoreSettingsUrl('taxcategories/new'), [
                'class' => ['btn', 'submit', 'add', 'icon'],
            ]);
        }

        $tableData = Json::encode($tableData);
        $deleteAction = app(Taxes::class)->deleteTaxCategories() ? "'commerce/tax-categories/delete'" : 'null';

        $js = <<<JS
    var columns = [
        { name: 'chip', title: Craft.t('commerce', 'Name') },
        { name: '__slot:handle', title: Craft.t('commerce', 'Handle') },
        { name: 'description', title: Craft.t('commerce', 'Description') },
        {
            name: 'default',
            title: Craft.t('commerce', 'Default?'),
            callback: function(value) {
                if (value) {
                    return '<div data-icon="check"></div>';
                }
            }
        },
    ];

    var actions = [
        {
            label: '',
            icon: 'settings',
            actions: [
                {
                    label: Craft.t('commerce', 'Set default category'),
                    action: 'commerce/tax-categories/set-default-category',
                    param: 'default',
                    value: 1,
                    allowMultiple: false
                }
            ]
        }
    ];

    new Craft.VueAdminTable({
        columns: columns,
        checkboxes: true,
        actions: actions,
        padded: true,
        container: '#tax-vue-admin-table',
        deleteAction: {$deleteAction},
        tableData: {$tableData},
    });
JS;

        HtmlStack::js($js, Position::BodyEnd);

        return $this->storeManagementCpScreen($storeHandle, hasStoreSwitcher: false)
            ->additionalButtonsHtml($buttons)
            ->contentHtml(NewHtml::tag('div', '', ['id' => 'tax-vue-admin-table']));
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        $productTypes = app(ProductTypes::class)->getAllProductTypes();

        if ($id) {
            $taxCategory = app(TaxCategories::class)->getTaxCategoryById($id);
            abort_if($taxCategory === null, 404);
        } else {
            $taxCategory = new TaxCategory();
        }

        $title = $taxCategory->id ? $taxCategory->name : t('Create a new tax category', category: 'commerce');

        $productTypesOptions = [];
        if (!empty($productTypes)) {
            $productTypesOptions = Arr::mapWithKeys($productTypes, fn($row) => [$row->id => ['label' => $row->name, 'value' => $row->id]]);
        }

        $allTaxCategoryIds = array_keys(app(TaxCategories::class)->getAllTaxCategories());
        $isDefaultAndOnlyCategory = $id && count($allTaxCategoryIds) === 1 && in_array($id, $allTaxCategoryIds);

        $taxRates = collect();
        app(Stores::class)->getAllStores()->each(fn(Store $s) => $taxRates->push(...app(TaxRates::class)->getAllTaxRates($s->id)->all()));

        $metaSidebar = '';
        if ($taxCategory->id) {
            $metaSidebar = Cp::metadataHtml([
                t('Created at') => I18N::getFormatter()->asDatetime($taxCategory->dateCreated, 'short'),
                t('Updated at') => I18N::getFormatter()->asDatetime($taxCategory->dateUpdated, 'short'),
            ]);
        }

        return $this->storeManagementCpScreen($storeHandle, false, false)
            ->title($title)
            ->addCrumb(t('Tax Categories', category: 'commerce'), $store->getStoreSettingsUrl('taxcategories'))
            ->action('commerce/tax-categories/save')
            ->redirectUrl($store->getStoreSettingsUrl('taxcategories'))
            ->metaSidebarHtml($metaSidebar)
            ->contentTemplate('commerce/store-management/tax/taxcategories/_edit', [
                'taxCategory' => $taxCategory,
                'productTypes' => $productTypes,
                'productTypesOptions' => $productTypesOptions,
                'isDefaultAndOnlyCategory' => $isDefaultAndOnlyCategory,
                'taxRates' => $taxRates,
                'store' => $store,
            ]);
    }

    public function save(Request $request): Response
    {
        $taxCategory = new TaxCategory();

        $taxCategory->id = $request->input('taxCategoryId') ? (int)$request->input('taxCategoryId') : null;
        $taxCategory->name = $request->input('name');
        $taxCategory->handle = $request->input('handle');
        $taxCategory->icon = $request->input('icon');
        $taxCategory->color = $request->input('color');
        $taxCategory->description = $request->input('description');
        $taxCategory->default = (bool)$request->input('default');

        $postedProductTypes = $request->input('productTypes', []) ?: [];
        $productTypes = [];
        foreach ($postedProductTypes as $productTypeId) {
            if ($productTypeId && $productType = app(ProductTypes::class)->getProductTypeById((int)$productTypeId)) {
                $productTypes[] = $productType;
            }
        }
        $taxCategory->setProductTypes($productTypes);

        if (!app(TaxCategories::class)->saveTaxCategory($taxCategory)) {
            return $this->asModelFailure(
                $taxCategory,
                t('Couldn\'t save tax category.', category: 'commerce'),
                'taxCategory'
            );
        }

        return $this->asModelSuccess(
            $taxCategory,
            t('Tax category saved.', category: 'commerce'),
            'taxCategory'
        );
    }

    public function delete(Request $request): Response
    {
        $id = $request->input('id');
        $ids = $request->input('ids');

        abort_if((!$id && empty($ids)) || ($id && !empty($ids)), 400, 'id or ids must be specified.');

        if ($id) {
            abort_unless($request->expectsJson(), 400);
            $ids = [$id];
        }

        $failedIds = [];
        foreach ($ids as $deleteId) {
            if (!app(TaxCategories::class)->deleteTaxCategoryById((int)$deleteId)) {
                $failedIds[] = $deleteId;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(t('Could not delete {count, number} tax {count, plural, one{category} other{categories}}.', [
                'count' => count($failedIds),
            ], category: 'commerce'));
        }

        return $this->asSuccess(t('Tax categories deleted.', category: 'commerce'));
    }

    public function setDefaultCategory(Request $request): Response
    {
        $ids = $request->input('ids');
        abort_if(empty($ids), 400, 'Missing ids');

        $id = Arr::first($ids);

        $taxCategory = app(TaxCategories::class)->getTaxCategoryById((int)$id);
        if ($taxCategory) {
            $taxCategory->default = true;
            if (app(TaxCategories::class)->saveTaxCategory($taxCategory)) {
                return $this->asSuccess(t('Tax category updated.', category: 'commerce'));
            }
        }

        return $this->asFailure(t('Unable to set default tax category.', category: 'commerce'));
    }
}
