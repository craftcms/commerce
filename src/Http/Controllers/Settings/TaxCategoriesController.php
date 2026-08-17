<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\models\Store;
use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\View;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html as NewHtml;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
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

        $taxCategories = Plugin::getInstance()->getTaxCategories()->getAllTaxCategories();

        $tableData = [];
        foreach ($taxCategories as $taxCategory) {
            $label = Html::encode(t($taxCategory->name, category: 'site'));
            $taxRates = $taxCategory->getTaxRates($store->id);
            $tableData[] = [
                'id' => $taxCategory->id,
                'title' => $label,
                'chip' => Cp::chipHtml($taxCategory, [
                    'labelHtml' => Html::a($label, $taxCategory->getCpEditUrl($store->id), [
                        'class' => ['chip-label', 'cell-bold'],
                    ]),
                ]),
                'url' => $taxCategory->getCpEditUrl($store->id),
                'handle' => $taxCategory->handle,
                'description' => Html::encode(t($taxCategory->description, category: 'site')),
                'default' => $taxCategory->default,
                '_showDelete' => $taxRates->isEmpty() && (count($taxCategories) > 1 && !$taxCategory->default),
            ];
        }

        \Craft::$app->getView()->registerTranslations('commerce', [
            'Default?',
            'Description',
            'Handle',
            'Name',
            'Set default category',
            'Used By Tax Rates',
            'Used by Tax Rates',
        ]);

        $buttons = Plugin::getInstance()->getTaxes()->taxCategoryActionHtml();
        if (Plugin::getInstance()->getTaxes()->createTaxCategories()) {
            $buttons .= Html::a(t('New tax category', category: 'commerce'), $store->getStoreSettingsUrl('taxcategories/new'), [
                'class' => ['btn', 'submit', 'add', 'icon'],
            ]);
        }

        $tableData = Json::encode($tableData);
        $deleteAction = Plugin::getInstance()->getTaxes()->deleteTaxCategories() ? "'commerce/tax-categories/delete'" : 'null';

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

        \Craft::$app->getView()->registerJs($js, View::POS_END);

        return $this->storeManagementCpScreen($storeHandle, hasStoreSwitcher: false)
            ->additionalButtonsHtml($buttons)
            ->contentHtml(NewHtml::tag('div', '', ['id' => 'tax-vue-admin-table']));
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();

        if ($id) {
            $taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($id);
            abort_if($taxCategory === null, 404);
        } else {
            $taxCategory = new TaxCategory();
        }

        $title = $taxCategory->id ? $taxCategory->name : t('Create a new tax category', category: 'commerce');

        $productTypesOptions = [];
        if (!empty($productTypes)) {
            $productTypesOptions = ArrayHelper::map($productTypes, 'id', fn($row) => ['label' => $row->name, 'value' => $row->id]);
        }

        $allTaxCategoryIds = array_keys(Plugin::getInstance()->getTaxCategories()->getAllTaxCategories());
        $isDefaultAndOnlyCategory = $id && count($allTaxCategoryIds) === 1 && in_array($id, $allTaxCategoryIds);

        $taxRates = collect();
        Plugin::getInstance()->getStores()->getAllStores()->each(fn(Store $s) => $taxRates->push(...Plugin::getInstance()->getTaxRates()->getAllTaxRates($s->id)->all()));

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

        $taxCategory->id = $request->input('taxCategoryId');
        $taxCategory->name = $request->input('name');
        $taxCategory->handle = $request->input('handle');
        $taxCategory->icon = $request->input('icon');
        $taxCategory->color = $request->input('color');
        $taxCategory->description = $request->input('description');
        $taxCategory->default = (bool)$request->input('default');

        $postedProductTypes = $request->input('productTypes', []) ?: [];
        $productTypes = [];
        foreach ($postedProductTypes as $productTypeId) {
            if ($productTypeId && $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId)) {
                $productTypes[] = $productType;
            }
        }
        $taxCategory->setProductTypes($productTypes);

        if (!Plugin::getInstance()->getTaxCategories()->saveTaxCategory($taxCategory)) {
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
            if (!Plugin::getInstance()->getTaxCategories()->deleteTaxCategoryById($deleteId)) {
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

        $id = ArrayHelper::firstValue($ids);

        $taxCategory = Plugin::getInstance()->getTaxCategories()->getTaxCategoryById($id);
        if ($taxCategory) {
            $taxCategory->default = true;
            if (Plugin::getInstance()->getTaxCategories()->saveTaxCategory($taxCategory)) {
                return $this->asSuccess(t('Tax category updated.', category: 'commerce'));
            }
        }

        return $this->asFailure(t('Unable to set default tax category.', category: 'commerce'));
    }
}
