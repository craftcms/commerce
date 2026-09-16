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
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\Data\TaxCategory;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tax\Taxes;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

/**
 * Tax categories are shared across every store (they're products' own data, not
 * per-store configuration), so unlike the rest of store-management this screen's
 * store-switcher is suppressed — see {@see showsStoreSwitcher()} — even though it's
 * still reached through a store-handled URL.
 */
readonly class TaxCategoriesController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Tax Categories', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('taxcategories')];
    }

    #[\Override]
    protected function showsStoreSwitcher(): bool
    {
        return false;
    }

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $taxCategories = app(TaxCategories::class)->getAllTaxCategories();
        $canDelete = app(Taxes::class)->deleteTaxCategories();

        $rows = array_map(function(TaxCategory $taxCategory) use ($store, $taxCategories, $canDelete) {
            $label = Html::encode(t($taxCategory->name, category: 'site'));
            $taxRates = $taxCategory->getTaxRates($store->id);

            return [
                'id' => $taxCategory->id,
                'name' => ['html' => Cp::chipHtml($taxCategory, [
                    'labelHtml' => Html::a($label, $taxCategory->getCpEditUrl($store->id), ['class' => 'cell-bold']),
                ])],
                'handle' => $taxCategory->handle,
                'description' => t($taxCategory->description, category: 'site'),
                'default' => $taxCategory->default ? ['icon' => 'check', 'label' => t('Yes')] : '',
                '_deletable' => $canDelete && $taxRates->isEmpty() && count($taxCategories) > 1 && !$taxCategory->default,
            ];
        }, $taxCategories);

        $nodes = [
            Table::make('tax-categories')
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'handle', 'label' => t('Handle')],
                    ['key' => 'description', 'label' => t('Description', category: 'commerce')],
                    ['key' => 'default', 'label' => t('Default Category', category: 'commerce')],
                ])
                ->rows(array_values($rows))
                ->emptyMessage(t('No tax categories exist yet.', category: 'commerce'))
                ->when(
                    app(Taxes::class)->createTaxCategories(),
                    fn(Table $table) => $table->createAction(t('New tax category', category: 'commerce'), $store->getStoreSettingsUrl('taxcategories/new')),
                )
                ->when($canDelete, fn(Table $table) => $table->deletable(action([self::class, 'delete']), bulk: true)),
        ];

        $title = t('Tax Categories', category: 'commerce');
        $engineButtonsHtml = app(Taxes::class)->taxCategoryActionHtml();

        return $this->cpScreenResponse($store)
            ->title($title)
            ->crumbs($this->crumbs($store))
            ->when($engineButtonsHtml !== '', fn(CpScreenResponse $screen) => $screen->additionalButtonsHtml($engineButtonsHtml))
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
            ]);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        if ($id) {
            $taxCategory = app(TaxCategories::class)->getTaxCategoryById($id);
            abort_if($taxCategory === null, 404);
        } else {
            $taxCategory = new TaxCategory();
        }

        $title = $taxCategory->id ? $taxCategory->name : t('Create a new tax category', category: 'commerce');

        $productTypes = app(ProductTypes::class)->getAllProductTypes();
        $productTypesOptions = array_values(array_map(
            fn($productType) => ['label' => $productType->name, 'value' => $productType->id],
            $productTypes,
        ));

        $allTaxCategoryIds = array_keys(app(TaxCategories::class)->getAllTaxCategories());
        $isDefaultAndOnlyCategory = $id && count($allTaxCategoryIds) === 1 && in_array($id, $allTaxCategoryIds);

        $taxRates = collect();
        if ($taxCategory->id) {
            app(Stores::class)->getAllStores()->each(fn(Store $s) => $taxRates->push(...$taxCategory->getTaxRates($s->id)->all()));
        }

        $formatter = app(Formatter::class);
        $metadataHtml = $taxCategory->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($taxCategory->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($taxCategory->dateUpdated, 'short'),
        ]) : null;

        $handle = Handle::make('handle');
        if (!$taxCategory->id) {
            $handle->source('name');
        }

        $lockDefault = $isDefaultAndOnlyCategory || ($taxCategory->id && $taxCategory->default);

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($taxCategory->id) {
            $formNodes[] = HiddenField::make('taxCategoryId');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('What this tax category will be called in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Handle', category: 'commerce'), $handle)
            ->instructions(t('How you’ll refer to this tax category in the templates.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Icon', category: 'app'), IconPicker::make('icon'));
        $formNodes[] = Field::make(t('Color', category: 'commerce'), ColorSelect::make('color')
            ->colors($this->colorPalette())
            ->allowTransparent()
            ->blankLabel(t('No color', category: 'app')));
        $formNodes[] = Field::make(t('Description', category: 'commerce'), Text::make('description'));

        $productTypesField = Field::make(
            t('Available to Product Types', category: 'commerce'),
            Choice::make('productTypes')->multiple()->options($productTypesOptions),
        )->instructions(t('Which product types should this category be available to?', category: 'commerce'));

        if ($productTypesOptions === []) {
            $productTypesField->warning(
                t('There aren’t any product types to select yet.', category: 'commerce') . ' ' .
                Html::a(t('Create a product type', category: 'commerce'), 'commerce/settings/producttypes/new', ['class' => 'go']),
            );
        }

        $formNodes[] = $productTypesField;

        // A locked default can't be un-set here (it's the only category, or already the
        // default), so the interactive control moves to a display-only path and the real
        // `default` key posts via a paired HiddenField instead — a Disabled control renders
        // `name=null` and submits nothing on its own.
        $defaultKey = $lockDefault ? 'defaultDisplay' : 'default';
        $defaultField = Field::make(
            t('Default Category', category: 'commerce'),
            Lightswitch::make($defaultKey)->mode($lockDefault ? ControlMode::Disabled : ControlMode::Editable),
        )->instructions(t('New products default to the first tax category available to them. If none are available, this category will be used.', category: 'commerce'));

        $formNodes[] = $defaultField;

        if ($lockDefault) {
            $formNodes[] = HiddenField::make('default');
        }

        if ($taxCategory->id && $taxRates->isNotEmpty()) {
            $formNodes[] = Table::make('used-by-tax-rates')
                ->columns([
                    ['key' => 'name', 'label' => t('Rate', category: 'commerce')],
                    ['key' => 'store', 'label' => t('Store', category: 'commerce')],
                ])
                ->rows($taxRates->map(fn($taxRate) => [
                    'id' => $taxRate->id,
                    'name' => ['html' => Html::a(Html::encode($taxRate->name), $taxRate->getCpEditUrl())],
                    'store' => t($taxRate->getStore()->name, category: 'site'),
                ])->values()->all());
        }

        $values = [
            'storeId' => $store->id,
            'taxCategoryId' => $taxCategory->id,
            'name' => $taxCategory->name,
            'handle' => $taxCategory->handle,
            'icon' => $taxCategory->icon,
            'color' => $taxCategory->color ?? '',
            'description' => $taxCategory->description,
            'productTypes' => $taxCategory->getProductTypeIds(),
            'default' => $taxCategory->default,
            'defaultDisplay' => $taxCategory->default,
        ];

        $form = $this->formResolver->resolve(Form::make($formNodes), new FormContext(values: $values));

        return $this->cpScreenResponse($store)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($taxCategory->id ? [['label' => $title]] : [])))
            ->action('commerce/tax-categories/save')
            ->redirectUrl($store->getStoreSettingsUrl('taxcategories'))
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'metadataHtml' => $metadataHtml,
            ]);
    }

    public function save(Request $request): Response
    {
        $taxCategory = new TaxCategory();

        $taxCategory->id = $request->input('taxCategoryId') ? (int)$request->input('taxCategoryId') : null;
        $taxCategory->name = $request->input('name');
        $taxCategory->handle = $request->input('handle');
        $taxCategory->icon = $request->input('icon');
        // '__blank__' is ColorSelect's internal sentinel for "no color" selected — it should
        // never reach here (the client translates it back to '' before posting), but guard
        // against it anyway for a genuinely JS-less submission.
        $color = $request->input('color');
        $taxCategory->color = ($color && $color !== '__blank__') ? $color : null;
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
                t('Couldn’t save tax category.', category: 'commerce'),
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
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        $ids = $request->input('ids');
        abort_if((!$id && empty($ids)) || ($id && !empty($ids)), 400, 'id or ids must be specified.');

        if ($id) {
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
