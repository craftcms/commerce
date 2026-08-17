<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\models\ShippingCategory;
use craft\commerce\Plugin;
use CraftCms\Cms\Support\Arr;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html as NewHtml;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

readonly class ShippingCategoriesController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        $shippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories($store->id);

        $tableData = [];
        foreach ($shippingCategories as $shippingCategory) {
            $label = Html::encode(t($shippingCategory->name, category: 'site'));
            $tableData[] = [
                'id' => $shippingCategory->id,
                'title' => $label,
                'chip' => Cp::chipHtml($shippingCategory, [
                    'labelHtml' => Html::a($label, $shippingCategory->getCpEditUrl(), [
                        'class' => ['chip-label', 'cell-bold'],
                    ]),
                ]),
                'url' => $shippingCategory->getCpEditUrl(),
                'handle' => $shippingCategory->handle,
                'description' => Html::encode(t($shippingCategory->description, category: 'site')),
                'default' => $shippingCategory->default,
                '_showDelete' => (count($shippingCategories) > 1 && !$shippingCategory->default),
            ];
        }

        $tableData = Json::encode($tableData);

        $js = <<<JS
var columns = [
        { name: 'chip', title: Craft.t('commerce', 'Name') },
        { name: '__slot:handle', title: Craft.t('commerce', 'Handle') },
        { name: 'description', title: Craft.t('commerce', 'Description') },
        {
            name: 'default',
            title: Craft.t('commerce', 'Default'),
            callback: function(value) {
                if (value) {
                    return '<div data-icon="check" title="'+Craft.escapeHtml(Craft.t('commerce','Yes'))+'"></div>';
                }
            }
        },
    ];

    new Craft.VueAdminTable({
        actions: [
        {
            label: '',
            icon: 'settings',
            actions: [
                {
                    label: Craft.t('commerce', 'Set Default Category'),
                    action: 'commerce/shipping-categories/set-default-category',
                    param: 'storeHandle',
                    value: '{$storeHandle}',
                    allowMultiple: false
                }
            ]
        }
    ],
        checkboxes: true,
        columns: columns,
        container: '#shipping-vue-admin-table',
        deleteAction: 'commerce/shipping-categories/delete',
        padded: true,
        tableData: {$tableData},
    });
JS;

        HtmlStack::js($js, Position::BodyEnd);

        return $this->storeManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(Html::a(
                t('New shipping category', category: 'commerce'),
                $store->getStoreSettingsUrl('shippingcategories/new'),
                ['class' => 'btn submit add icon']
            ))
            ->contentHtml(NewHtml::tag('div', '', ['id' => 'shipping-vue-admin-table']));
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        if ($id) {
            $shippingCategory = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($id, $store->id);
            abort_if($shippingCategory === null, 404);
        } else {
            $shippingCategory = \Craft::createObject([
                'class' => ShippingCategory::class,
                'attributes' => ['storeId' => $store->id],
            ]);
        }

        $title = $shippingCategory->id ? $shippingCategory->name : t('Create a new shipping category', category: 'commerce');

        $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        $productTypesOptions = [];
        if (!empty($productTypes)) {
            $productTypesOptions = Arr::mapWithKeys($productTypes, fn($row) => [$row->id => ['label' => $row->name, 'value' => $row->id]]);
        }

        $allShippingCategories = Plugin::getInstance()->getShippingCategories()->getAllShippingCategories($store->id);
        $isDefaultAndOnlyCategory = $id && $allShippingCategories->count() === 1 && $allShippingCategories->firstWhere('id', $id);

        $metaSidebar = '';
        if ($shippingCategory->id) {
            $metaSidebar = Cp::metadataHtml([
                t('Created at') => I18N::getFormatter()->asDatetime($shippingCategory->dateCreated, 'short'),
                t('Updated at') => I18N::getFormatter()->asDatetime($shippingCategory->dateUpdated, 'short'),
            ]);
        }

        return $this->storeManagementCpScreen($storeHandle, false)
            ->title($title)
            ->addCrumb(t('Shipping Categories', category: 'commerce'), $store->getStoreSettingsUrl('shippingcategories'))
            ->action('commerce/shipping-categories/save')
            ->redirectUrl($store->getStoreSettingsUrl('shippingcategories'))
            ->metaSidebarHtml($metaSidebar)
            ->contentTemplate('commerce/store-management/shipping/shippingcategories/_edit', [
                'id' => $id,
                'shippingCategory' => $shippingCategory,
                'productTypes' => $productTypes,
                'storeHandle' => $storeHandle,
                'title' => $title,
                'productTypesOptions' => $productTypesOptions,
                'isDefaultAndOnlyCategory' => $isDefaultAndOnlyCategory,
            ]);
    }

    public function save(Request $request): Response
    {
        $shippingCategory = new ShippingCategory();

        $shippingCategory->id = $request->input('shippingCategoryId');
        $shippingCategory->storeId = $request->input('storeId');
        $shippingCategory->name = $request->input('name');
        $shippingCategory->handle = $request->input('handle');
        $shippingCategory->icon = $request->input('icon');
        $shippingCategory->color = $request->input('color');
        $shippingCategory->description = $request->input('description');
        $shippingCategory->default = (bool)$request->input('default');

        if ($shippingCategory->default) {
            $productTypes = Plugin::getInstance()->getProductTypes()->getAllProductTypes();
        } else {
            $postedProductTypes = $request->input('productTypes', []) ?: [];
            $productTypes = [];
            foreach ($postedProductTypes as $productTypeId) {
                if ($productTypeId && $productType = Plugin::getInstance()->getProductTypes()->getProductTypeById($productTypeId)) {
                    $productTypes[] = $productType;
                }
            }
        }
        $shippingCategory->setProductTypes($productTypes);

        if (!Plugin::getInstance()->getShippingCategories()->saveShippingCategory($shippingCategory)) {
            return $this->asModelFailure(
                $shippingCategory,
                t('Couldn\'t save shipping category.', category: 'commerce'),
                'shippingCategory'
            );
        }

        return $this->asModelSuccess(
            $shippingCategory,
            t('Shipping category saved.', category: 'commerce'),
            'shippingCategory',
            data: [
                'id' => $shippingCategory->id,
                'name' => $shippingCategory->name,
            ]
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
            if (!Plugin::getInstance()->getShippingCategories()->deleteShippingCategoryById($deleteId)) {
                $failedIds[] = $deleteId;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(t('Could not delete {count, number} shipping {count, plural, one{category} other{categories}}.', [
                'count' => count($failedIds),
            ], category: 'commerce'));
        }

        return $this->asSuccess(t('Shipping categories deleted.', category: 'commerce'));
    }

    public function setDefaultCategory(Request $request): Response
    {
        $ids = $request->input('ids');
        $storeHandle = $request->input('storeHandle');
        $store = $storeHandle ? Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle) : null;
        abort_if(!$storeHandle || $store === null, 400, 'Invalid store.');

        if (!empty($ids)) {
            $id = Arr::first($ids);

            $shippingCategory = Plugin::getInstance()->getShippingCategories()->getShippingCategoryById($id, $store->id);
            if ($shippingCategory) {
                $shippingCategory->default = true;
                if (Plugin::getInstance()->getShippingCategories()->saveShippingCategory($shippingCategory)) {
                    return $this->asSuccess(t('Shipping category updated.', category: 'commerce'));
                }
            }
        }

        return $this->asFailure(t('Unable to set default shipping category.', category: 'commerce'));
    }
}
