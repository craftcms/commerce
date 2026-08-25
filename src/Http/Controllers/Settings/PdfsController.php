<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Helpers\Locale as LocaleHelper;
use CraftCms\Commerce\Pdf\Models\Pdf;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Pdf\Records\Pdf as PdfRecord;
use CraftCms\Commerce\Store\Models\Store;
use CraftCms\Commerce\Store\Stores;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

class PdfsController extends SettingsController
{

    public function index(): string
    {
        $pdfs = [];
        $stores = app(Stores::class)->getAllStores();

        $stores->each(function(Store $store) use (&$pdfs) {
            $pdfs[$store->handle] = app(Pdfs::class)->getAllPdfs($store->id);
        });

        return pageTemplate('commerce/settings/pdfs/index', [
            'pdfs' => $pdfs,
            'stores' => $stores->all(),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        if ($storeHandle === null || !$store = app(Stores::class)->getStoreByHandle($storeHandle)) {
            $store = app(Stores::class)->getPrimaryStore();
        }

        $pdfLanguageOptions = [
            PdfRecord::LOCALE_ORDER_LANGUAGE => t('The language the order was made in.', category: 'commerce'),
        ];

        $pdfLanguageOptions = array_merge($pdfLanguageOptions, LocaleHelper::getSiteAndOtherLanguages());

        if ($id) {
            $pdf = app(Pdfs::class)->getPdfById($id, $store->id);
            abort_if($pdf === null, 404);
        } else {
            $pdf = \Craft::createObject([
                'class' => Pdf::class,
                'attributes' => ['storeId' => $store->id],
            ]);
        }

        $title = $pdf->id ? $pdf->name : t('Create a new PDF', category: 'commerce');

        $isDefault = app(Pdfs::class)->getAllPdfs($pdf->storeId)->count() === 0 || $pdf->isDefault;
        $paperOrientationOptions = Pdf::getPaperOrientationOptions();
        $paperSizeOptions = Pdf::getPaperSizeOptions();

        return new CpScreenResponse()
            ->title($title)
            ->crumbs([
                ['label' => t('Commerce', category: 'commerce'), 'url' => 'commerce'],
                ['label' => t('Settings'), 'url' => 'commerce/settings', 'ariaLabel' => t('Commerce Settings', category: 'commerce')],
                ['label' => t('PDFs', category: 'commerce'), 'url' => 'commerce/settings/pdfs'],
            ])
            ->selectedSubnavItem('settings')
            ->action('commerce/pdfs/save')
            ->redirectUrl('commerce/settings/pdfs')
            ->contentTemplate('commerce/settings/pdfs/_edit', [
                'pdf' => $pdf,
                'pdfLanguageOptions' => $pdfLanguageOptions,
                'isDefault' => $isDefault,
                'paperOrientationOptions' => $paperOrientationOptions,
                'paperSizeOptions' => $paperSizeOptions,
                'readOnly' => $this->readOnly,
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
            return $this->asModelFailure($pdf, t('Couldn\'t save PDF.', category: 'commerce'), 'pdf');
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
            return $this->asFailure(t('Couldn\'t reorder PDFs.', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
