<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Element\Enums\PropagationMethod;
use CraftCms\Cms\Field\Enums\TranslationMethod;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Form\Controls\Handle;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Controls\Table as TableControl;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Separator;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Form\Nodes\TemplateContent;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Commerce\Catalog\Elements\Variant;
use CraftCms\Commerce\Catalog\Models\ProductTypeSite;
use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;
use CraftCms\Commerce\Catalog\ProductType\ProductTypes;
use CraftCms\Commerce\Shipping\Models\ShippingCategory;
use CraftCms\Commerce\Tax\Models\TaxCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class ProductTypesController extends BaseSettingsController
{
    protected function crumbs(?string $title = null, ?string $url = null): array
    {
        $crumbs = parent::crumbs(t('Product Types', category: 'commerce'), cp_url('commerce/settings/producttypes'));

        if ($title || $url) {
            $crumbs[] = ['label' => $title, 'href' => $url];
        }

        return $crumbs;
    }

    public function productTypeIndex(): CpScreenResponse
    {
        $canManageShipping = (bool)currentUser()?->can('commerce-manageShipping');
        $canManageTaxes = (bool)currentUser()?->can('commerce-manageTaxes');

        $rows = array_map(fn(ProductType $productType) => [
            'name' => [
                'label' => t($productType->name, category: 'site'),
                'url' => $productType->getCpEditUrl(),
            ],
            'handle' => $productType->handle,
            'maxVariants' => $productType->maxVariants ?? '',
            'shippingCategories' => array_map(fn(ShippingCategory $category) => [
                'label' => t($category->name, category: 'site'),
                'url' => $canManageShipping ? $category->getCpEditUrl() : null,
            ], $productType->getShippingCategories()),
            'taxCategories' => array_map(fn(TaxCategory $category) => [
                'label' => t($category->name, category: 'site'),
                'url' => $canManageTaxes ? $category->getCpEditUrl() : null,
            ], $productType->getTaxCategories()),
        ], app(ProductTypes::class)->getAllProductTypes());

        $title = t('Product Types', category: 'commerce');

        $form = Form::make([
            Table::make('product-types')
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'handle', 'label' => t('Handle')],
                    ['key' => 'maxVariants', 'label' => t('Max Variants', category: 'commerce')],
                    ['key' => 'shippingCategories', 'label' => t('Available Shipping Categories', category: 'commerce')],
                    ['key' => 'taxCategories', 'label' => t('Available Tax Categories', category: 'commerce')],
                ])
                ->rows(array_values($rows))
                ->emptyMessage(t('No product types exist yet.', category: 'commerce'))
                ->createAction(
                    $this->readOnly ? null : t('New product type', category: 'commerce'),
                    $this->readOnly ? null : cp_url('commerce/settings/producttypes/new'),
                ),
        ]);

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs($title))
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve($form, new FormContext()),
            ]);
    }

    public function editProductType(?int $productTypeId = null): CpScreenResponse
    {
        $brandNewProductType = false;

        if ($productTypeId) {
            $productType = app(ProductTypes::class)->getProductTypeById($productTypeId);
            abort_if(!$productType, 404);
        } else {
            $productType = new ProductType();
            $brandNewProductType = true;
        }

        $title = $productTypeId ? $productType->name : t('Create a new product type', category: 'commerce');
        $values = $this->initialValues($productType, $brandNewProductType);

        $form = $this->formResolver->resolve(
            $this->buildForm($productType, $values, $brandNewProductType),
            new FormContext(
                values: $values,
                mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
                refreshable: !$this->readOnly,
            ),
        );

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs($brandNewProductType ? null : $title))
            ->redirectUrl('commerce/settings/producttypes')
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'saveProductType']),
                ],
                'refreshUrl' => $this->readOnly ? null : action([self::class, 'renderForm']),
            ]);
    }

    /**
     * Re-resolves the {@see editProductType()} Form tree for the values currently in progress
     * on the client, so toggles like `isStructure`/`hasProductTitleField` can branch which
     * fields appear next without a full page reload.
     */
    public function renderForm(Request $request): JsonResponse
    {
        // Validate for shape only — `Request::validate()` returns just the ruled subset
        // (`validated()`), which would silently strip every field but `productTypeId` back
        // out of `values`. Reading the raw input keeps the full posted values intact, which
        // `buildForm()`'s branching (and every other control's live value) depends on.
        $request->validate([
            'values' => ['required', 'array'],
            'values.productTypeId' => ['nullable', 'integer'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');
        $productTypeId = $values['productTypeId'] ?? null;

        if ($productTypeId) {
            $productType = app(ProductTypes::class)->getProductTypeById((int) $productTypeId);
            abort_if(!$productType, 404);
        } else {
            $productType = new ProductType();
        }

        // The client only posts values for controls currently in the rendered tree — a field
        // hidden behind a toggle that's about to flip back on is absent, not merely empty. Layer
        // the posted values over this model's real defaults so a newly-revealed field (e.g.
        // `defaultPlacement` when `isStructure` flips on) gets its actual value instead of null.
        $values = array_replace($this->initialValues($productType, brandNew: !$productTypeId), $values);

        $form = $this->formResolver->resolve(
            $this->buildForm($productType, $values, brandNew: !$productTypeId),
            new FormContext(
                values: $values,
                mode: ControlMode::Editable,
                refreshable: true,
            ),
        );

        return new JsonResponse(['form' => $form]);
    }

    /**
     * The Form's starting values, derived from a loaded (or brand new) {@see ProductType}.
     * Only used for the initial page load — {@see renderForm()} uses the client's own
     * in-progress values instead, so unsaved edits aren't clobbered on every toggle.
     *
     * @return array<string, mixed>
     */
    private function initialValues(ProductType $productType, bool $brandNew): array
    {
        $siteSettings = $productType->getSiteSettings();
        $siteRows = [];

        foreach (Sites::getAllSites() as $site) {
            $settings = $siteSettings[$site->id] ?? null;

            $siteRows[$site->handle] = [
                'name' => $site->getName(),
                'enabled' => $brandNew || $settings !== null,
                'uriFormat' => $settings->uriFormat ?? '',
                'template' => $settings->template ?? '',
                'enabledByDefault' => $settings === null || $settings->enabledByDefault,
            ];
        }

        $productFieldLayout = $productType->getProductFieldLayout();
        $variantFieldLayout = $productType->getVariantFieldLayout();

        return [
            'productTypeId' => $productType->id,
            'name' => $productType->name,
            'handle' => $productType->handle,
            'isStructure' => $productType->isStructure,
            'defaultPlacement' => $productType->defaultPlacement,
            'maxLevels' => $productType->maxLevels ?? '',
            'enableVersioning' => $productType->enableVersioning,
            'hasProductTitleField' => $productType->hasProductTitleField,
            'productTitleFormat' => $productType->productTitleFormat,
            'productTitleTranslationMethod' => $productType->productTitleTranslationMethod,
            'productTitleTranslationKeyFormat' => $productType->productTitleTranslationKeyFormat ?? '',
            'productUiLabelFormat' => $productType->productUiLabelFormat,
            'showSlugField' => $productType->showSlugField,
            'slugTranslationMethod' => $productType->slugTranslationMethod,
            'slugTranslationKeyFormat' => $productType->slugTranslationKeyFormat ?? '',
            'skuFormat' => $productType->skuFormat ?? '',
            'descriptionFormat' => $productType->descriptionFormat,
            'maxVariants' => $productType->maxVariants ?? '',
            'hasDimensions' => $productType->hasDimensions,
            'hasVariantTitleField' => $productType->hasVariantTitleField,
            'variantTitleFormat' => $productType->variantTitleFormat,
            'variantTitleTranslationMethod' => $productType->variantTitleTranslationMethod,
            'variantTitleTranslationKeyFormat' => $productType->variantTitleTranslationKeyFormat ?? '',
            'variantUiLabelFormat' => $productType->variantUiLabelFormat,
            'sites' => $siteRows,
            'previewTargets' => $productType->previewTargets ?? [],
            'propagationMethod' => $productType->propagationMethod->value,
            'fieldLayout' => [
                'id' => $productFieldLayout->id,
                'uid' => $productFieldLayout->uid,
                ...($productFieldLayout->getConfig() ?? []),
            ],
            'variant-layout' => [
                'fieldLayout' => [
                    'id' => $variantFieldLayout->id,
                    'uid' => $variantFieldLayout->uid,
                    ...($variantFieldLayout->getConfig() ?? []),
                ],
            ],
        ];
    }

    /**
     * Builds the edit screen's Form tree, branching on `$values` (rather than reading straight
     * off `$productType`) so {@see editProductType()} and {@see renderForm()} produce the exact
     * same shape for the exact same values — one from a loaded model, the other from whatever the
     * client just posted mid-edit.
     *
     * @param  array<string, mixed>  $values
     */
    private function buildForm(ProductType $productType, array $values, bool $brandNew): Form
    {
        $isMultiSite = Sites::isMultiSite();

        $handle = Handle::make('handle');

        if ($brandNew) {
            $handle->source('name');
        }

        $isStructureField = Field::make(
            t('Enable structure for products of this type', category: 'commerce'),
            Lightswitch::make('isStructure'),
        );

        if ($productType->id) {
            $isStructureField->warning(t('Changing this may result in data loss.'));
        }

        $settingsFields = [
            HiddenField::make('productTypeId'),
            Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
                ->instructions(t('What this product type will be called in the control panel.', category: 'commerce'))
                ->required(),
            Field::make(t('Handle', category: 'commerce'), $handle)
                ->instructions(t('How you’ll refer to this product type in the templates.', category: 'commerce'))
                ->required(),
            $isStructureField,
        ];

        if ($values['isStructure'] ?? false) {
            $settingsFields[] = Field::make(
                t('Default {type} Placement', ['type' => t('Product', category: 'commerce')]),
                Choice::make('defaultPlacement')->options([
                    ['value' => 'beginning', 'label' => t('Before other {type}', ['type' => t('products', category: 'commerce')])],
                    ['value' => 'end', 'label' => t('After other {type}', ['type' => t('products', category: 'commerce')])],
                ]),
            )->instructions(t('Where new {type} should be placed by default in the structure.', ['type' => t('products', category: 'commerce')]));

            $settingsFields[] = Field::make(t('Max Levels'), Number::make('maxLevels')->min(1)->max(32767)->size(5))
                ->instructions(t('The maximum number of levels this product type can have. Leave blank if you don’t care.', category: 'commerce'));
        }

        $settingsFields[] = Field::make(t('Enable versioning for products of this type', category: 'commerce'), Lightswitch::make('enableVersioning'));
        $settingsFields[] = Field::make(t('Show the Title field for products', category: 'commerce'), Lightswitch::make('hasProductTitleField'));

        if ($values['hasProductTitleField'] ?? false) {
            if ($isMultiSite) {
                $settingsFields[] = Field::make(
                    t('{name} Translation Method', ['name' => t('Title')]),
                    Choice::make('productTitleTranslationMethod')->options(TranslationMethod::asOptions()),
                )->instructions(t('How should {name} values be translated?', ['name' => t('Title')]));

                if (($values['productTitleTranslationMethod'] ?? null) === TranslationMethod::Custom->value) {
                    $settingsFields[] = Field::make(
                        t('{name} Translation Key Format', ['name' => t('Title')]),
                        Text::make('productTitleTranslationKeyFormat')->monospace(),
                    )->instructions(t('Template that defines the {name} field’s custom “translation key” format. Values will be copied to all sites that produce the same key.', ['name' => t('Title')]));
                }
            }
        } else {
            $settingsFields[] = Field::make(t('Product Title Format', category: 'commerce'), Text::make('productTitleFormat')->monospace())
                ->instructions(t('What the auto-generated product titles should look like. You can include tags that output product properties, such as {ex1} or {ex2}. All custom fields used must be set to required.', [
                    'ex1' => Html::code('{sku}'),
                    'ex2' => Html::code('{myProductsCustomField}'),
                ], category: 'commerce'));
        }

        $settingsFields[] = Field::make(t('UI Label Format'), Text::make('productUiLabelFormat')->monospace())
            ->instructions(t('How products should be labeled within the control panel.', category: 'commerce'));
        $settingsFields[] = Field::make(t('Show the Slug field'), Lightswitch::make('showSlugField'));

        if ($isMultiSite && ($values['showSlugField'] ?? false)) {
            $settingsFields[] = Field::make(
                t('{name} Translation Method', ['name' => t('Slug')]),
                Choice::make('slugTranslationMethod')->options(TranslationMethod::asOptions()),
            )->instructions(t('How should {name} values be translated?', ['name' => t('Slug')]));

            if (($values['slugTranslationMethod'] ?? null) === TranslationMethod::Custom->value) {
                $settingsFields[] = Field::make(
                    t('{name} Translation Key Format', ['name' => t('Slug')]),
                    Text::make('slugTranslationKeyFormat')->monospace(),
                )->instructions(t('Template that defines the {name} field’s custom "translation key" format. Values will be copied to all sites that produce the same key.', ['name' => t('Slug')]));
            }
        }

        $settingsFields[] = Field::make(t('Automatic SKU Format', category: 'commerce'), Text::make('skuFormat')->monospace())
            ->instructions(t('What the unique auto-generated SKUs should look like, when a SKU field is submitted without a value. You can include tags that output properties, such as {ex1} or {ex2}', [
                'ex1' => Html::code('{product.slug}'),
                'ex2' => Html::code('{myVariantCustomField}'),
            ], category: 'commerce'));
        $settingsFields[] = Field::make(t('Order Description Format', category: 'commerce'), Text::make('descriptionFormat')->monospace())
            ->instructions(t('How this product will be described on a line item in an order. You can include tags that output properties, such as {ex1} or {ex2}', [
                'ex1' => Html::code('{product.title}'),
                'ex2' => Html::code('{myVariantCustomField}'),
            ], category: 'commerce'));
        $settingsFields[] = Field::make(t('Max Variants', category: 'commerce'), Number::make('maxVariants')->size(2));
        $settingsFields[] = Field::make(t('Show the Dimensions and Weight fields for products of this type', category: 'commerce'), Lightswitch::make('hasDimensions'));
        $settingsFields[] = Separator::make('variant-settings-separator');
        $settingsFields[] = Field::make(t('Show the Title field for variants', category: 'commerce'), Lightswitch::make('hasVariantTitleField'));

        if ($values['hasVariantTitleField'] ?? false) {
            if ($isMultiSite) {
                $settingsFields[] = Field::make(
                    t('{name} Translation Method', ['name' => t('Title')]),
                    Choice::make('variantTitleTranslationMethod')->options(TranslationMethod::asOptions()),
                )->instructions(t('How should {name} values be translated?', ['name' => t('Title')]));

                if (($values['variantTitleTranslationMethod'] ?? null) === TranslationMethod::Custom->value) {
                    $settingsFields[] = Field::make(
                        t('{name} Translation Key Format', ['name' => t('Title')]),
                        Text::make('variantTitleTranslationKeyFormat')->monospace(),
                    )->instructions(t('Template that defines the {name} field’s custom “translation key” format. Values will be copied to all sites that produce the same key.', ['name' => t('Title')]));
                }
            }
        } else {
            $settingsFields[] = Field::make(t('Variant Title Format', category: 'commerce'), Text::make('variantTitleFormat')->monospace())
                ->instructions(t('What the auto-generated variant titles should look like. You can include tags that output variant properties, such as {ex1} or {ex2}. All custom fields used must be set to required.', [
                    'ex1' => Html::code('{sku}'),
                    'ex2' => Html::code('{myVariantsCustomField}'),
                ], category: 'commerce'));
        }

        $settingsFields[] = Field::make(t('Variant UI Label Format', category: 'commerce'), Text::make('variantUiLabelFormat')->monospace())
            ->instructions(t('How variants should be labeled within the control panel.', category: 'commerce'));
        $settingsFields[] = Separator::make('site-settings-separator');
        $settingsFields[] = Heading::make('site-settings-heading', t('Site Settings'))
            ->description(t('Choose which sites this product type should be available in, and configure the site-specific settings.', category: 'commerce'));
        $settingsFields[] = Field::make(control: TableControl::make('sites')
            ->keyed()
            ->columns([
                'name' => ['heading' => t('Site'), 'type' => 'heading'],
                'enabled' => ['heading' => t('Enabled'), 'type' => 'lightswitch'],
                'uriFormat' => [
                    'heading' => t('Product URI Format', category: 'commerce'),
                    'type' => 'singleline',
                    'info' => t('What product URIs should look like for the site.', category: 'commerce'),
                ],
                'template' => [
                    'heading' => t('Template'),
                    'type' => 'template',
                    'options' => SelectOptions::getTemplateSuggestions(),
                    'info' => t('Which template should be loaded when a product’s URL is requested.', category: 'commerce'),
                ],
                'enabledByDefault' => ['heading' => t('Default Status'), 'type' => 'lightswitch'],
            ]));

        if (!$this->generalConfig->headlessMode) {
            $settingsFields[] = Separator::make('preview-targets-separator');
            $settingsFields[] = Heading::make('preview-targets-heading', t('Preview Targets'))
                ->description(t('Locations that should be available for previewing products in this product type.', category: 'commerce'));
            $settingsFields[] = Field::make(control: TableControl::make('previewTargets')
                ->columns([
                    'label' => ['heading' => t('Label'), 'type' => 'singleline'],
                    'urlFormat' => [
                        'heading' => t('URL Format'),
                        'type' => 'singleline',
                        'info' => t('The URL/URI to use for this target.'),
                    ],
                    'refresh' => ['heading' => t('Auto-refresh'), 'type' => 'lightswitch'],
                ])
                ->allowAdd()
                ->allowDelete()
                ->allowReorder());
        }

        if ($isMultiSite) {
            $settingsFields[] = Field::make(
                t('Propagation Method'),
                Choice::make('propagationMethod')->options([
                    ['value' => 'none', 'label' => t('Only save product to the site they were created in', category: 'commerce')],
                    ['value' => 'siteGroup', 'label' => t('Save product to other sites in the same site group', category: 'commerce')],
                    ['value' => 'language', 'label' => t('Save product to other sites with the same language', category: 'commerce')],
                    ['value' => 'all', 'label' => t('Save product to all sites enabled for this product type', category: 'commerce')],
                    ['value' => 'custom', 'label' => t('Let each product choose which sites it should be saved to', category: 'commerce')],
                ]),
            )->instructions(t('Of the enabled sites above, which sites should products in this product type be saved to?', category: 'commerce'));
        }

        return Form::make()
            ->addTab(t('Settings'), $settingsFields)
            ->addTab(t('Tax & Shipping', category: 'commerce'), [
                Heading::make('shipping-categories-heading', t('Available Shipping Categories', category: 'commerce')),
                TemplateContent::make('shipping-categories', $this->categoriesHtml(
                    $productType->getShippingCategories(),
                    (bool) currentUser()?->can('commerce-manageShipping'),
                )),
                Separator::make('tax-shipping-separator'),
                Heading::make('tax-categories-heading', t('Available Tax Categories', category: 'commerce')),
                TemplateContent::make('tax-categories', $this->categoriesHtml(
                    $productType->getTaxCategories(),
                    (bool) currentUser()?->can('commerce-manageTaxes'),
                )),
            ])
            ->addTab(t('Product Fields', category: 'commerce'), [
                Field::make(null, FieldLayoutDesigner::make('fieldLayout')
                    ->elementType(Product::class)
                    ->withCardViewDesigner()),
            ])
            ->addTab(t('Variant Fields', category: 'commerce'), [
                Field::make(null, FieldLayoutDesigner::make('variant-layout.fieldLayout')
                    ->elementType(Variant::class)
                    ->withCardViewDesigner()),
            ]);
    }

    /**
     * @param  ShippingCategory[]|TaxCategory[]  $categories
     *
     * Category names are user-entered, so every name is run through {@see Html::encode()}
     * before it's embedded — `Html::tag()`/`Html::a()` don't encode their content themselves
     * (they assume the caller already has), and this content is otherwise-unsanitized HTML by
     * the time {@see TemplateContent::make()} sanitizes it, so this can't rely on that alone.
     */
    private function categoriesHtml(array $categories, bool $canManage): string
    {
        if (empty($categories)) {
            return Html::tag('p', Html::encode(t('None', category: 'commerce')));
        }

        $items = implode('', array_map(
            fn($category) => Html::tag('li', $canManage
                ? Html::a(Html::encode(t($category->name, category: 'site')), $category->getCpEditUrl())
                : Html::encode(t($category->name, category: 'site'))),
            $categories,
        ));

        return Html::tag('ul', $items, ['class' => 'bullets']);
    }

    public function saveProductType(Request $request): ?Response
    {
        abort_unless(currentUser()?->can('manageCommerce'), 403, t('This action is not allowed for the current user.', category: 'commerce'));

        $productTypeId = $request->input('productTypeId') ? (int)$request->input('productTypeId') : null;

        if ($productTypeId) {
            $productType = app(ProductTypes::class)->getProductTypeById($productTypeId);
            abort_unless($productType !== null, 400, "Invalid section ID: $productTypeId");
        } else {
            $productType = new ProductType();
        }

        // Shared attributes
        $productType->id = $productTypeId;
        $productType->name = $request->input('name');
        $productType->handle = $request->input('handle');
        $productType->enableVersioning = $request->input('enableVersioning') ?? $productType->enableVersioning;
        $productType->hasDimensions = (bool)$request->input('hasDimensions');
        $productType->hasProductTitleField = (bool)$request->input('hasProductTitleField');
        $productType->productTitleFormat = $request->input('productTitleFormat', $productType->productTitleFormat) ?? '';
        $productType->productUiLabelFormat = $request->input('productUiLabelFormat');
        $productType->productTitleTranslationMethod = $request->input('productTitleTranslationMethod', $productType->productTitleTranslationMethod);
        $productType->productTitleTranslationKeyFormat = $request->input('productTitleTranslationKeyFormat', $productType->productTitleTranslationKeyFormat);
        $productType->showSlugField = (bool)$request->input('showSlugField', $productType->showSlugField);
        $productType->slugTranslationMethod = $request->input('slugTranslationMethod', $productType->slugTranslationMethod);
        $productType->slugTranslationKeyFormat = $request->input('slugTranslationKeyFormat', $productType->slugTranslationKeyFormat);
        $maxVariants = $request->input('maxVariants');
        $productType->maxVariants = $maxVariants ? (int)$maxVariants : null;
        $productType->hasVariantTitleField = $request->input('hasVariantTitleField', false);
        $productType->variantTitleFormat = $request->input('variantTitleFormat', $productType->variantTitleFormat) ?? '';
        $productType->variantUiLabelFormat = $request->input('variantUiLabelFormat');
        $productType->variantTitleTranslationMethod = $request->input('variantTitleTranslationMethod', $productType->variantTitleTranslationMethod);
        $productType->variantTitleTranslationKeyFormat = $request->input('variantTitleTranslationKeyFormat', $productType->variantTitleTranslationKeyFormat);
        $productType->skuFormat = $request->input('skuFormat');
        $productType->descriptionFormat = $request->input('descriptionFormat');
        $productType->propagationMethod = PropagationMethod::tryFrom($request->input('propagationMethod') ?? '') ?? PropagationMethod::All;
        $productType->isStructure = $request->input('isStructure');
        $maxLevels = (int)$request->input('maxLevels');
        $productType->maxLevels = $maxLevels ?: null; // zero should be null
        $productType->defaultPlacement = $request->input('defaultPlacement', $productType->defaultPlacement) ?? $productType->defaultPlacement;
        $productType->previewTargets = $request->input('previewTargets') ?: [];

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Sites::getAllSites() as $site) {
            $postedSettings = $request->input('sites.' . $site->handle);

            // Skip disabled sites if this is a multi-site install
            if (Sites::isMultiSite() && empty($postedSettings['enabled'])) {
                continue;
            }

            $siteSettings = new ProductTypeSite();
            $siteSettings->siteId = $site->id;
            $siteSettings->hasUrls = !empty($postedSettings['uriFormat']);

            $siteSettings->enabledByDefault = (bool)$postedSettings['enabledByDefault'];

            if ($siteSettings->hasUrls) {
                $siteSettings->uriFormat = $postedSettings['uriFormat'];
                $siteSettings->template = $postedSettings['template'];
            } else {
                $siteSettings->uriFormat = null;
                $siteSettings->template = null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $productType->setSiteSettings($allSiteSettings);

        // Set the product type field layout
        $fieldLayout = Fields::assembleLayoutFromPost();
        $fieldLayout->type = Product::class;
        $productType->setProductFieldLayout($fieldLayout);

        // Set the variant field layout
        $variantFieldLayout = Fields::assembleLayoutFromPost('variant-layout');
        $variantFieldLayout->type = Variant::class;
        $productType->setVariantFieldLayout($variantFieldLayout);

        // Save it
        if (app(ProductTypes::class)->saveProductType($productType)) {
            return $this->asSuccess(t('Product type saved.', category: 'commerce'));
        }

        return $this->asModelFailure($productType, t('Couldn\'t save product type.', category: 'commerce'), 'productType');
    }

    public function deleteProductType(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $productTypeId = $request->input('id');
        abort_if(!$productTypeId, 400, 'Missing product type id');

        app(ProductTypes::class)->deleteProductTypeById((int)$productTypeId);

        return $this->asSuccess();
    }
}
