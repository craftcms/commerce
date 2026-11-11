<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Ui\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Ui\Controls\Handle;
use CraftCms\Cms\Ui\Controls\Table as TableControl;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Nodes\Heading;
use CraftCms\Cms\Ui\Nodes\HiddenField;
use CraftCms\Cms\Ui\Nodes\Separator;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductType;
use CraftCms\Commerce\DigitalProduct\Data\DigitalProductTypeSite;
use CraftCms\Commerce\DigitalProduct\DigitalProductTypes;
use CraftCms\Commerce\DigitalProduct\Elements\DigitalProduct;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

class DigitalProductTypesController extends BaseSettingsController
{
    protected function getSectionCrumb(): array
    {
        return ['label' => t('Digital Product Types', category: 'commerce'), 'href' => cp_url('commerce/settings/digital-product-types')];
    }

    public function index(): CpScreenResponse
    {
        $rows = array_map(fn(DigitalProductType $type) => [
            'id' => $type->id,
            'name' => [
                'label' => t($type->name, category: 'site'),
                'url' => $type->getCpEditUrl(),
            ],
            'handle' => $type->handle,
        ], app(DigitalProductTypes::class)->getAllDigitalProductTypes());

        $ui = Ui::make([
            Table::make('digital-product-types')
                ->columns([
                    ['key' => 'name', 'label' => t('Name'), 'sortable' => true],
                    ['key' => 'handle', 'label' => t('Handle')],
                ])
                ->rows(array_values($rows))
                ->emptyMessage(t('No digital product types exist yet.', category: 'commerce'))
                ->searchable()
                ->createAction(
                    $this->readOnly ? null : t('New digital product type', category: 'commerce'),
                    $this->readOnly ? null : cp_url('commerce/settings/digital-product-types/new'),
                )
                ->createActionInPageHeader()
                ->when(
                    !$this->readOnly,
                    fn(Table $table) => $table->deletable(action([self::class, 'destroy'])),
                ),
        ]);

        return $this->cpScreenResponse()
            ->title(t('Digital Product Types', category: 'commerce'))
            ->crumbs($this->crumbs())
            ->ui($ui, props: [
                'contentMaxWidth' => false,
            ]);
    }

    public function create(): CpScreenResponse
    {
        return $this->editScreen(new DigitalProductType(), true);
    }

    public function edit(int $typeId): CpScreenResponse
    {
        $type = app(DigitalProductTypes::class)->getDigitalProductTypeById($typeId);
        abort_if(!$type, 404);

        return $this->editScreen($type, false);
    }

    private function editScreen(DigitalProductType $type, bool $brandNew): CpScreenResponse
    {
        $title = $brandNew ? t('Create a new digital product type', category: 'commerce') : (string)$type->name;

        $siteSettings = $type->getSiteSettings();
        $siteRows = [];

        foreach (Sites::getAllSites() as $site) {
            $settings = $siteSettings[$site->id] ?? null;
            $siteRows[$site->handle] = [
                'name' => $site->getName(),
                'enabled' => $brandNew || $settings !== null,
                'uriFormat' => $settings->uriFormat ?? '',
                'template' => $settings->template ?? '',
            ];
        }

        $fieldLayout = $type->getFieldLayout();

        $handle = Handle::make('handle');
        if ($brandNew) {
            $handle->source('name');
        }

        $siteColumns = [
            'name' => ['heading' => t('Site'), 'type' => 'heading'],
            'uriFormat' => [
                'heading' => t('Product URI Format', category: 'commerce'),
                'type' => 'singleline',
                'info' => t('What product URIs should look like for the site. Leave blank if products don’t have URLs.', category: 'commerce'),
            ],
            'template' => [
                'heading' => t('Template'),
                'type' => 'template',
                'options' => SelectOptions::getTemplateSuggestions(),
                'info' => t('Which template should be loaded when a product’s URL is requested.', category: 'commerce'),
            ],
        ];

        if (Sites::isMultiSite()) {
            $siteColumns = ['name' => $siteColumns['name'], 'enabled' => ['heading' => t('Enabled'), 'type' => 'lightswitch'], ...$siteColumns];
        }

        $ui = Ui::make()
            ->addTab(t('Settings'), [
                HiddenField::make('typeId'),
                Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
                    ->instructions(t('What this digital product type will be called in the control panel.', category: 'commerce'))
                    ->required(),
                Field::make(t('Handle', category: 'commerce'), $handle)
                    ->instructions(t('How you’ll refer to this digital product type in the templates.', category: 'commerce'))
                    ->required(),
                Field::make(t('Automatic SKU Format', category: 'commerce'), Text::make('skuFormat')->monospace())
                    ->instructions(t('What the unique auto-generated SKUs should look like, when a SKU field is submitted without a value. You can include tags that output properties, such as {ex1} or {ex2}', [
                        'ex1' => Html::code('{slug}'),
                        'ex2' => Html::code('{myCustomField}'),
                    ], category: 'commerce')),
                Separator::make('site-settings-separator'),
                Heading::make('site-settings-heading', t('Site Settings'))
                    ->description(t('Choose which sites this digital product type should be available in, and configure the site-specific settings.', category: 'commerce')),
                Field::make(control: TableControl::make('sites')->keyed()->columns($siteColumns)),
            ])
            ->addTab(t('Product Fields', category: 'commerce'), [
                Field::make(null, FieldLayoutDesigner::make('fieldLayout')
                    ->elementType(DigitalProduct::class)
                    ->withCardViewDesigner()),
            ]);

        $ui = $this->uiResolver->resolve($ui, new UiContext(
            values: [
                'typeId' => $type->id,
                'name' => $type->name,
                'handle' => $type->handle,
                'skuFormat' => $type->skuFormat ?? '',
                'sites' => $siteRows,
                'fieldLayout' => [
                    'id' => $fieldLayout->id,
                    'uid' => $fieldLayout->uid,
                    ...($fieldLayout->getConfig() ?? []),
                ],
            ],
            mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
        ));

        return $this->cpScreenResponse(subnav: false)
            ->title($title)
            ->crumbs($brandNew ? $this->crumbs() : $this->crumbs(['label' => $title]))
            ->redirectUrl('commerce/settings/digital-product-types')
            ->ui($ui, props: [
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'store']),
                ],
            ]);
    }

    public function store(Request $request): Response
    {
        $typesService = app(DigitalProductTypes::class);
        $typeId = $request->integer('typeId') ?: null;

        if ($typeId) {
            $type = $typesService->getDigitalProductTypeById($typeId);
            abort_if($type === null, 400, "Invalid digital product type ID: $typeId");
        } else {
            $type = new DigitalProductType();
        }

        $type->name = $request->input('name');
        $type->handle = $request->input('handle');
        $type->skuFormat = $request->input('skuFormat') ?: null;

        $allSiteSettings = [];

        foreach (Sites::getAllSites() as $site) {
            $posted = $request->input('sites.' . $site->handle) ?? [];

            if (Sites::isMultiSite() && empty($posted['enabled'])) {
                continue;
            }

            $siteSettings = new DigitalProductTypeSite(['siteId' => $site->id]);
            $siteSettings->hasUrls = !empty($posted['uriFormat']);
            $siteSettings->uriFormat = $siteSettings->hasUrls ? $posted['uriFormat'] : null;
            $siteSettings->template = $siteSettings->hasUrls ? ($posted['template'] ?? null) : null;
            $allSiteSettings[$site->id] = $siteSettings;
        }

        $type->setSiteSettings($allSiteSettings);

        $fieldLayout = Fields::assembleLayoutFromPost();
        $fieldLayout->type = DigitalProduct::class;
        $type->setFieldLayout($fieldLayout);

        if ($typesService->saveDigitalProductType($type)) {
            return $this->asSuccess(t('Digital product type saved.', category: 'commerce'));
        }

        return $this->asModelFailure($type, t('Couldn’t save digital product type.', category: 'commerce'), 'digitalProductType');
    }

    public function destroy(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $typeId = $request->integer('id');
        abort_if(!$typeId, 400, 'Missing digital product type ID');

        app(DigitalProductTypes::class)->deleteDigitalProductTypeById($typeId);

        return $this->asSuccess();
    }
}
