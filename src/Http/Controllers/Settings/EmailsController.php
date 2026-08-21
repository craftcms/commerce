<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\helpers\App;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Models\Email;
use CraftCms\Commerce\Email\Records\Email as EmailRecord;
use CraftCms\Commerce\Helpers\Locale as LocaleHelper;
use CraftCms\Commerce\Pdf\Models\Pdf;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Store\Models\Store;

use CraftCms\Commerce\Store\Stores;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class EmailsController
{
    use RespondsWithFlash;

    private bool $readOnly;

    public function __construct(GeneralConfig $generalConfig)
    {
        $this->readOnly = !$generalConfig->allowAdminChanges;
    }

    public function index(): string
    {
        $emails = [];
        $stores = app(Stores::class)->getAllStores();

        $stores->each(function(Store $store) use (&$emails) {
            $emails[$store->handle] = app(Emails::class)->getAllEmails($store->id);
        });

        return pageTemplate('commerce/settings/emails/index', [
            'stores' => $stores->all(),
            'emails' => $emails,
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        if ($storeHandle === null || !$store = app(Stores::class)->getStoreByHandle($storeHandle)) {
            $store = app(Stores::class)->getPrimaryStore();
        }

        if ($id) {
            $email = app(Emails::class)->getEmailById($id, $store->id);
            abort_if($email === null, 404);
        } else {
            $email = \Craft::createObject([
                'class' => Email::class,
                'attributes' => ['storeId' => $store->id],
            ]);
        }

        $title = $email->id ? $email->name : t('Create a new email', category: 'commerce');

        $pdfs = app(Pdfs::class)->getAllPdfs($email->storeId);
        $pdfList = ['' => t('Do not attach a PDF to this email', category: 'commerce')];
        $pdfList = Arr::merge($pdfList, $pdfs->mapWithKeys(fn(Pdf $pdf) => [$pdf->id => $pdf->name])->all());
        $senderAddressPlaceholder = App::mailSettings()->fromEmail;
        $senderNamePlaceholder = App::mailSettings()->fromName;

        $emailLanguageOptions = [
            EmailRecord::LOCALE_ORDER_LANGUAGE => t('The language the order was made in.', category: 'commerce'),
        ];

        $emailLanguageOptions = array_merge($emailLanguageOptions, LocaleHelper::getSiteAndOtherLanguages());

        $emailRenderSiteOptions = [
            '' => t('The site the order was made in.', category: 'commerce'),
            ['optgroup' => t('Sites', category: 'commerce')],
        ] + collect(Sites::getAllSites())->mapWithKeys(fn(Site $site) => [$site->id => $site->name])->all();

        return new CpScreenResponse()
            ->title($title)
            ->crumbs([
                ['label' => t('Commerce', category: 'commerce'), 'url' => 'commerce'],
                ['label' => t('Settings'), 'url' => 'commerce/settings', 'ariaLabel' => t('Commerce Settings', category: 'commerce')],
                ['label' => t('Emails', category: 'commerce'), 'url' => 'commerce/settings/emails'],
            ])
            ->selectedSubnavItem('settings')
            ->action('commerce/emails/save')
            ->redirectUrl('commerce/settings/emails')
            ->contentTemplate('commerce/settings/emails/_edit', [
                'email' => $email,
                'pdfList' => $pdfList,
                'senderAddressPlaceholder' => $senderAddressPlaceholder,
                'senderNamePlaceholder' => $senderNamePlaceholder,
                'emailLanguageOptions' => $emailLanguageOptions,
                'emailRenderSiteOptions' => $emailRenderSiteOptions,
                'readOnly' => $this->readOnly,
            ]);
    }

    public function save(Request $request): Response
    {
        $emailsService = app(Emails::class);
        $emailId = $request->input('emailId') ? (int)$request->input('emailId') : null;
        $storeId = $request->input('storeId');
        abort_if(!$storeId, 400, "Invalid store ID: $storeId");
        $storeId = (int)$storeId;

        if ($emailId) {
            $email = $emailsService->getEmailById($emailId, $storeId);
            abort_if($email === null, 400, "Invalid email ID: $emailId");
        } else {
            $email = new Email();
        }

        $renderSiteId = $request->input('renderSiteId');

        $email->storeId = $storeId;
        $email->name = $request->input('name');
        $email->subject = $request->input('subject');
        $email->recipientType = $request->input('recipientType');
        $email->setTo($request->input('to'));
        $email->setBcc($request->input('bcc'));
        $email->setCc($request->input('cc'));
        $email->replyTo = $request->input('replyTo');
        $email->enabled = (bool)$request->input('enabled');
        $email->templatePath = $request->input('templatePath');
        $email->plainTextTemplatePath = $request->input('plainTextTemplatePath');
        $pdfId = $request->input('pdfId');
        $email->pdfId = $pdfId ? (int)$pdfId : null;
        $email->language = $request->input('language');
        $email->renderSiteId = $renderSiteId ? (int)$renderSiteId : null;
        $email->setSenderAddress($request->input('senderAddress'));
        $email->setSenderName($request->input('senderName'));

        if (!$emailsService->saveEmail($email)) {
            return $this->asModelFailure($email, t('Couldn\'t save email.', category: 'commerce'), 'email');
        }

        return $this->asModelSuccess($email, t('Email saved.', category: 'commerce'), 'email');
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing email id');

        if (!app(Emails::class)->deleteEmailById((int)$id)) {
            return $this->asFailure(t('Couldn\'t delete email.', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
