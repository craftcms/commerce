<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use craft\helpers\Cp;
use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\ColorSelect;
use CraftCms\Cms\Form\Controls\Handle;
use CraftCms\Cms\Form\Controls\IconPicker;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use CraftCms\Commerce\Shipping\Data\ShippingCategory;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class ShippingCategoriesController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Shipping Categories', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('shippingcategories')];
    }

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $shippingCategories = app(ShippingCategories::class)->getAllShippingCategories($store->id);

        $rows = $shippingCategories
            ->map(fn(ShippingCategory $shippingCategory) => [
                'id' => $shippingCategory->id,
                'name' => ['html' => Cp::chipHtml($shippingCategory, [
                    'labelHtml' => Html::a(Html::encode(t($shippingCategory->name, category: 'site')), $shippingCategory->getCpEditUrl(), ['class' => 'cell-bold']),
                ])],
                'handle' => $shippingCategory->handle,
                'description' => t($shippingCategory->description, category: 'site'),
                'default' => $shippingCategory->default ? ['icon' => 'check', 'label' => t('Yes')] : '',
                '_deletable' => $shippingCategories->count() > 1 && !$shippingCategory->default,
            ])
            ->values()
            ->all();

        $nodes = [
            Table::make('shipping-categories')
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'handle', 'label' => t('Handle')],
                    ['key' => 'description', 'label' => t('Description', category: 'commerce')],
                    ['key' => 'default', 'label' => t('Default Category', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No shipping categories exist yet.', category: 'commerce'))
                ->createAction(t('New shipping category', category: 'commerce'), $store->getStoreSettingsUrl('shippingcategories/new'))
                ->deletable(action([self::class, 'delete'])),
        ];

        $title = t('Shipping Categories', category: 'commerce');

        return $this->cpScreenResponse($store)
            ->title($title)
            ->crumbs($this->crumbs($store))
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
            ]);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        if ($id) {
            $shippingCategory = app(ShippingCategories::class)->getShippingCategoryById($id, $store->id);
            abort_if($shippingCategory === null, 404);
        } else {
            $shippingCategory = new ShippingCategory(['storeId' => $store->id]);
        }

        $title = $shippingCategory->id ? $shippingCategory->name : t('Create a new shipping category', category: 'commerce');
        $lockDefault = $this->lockDefault($shippingCategory, $store);

        $formatter = app(Formatter::class);
        $metadataHtml = $shippingCategory->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($shippingCategory->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($shippingCategory->dateUpdated, 'short'),
        ]) : null;

        $values = $this->initialValues($shippingCategory, $store);

        $form = $this->formResolver->resolve(
            $this->buildForm($shippingCategory, $values, $lockDefault),
            new FormContext(values: $values, refreshable: true),
        );

        return $this->cpScreenResponse($store)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($shippingCategory->id ? [['label' => $title]] : [])))
            ->action('commerce/shipping-categories/save')
            ->redirectUrl($store->getStoreSettingsUrl('shippingcategories'))
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
     * client, so toggling "Default Category" can force every product type on (and disable
     * further picking) without a full page reload.
     */
    public function renderForm(Request $request): JsonResponse
    {
        $request->validate([
            'values' => ['required', 'array'],
            'values.storeId' => ['required', 'integer'],
            'values.shippingCategoryId' => ['nullable', 'integer'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');
        $store = app(Stores::class)->getStoreById((int)$values['storeId']);
        abort_if($store === null, 404);

        $shippingCategoryId = $values['shippingCategoryId'] ?? null;
        if ($shippingCategoryId) {
            $shippingCategory = app(ShippingCategories::class)->getShippingCategoryById((int)$shippingCategoryId, $store->id);
            abort_if($shippingCategory === null, 404);
        } else {
            $shippingCategory = new ShippingCategory(['storeId' => $store->id]);
        }

        $lockDefault = $this->lockDefault($shippingCategory, $store);

        // The client only posts values for controls currently in the rendered tree, so layer
        // them over this model's real defaults before re-resolving — otherwise a field that's
        // about to be revealed (or the productTypes list, once `default` forces it) would fall
        // back to empty instead of its actual value.
        $values = array_replace($this->initialValues($shippingCategory, $store), $values);

        // `default` just flipped on (that's what triggered this round trip): the productTypes
        // control is about to render disabled, so its stale posted selection — captured before
        // the toggle — needs overriding to "every product type" rather than merged in as-is.
        if ($values['default'] ?? false) {
            $values['productTypes'] = array_column(app(ProductTypes::class)->getAllProductTypes(), 'id');
        }

        $form = $this->formResolver->resolve(
            $this->buildForm($shippingCategory, $values, $lockDefault),
            new FormContext(values: $values, refreshable: true),
        );

        return new JsonResponse(['form' => $form]);
    }

    private function lockDefault(ShippingCategory $shippingCategory, Store $store): bool
    {
        if (!$shippingCategory->id) {
            return false;
        }

        $allShippingCategories = app(ShippingCategories::class)->getAllShippingCategories($store->id);
        $isOnlyCategory = $allShippingCategories->count() === 1 && $allShippingCategories->firstWhere('id', $shippingCategory->id);

        return $isOnlyCategory || $shippingCategory->default;
    }

    /** @return array<string, mixed> */
    private function initialValues(ShippingCategory $shippingCategory, Store $store): array
    {
        // A default category is available to every product type, whether or not it's ever
        // been explicitly assigned to them — mirrors save()'s own handling below.
        $productTypes = $shippingCategory->default
            ? array_column(app(ProductTypes::class)->getAllProductTypes(), 'id')
            : $shippingCategory->getProductTypeIds();

        return [
            'storeId' => $store->id,
            'shippingCategoryId' => $shippingCategory->id,
            'name' => $shippingCategory->name,
            'handle' => $shippingCategory->handle,
            'icon' => $shippingCategory->icon,
            'color' => $shippingCategory->color ?? '',
            'description' => $shippingCategory->description,
            'productTypes' => $productTypes,
            'default' => $shippingCategory->default,
            'defaultDisplay' => $shippingCategory->default,
        ];
    }

    /** @param array<string, mixed> $values */
    private function buildForm(ShippingCategory $shippingCategory, array $values, bool $lockDefault): Form
    {
        $isDefault = (bool)($values['default'] ?? false);

        $handle = Handle::make('handle');
        if (!$shippingCategory->id) {
            $handle->source('name');
        }

        $productTypesOptions = array_values(array_map(
            fn($productType) => ['label' => $productType->name, 'value' => $productType->id],
            app(ProductTypes::class)->getAllProductTypes(),
        ));

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($shippingCategory->id) {
            $formNodes[] = HiddenField::make('shippingCategoryId');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('What this shipping category will be called in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Handle', category: 'commerce'), $handle)
            ->instructions(t('How you\'ll refer to this shipping category in the templates.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Description', category: 'commerce'), Text::make('description'));
        $formNodes[] = Field::make(t('Icon', category: 'app'), IconPicker::make('icon'));
        $formNodes[] = Field::make(t('Color', category: 'commerce'), ColorSelect::make('color')
            ->colors($this->colorPalette())
            ->allowTransparent()
            ->blankLabel(t('No color', category: 'app')));

        $productTypesControl = Choice::make('productTypes')->multiple()->options($productTypesOptions);
        if ($isDefault) {
            $productTypesControl->mode(ControlMode::Disabled);
        }

        $productTypesField = Field::make(t('Available to Product Types', category: 'commerce'), $productTypesControl)
            ->instructions($isDefault
                ? t('The default shipping category is automatically available to all product types.', category: 'commerce')
                : t('Which product types should this category be available to?', category: 'commerce'));

        if ($productTypesOptions === []) {
            $productTypesField->warning(
                t('There aren\'t any product types to select yet.', category: 'commerce') . ' ' .
                Html::a(t('Create a product type', category: 'commerce'), 'commerce/settings/producttypes/new', ['class' => 'go']),
            );
        }

        $formNodes[] = $productTypesField;

        $defaultKey = $lockDefault ? 'defaultDisplay' : 'default';
        $defaultControl = Lightswitch::make($defaultKey)->mode($lockDefault ? ControlMode::Disabled : ControlMode::Editable);
        if (!$lockDefault) {
            $defaultControl->reactive();
        }

        $formNodes[] = Field::make(t('Default Category', category: 'commerce'), $defaultControl)
            ->instructions(t('This category will be used as the default for all purchasables in this store.', category: 'commerce'));

        if ($lockDefault) {
            $formNodes[] = HiddenField::make('default');
        }

        return Form::make($formNodes);
    }

    public function save(Request $request): Response
    {
        $shippingCategory = new ShippingCategory();

        $shippingCategoryId = $request->input('shippingCategoryId');
        $shippingCategory->id = $shippingCategoryId ? (int)$shippingCategoryId : null;
        $storeId = $request->input('storeId');
        $shippingCategory->storeId = $storeId ? (int)$storeId : null;
        $this->requireStoreAccess($shippingCategory->storeId);
        $shippingCategory->name = $request->input('name');
        $shippingCategory->handle = $request->input('handle');
        $shippingCategory->icon = $request->input('icon');
        // '__blank__' is ColorSelect's internal sentinel for "no color" selected — it should
        // never reach here (the client translates it back to '' before posting), but guard
        // against it anyway for a genuinely JS-less submission.
        $color = $request->input('color');
        $shippingCategory->color = ($color && $color !== '__blank__') ? $color : null;
        $shippingCategory->description = $request->input('description');
        $shippingCategory->default = (bool)$request->input('default');

        if ($shippingCategory->default) {
            $productTypes = app(ProductTypes::class)->getAllProductTypes();
        } else {
            $postedProductTypes = $request->input('productTypes', []) ?: [];
            $productTypes = [];
            foreach ($postedProductTypes as $productTypeId) {
                if ($productTypeId && $productType = app(ProductTypes::class)->getProductTypeById((int)$productTypeId)) {
                    $productTypes[] = $productType;
                }
            }
        }
        $shippingCategory->setProductTypes($productTypes);

        if (!app(ShippingCategories::class)->saveShippingCategory($shippingCategory)) {
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
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing shipping category id');

        // Resolve first so we can check the record's own store before deleting it — mirrors
        // the store-access guard save()/setDefaultCategory() already carry.
        $shippingCategory = app(ShippingCategories::class)->getShippingCategoryById((int)$id);
        if ($shippingCategory) {
            $this->requireStoreAccess($shippingCategory->storeId);
        }

        if (!$shippingCategory || !app(ShippingCategories::class)->deleteShippingCategoryById((int)$id)) {
            return $this->asFailure(t('Could not delete shipping category.', category: 'commerce'));
        }

        return $this->asSuccess(t('Shipping category deleted.', category: 'commerce'));
    }

    public function setDefaultCategory(Request $request): Response
    {
        $ids = $request->input('ids');
        $storeHandle = $request->input('storeHandle');
        $store = $storeHandle ? app(Stores::class)->getStoreByHandle($storeHandle) : null;
        abort_if(!$storeHandle || $store === null, 400, 'Invalid store.');
        $this->requireStoreAccess($store->id);

        if (!empty($ids)) {
            $id = Arr::first($ids);

            $shippingCategory = app(ShippingCategories::class)->getShippingCategoryById((int)$id, $store->id);
            if ($shippingCategory) {
                $shippingCategory->default = true;
                if (app(ShippingCategories::class)->saveShippingCategory($shippingCategory)) {
                    return $this->asSuccess(t('Shipping category updated.', category: 'commerce'));
                }
            }
        }

        return $this->asFailure(t('Unable to set default shipping category.', category: 'commerce'));
    }
}
