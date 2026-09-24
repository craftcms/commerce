<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Combobox;
use CraftCms\Cms\Form\Controls\Handle;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Number;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Helpers\Locale as LocaleHelper;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Models\Pdf as PdfRecord;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

class PdfsController extends BaseSettingsController
{
    protected function getSectionCrumb(): array
    {
        return ['label' => t('PDFs', category: 'commerce'), 'href' => cp_url('commerce/settings/pdfs')];
    }

    public function index(): CpScreenResponse
    {
        $stores = app(Stores::class)->getAllStores();
        $isMultiStore = $stores->count() > 1;

        // Every store's table can plant its own create action in the page's shared actions
        // slot, so with more than one store, only the first store's table gets one — a single
        // combined "New PDF" menu covering every store, rather than one button apiece.
        $createMenuItems = $this->readOnly ? [] : $stores->map(fn(Store $store) => [
            'label' => $store->name,
            'url' => cp_url("commerce/settings/pdfs/{$store->handle}/new"),
        ])->all();
        $createMenuAssigned = false;

        $nodes = [];
        $stores->each(function(Store $store) use (&$nodes, $isMultiStore, $createMenuItems, &$createMenuAssigned) {
            if ($isMultiStore) {
                $nodes[] = Heading::make("{$store->handle}-heading", $store->name);
            }

            $rows = app(Pdfs::class)->getAllPdfs($store->id)
                ->map(fn(Pdf $pdf) => [
                    'id' => $pdf->id,
                    // Restores the legacy VueAdminTable screen's own automatic status dot
                    // (driven there by an implicit `status` row key, not a real column) — the
                    // explicit "Enabled?" Yes/No column below was this same information, added
                    // as a stand-in for the dot when this screen first converted; dropped now
                    // that the dot itself is back, matching the legacy column set exactly again.
                    '_status' => $pdf->enabled,
                    'name' => ['label' => t($pdf->name, category: 'site'), 'url' => $pdf->getCpEditUrl()],
                    'handle' => ['html' => FormFields::copytextHtml(['value' => $pdf->handle, 'monospace' => true])],
                    'default' => $pdf->isDefault ? ['icon' => 'check', 'label' => t('Yes')] : '',
                ])
                ->all();

            $nodes[] = Table::make("{$store->handle}-pdfs")
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'handle', 'label' => t('Handle')],
                    ['key' => 'default', 'label' => t('Default?', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No PDFs exist yet.', category: 'commerce'))
                ->statusFilter()
                ->when(!$createMenuAssigned && $createMenuItems, function(Table $table) use ($createMenuItems, &$createMenuAssigned) {
                    $table->createActionMenu(t('New PDF', category: 'commerce'), $createMenuItems)
                        ->createActionInPageHeader();
                    $createMenuAssigned = true;
                })
                ->when(!$this->readOnly, fn(Table $table) => $table
                    ->reorderable(action([self::class, 'reorder']))
                    ->deletable(action([self::class, 'delete'])));
        });

        $title = t('PDFs', category: 'commerce');

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs())
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
                'contentMaxWidth' => false,
            ]);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        if ($storeHandle === null || !$store = app(Stores::class)->getStoreByHandle($storeHandle)) {
            $store = app(Stores::class)->getPrimaryStore();
        }

        if ($id) {
            $pdf = app(Pdfs::class)->getPdfById($id, $store->id);
            abort_if($pdf === null, 404);
        } else {
            $pdf = new Pdf(['storeId' => $store->id]);
        }

        $title = $pdf->id ? $pdf->name : t('Create a new PDF', category: 'commerce');
        $isDefault = $pdf->isDefault || app(Pdfs::class)->getAllPdfs($pdf->storeId)->count() === 0;

        // The old select field grouped these under "Site Languages"/"Other Languages" optgroup
        // headers; Choice has no optgroup concept, so this flattens them into one list — every
        // option still selectable, just without the section headers.
        $languageOptions = collect([PdfRecord::LOCALE_ORDER_LANGUAGE => t('The language the order was made in.', category: 'commerce')])
            ->merge(LocaleHelper::getSiteAndOtherLanguages())
            ->reject(fn($label) => is_array($label))
            ->map(fn($label, $value) => ['label' => $label, 'value' => $value])
            ->values()
            ->all();

        $paperOrientationOptions = collect(Pdf::getPaperOrientationOptions())
            ->map(fn($label, $value) => ['label' => $label, 'value' => $value])
            ->values()
            ->all();

        $paperSizeOptions = collect(Pdf::getPaperSizeOptions())
            ->map(fn($label, $value) => ['label' => $label, 'value' => $value])
            ->values()
            ->all();

        $handle = Handle::make('handle');
        if (!$pdf->id) {
            $handle->source('name');
        }

        $defaultNode = $pdf->isDefault
            ? HiddenField::make('isDefault')
            : Field::make(t('Default Order PDF', category: 'commerce'), Lightswitch::make('isDefault'))
                ->instructions(t('This is the default PDF that will be rendered when requesting the order PDF.', category: 'commerce'));

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($pdf->id) {
            $formNodes[] = HiddenField::make('id');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('What this PDF will be called in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Handle', category: 'commerce'), $handle)
            ->instructions(t('How you’ll refer to this PDF in the templates.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Description', category: 'commerce'), Text::make('description'));
        // Same template-path autosuggest as the old craft.cp.getTemplateSuggestions() JS call —
        // Combobox is a free-text input with suggestions, not a closed choice.
        $formNodes[] = Field::make(t('PDF Template Path', category: 'commerce'), Combobox::make('templatePath')->options(SelectOptions::getTemplateSuggestions()))
            ->instructions(t('The template that the PDF should be generated from.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Order PDF Filename Format', category: 'commerce'), Text::make('fileNameFormat')->monospace())
            ->instructions(t('What the order PDF filename should look like (sans extension). You can include tags that output order properties, such as {ex1} or {ex2}.', [
                // Field::instructions() runs through the same markdown as the Twig field macro, but
                // (unlike the old macro) doesn't pass raw inline HTML through — backticks instead of
                // literal <code> tags get the same rendered result.
                'ex1' => '`{number}`',
                'ex2' => '`{myOrderCustomField}`',
            ], category: 'commerce'));
        $formNodes[] = Field::make(t('Language', category: 'commerce'), Choice::make('language')->options($languageOptions))
            ->instructions(t('The language to be used when PDF is rendered.'));
        $formNodes[] = Field::make(t('Paper Orientation', category: 'commerce'), Choice::make('paperOrientation')->options($paperOrientationOptions));
        $formNodes[] = Field::make(t('Paper Size', category: 'commerce'), Choice::make('paperSize')->options($paperSizeOptions));
        $formNodes[] = Field::make(t('Link Duration', category: 'commerce'), Number::make('linkExpiry')->min(1))
            ->instructions(t('How long (in seconds) a PDF download link should remain valid before expiring. Default is 86400 (24 hours).', category: 'commerce'));
        $formNodes[] = Field::make(t('Enabled?', category: 'commerce'), Lightswitch::make('enabled'))
            ->instructions(t('If disabled, this PDF will not be available or sent with emails.', category: 'commerce'));
        $formNodes[] = $defaultNode;

        $form = $this->formResolver->resolve(Form::make($formNodes), new FormContext(
            values: [
                'storeId' => $store->id,
                'id' => $pdf->id,
                'name' => $pdf->name,
                'handle' => $pdf->handle,
                'description' => $pdf->description,
                'templatePath' => $pdf->templatePath,
                'fileNameFormat' => $pdf->fileNameFormat,
                'language' => $pdf->language,
                'paperOrientation' => $pdf->paperOrientation,
                'paperSize' => $pdf->paperSize,
                'linkExpiry' => $pdf->linkExpiry,
                'enabled' => $pdf->enabled,
                'isDefault' => $isDefault,
            ],
            mode: $this->generalConfig->allowAdminChanges ? ControlMode::Editable : ControlMode::ReadOnly,
        ));

        return $this->cpScreenResponse(subnav: false)
            ->title($title)
            ->crumbs($pdf->id ? $this->crumbs(['label' => $title]) : $this->crumbs())
            ->action('commerce/pdfs/save')
            ->redirectUrl('commerce/settings/pdfs')
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
            ]);
    }

    public function save(Request $request): Response
    {
        $pdfsService = app(Pdfs::class);
        $pdfId = $request->input('id') ? (int)$request->input('id') : null;
        $storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;

        if ($pdfId) {
            $pdf = $pdfsService->getPdfById($pdfId, $storeId);
            abort_if($pdf === null, 400, "Invalid PDF ID: $pdfId");
        } else {
            $pdf = new Pdf();
        }

        $pdf->storeId = $storeId;
        $pdf->name = $request->input('name');
        $pdf->handle = $request->input('handle');
        $pdf->description = $request->input('description');
        $pdf->templatePath = $request->input('templatePath');
        $pdf->fileNameFormat = $request->input('fileNameFormat');
        $pdf->enabled = (bool)$request->input('enabled');
        $pdf->isDefault = (bool)$request->input('isDefault');
        $pdf->language = $request->input('language');
        $pdf->linkExpiry = (int)$request->input('linkExpiry');
        $pdf->paperSize = $request->input('paperSize');
        $pdf->paperOrientation = $request->input('paperOrientation');

        if (!$pdfsService->savePdf($pdf)) {
            return $this->asModelFailure($pdf, t('Couldn’t save PDF.', category: 'commerce'), 'pdf');
        }

        return $this->asModelSuccess($pdf, t('PDF saved.', category: 'commerce'), 'pdf');
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing PDF id');

        app(Pdfs::class)->deletePdfById((int)$id);

        return $this->asSuccess();
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = Json::decode($request->input('ids'));

        if (!app(Pdfs::class)->reorderPdfs($ids)) {
            return $this->asFailure(t('Couldn’t reorder PDFs.', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
