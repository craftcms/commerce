<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\helpers\App;
use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Combobox;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Models\Email as EmailRecord;
use CraftCms\Commerce\Helpers\Locale as LocaleHelper;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

class EmailsController extends BaseSettingsController
{
    protected function getSectionCrumb(): array
    {
        return ['label' => t('Emails', category: 'commerce'), 'href' => cp_url('commerce/settings/emails')];
    }

    public function index(): CpScreenResponse
    {
        $stores = app(Stores::class)->getAllStores();
        $isMultiStore = $stores->count() > 1;

        // Every store's table can plant its own create action in the page's shared actions
        // slot, so with more than one store, only the first store's table gets one — a single
        // combined "New email" menu covering every store, rather than one button apiece.
        $createMenuItems = $this->readOnly ? [] : $stores->map(fn(Store $store) => [
            'label' => $store->name,
            'url' => cp_url("commerce/settings/emails/{$store->handle}/new"),
        ])->all();
        $createMenuAssigned = false;

        $nodes = [];
        $stores->each(function(Store $store) use (&$nodes, $isMultiStore, $createMenuItems, &$createMenuAssigned) {
            if ($isMultiStore) {
                $nodes[] = Heading::make("{$store->handle}-heading", $store->name);
            }

            $rows = app(Emails::class)->getAllEmails($store->id)
                ->map(function(Email $email) {
                    $to = $email->recipientType === EmailRecord::TYPE_CUSTOM
                        ? $email->getTo(false)
                        : t('Customer', category: 'commerce');
                    $previewUrl = Url::actionUrl('commerce/email-preview/render', ['email' => "{$email->id}:{$email->storeId}"]);

                    return [
                        'id' => $email->id,
                        // Restores the legacy VueAdminTable screen's own automatic status dot
                        // (driven there by an implicit `status` row key, not a real column) —
                        // this screen's own conversion dropped it entirely rather than adding a
                        // stand-in column the way PdfsController/TaxRatesController did.
                        '_status' => $email->enabled,
                        'name' => ['label' => t($email->name, category: 'site'), 'url' => $email->getCpEditUrl()],
                        'subject' => t($email->subject, category: 'site'),
                        'to' => $to,
                        'bcc' => $email->getBcc(false),
                        'template' => $email->templatePath ? ['html' => Html::tag('code', Html::encode($email->templatePath))] : '',
                        // Opens in a new tab, same as the old admin table's preview button — the
                        // structured link shapes don't support that, so this is a hand-built anchor.
                        'preview' => ['html' => Html::a(t('Preview', category: 'commerce'), $previewUrl, [
                            'class' => 'btn small',
                            'target' => '_blank',
                            'rel' => 'noopener',
                        ])],
                    ];
                })
                ->all();

            $nodes[] = Table::make("{$store->handle}-emails")
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'subject', 'label' => t('Subject', category: 'commerce')],
                    ['key' => 'to', 'label' => t('To', category: 'commerce')],
                    ['key' => 'bcc', 'label' => t('Bcc', category: 'commerce')],
                    ['key' => 'template', 'label' => t('Template Path', category: 'commerce')],
                    ['key' => 'preview', 'label' => t('Preview', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No emails exist yet.', category: 'commerce'))
                ->statusFilter()
                ->when(!$createMenuAssigned && $createMenuItems, function(Table $table) use ($createMenuItems, &$createMenuAssigned) {
                    $table->createActionMenu(t('New email', category: 'commerce'), $createMenuItems)
                        ->createActionInPageHeader();
                    $createMenuAssigned = true;
                })
                ->when(!$this->readOnly, fn(Table $table) => $table
                    ->deletable(action([self::class, 'delete'])));
        });

        $title = t('Emails', category: 'commerce');

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
            $email = app(Emails::class)->getEmailById($id, $store->id);
            abort_if($email === null, 404);
        } else {
            $email = new Email(['storeId' => $store->id]);
        }

        $title = $email->id ? $email->name : t('Create a new email', category: 'commerce');

        $values = [
            'storeId' => $store->id,
            'id' => $email->id,
            'name' => $email->name,
            'senderAddress' => $email->getSenderAddress(false),
            'senderName' => $email->getSenderName(false),
            'subject' => $email->subject,
            'replyTo' => $email->replyTo,
            'recipientType' => $email->recipientType,
            'to' => $email->getTo(false),
            'bcc' => $email->getBcc(false),
            'cc' => $email->getCc(false),
            'templatePath' => $email->templatePath,
            'plainTextTemplatePath' => $email->plainTextTemplatePath,
            'pdfId' => $email->pdfId,
            'language' => $email->language,
            'renderSiteId' => $email->renderSiteId,
            'enabled' => $email->enabled,
        ];

        $form = $this->formResolver->resolve($this->buildForm($values), new FormContext(
            values: $values,
            mode: $this->generalConfig->allowAdminChanges ? ControlMode::Editable : ControlMode::ReadOnly,
            refreshable: !$this->readOnly,
        ));

        return $this->cpScreenResponse(subnav: false)
            ->title($title)
            ->crumbs($email->id ? $this->crumbs(['label' => $title]) : $this->crumbs())
            ->action('commerce/emails/save')
            ->redirectUrl('commerce/settings/emails')
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'refreshUrl' => $this->readOnly ? null : action([self::class, 'renderForm']),
            ]);
    }

    /**
     * Re-resolves the {@see edit()} Form tree for the values currently in progress on the
     * client, so the Recipient toggle can show or hide the Custom Recipient field without a
     * full page reload.
     */
    public function renderForm(Request $request): JsonResponse
    {
        // Validate for shape only — Request::validate() returns just the ruled subset, which
        // would silently strip every field but the ones named here back out of `values`.
        // Reading the raw input keeps the full posted values intact for buildForm()'s branching.
        $request->validate([
            'values' => ['required', 'array'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');

        $form = $this->formResolver->resolve($this->buildForm($values), new FormContext(
            values: $values,
            mode: ControlMode::Editable,
            refreshable: true,
        ));

        return new JsonResponse(['form' => $form]);
    }

    /** @param array<string, mixed> $values */
    private function buildForm(array $values): Form
    {
        $storeId = (int)($values['storeId'] ?? 0);
        $store = app(Stores::class)->getStoreById($storeId) ?? app(Stores::class)->getPrimaryStore();

        $pdfOptions = [['label' => t('Do not attach a PDF to this email', category: 'commerce'), 'value' => '']];
        foreach (app(Pdfs::class)->getAllPdfs($store->id) as $pdf) {
            $pdfOptions[] = ['label' => $pdf->name, 'value' => $pdf->id];
        }

        // The old select field grouped these under "Site Languages"/"Other Languages" optgroup
        // headers; Choice has no optgroup concept, so this flattens them into one list — every
        // option still selectable, just without the section headers.
        $languageOptions = collect([EmailRecord::LOCALE_ORDER_LANGUAGE => t('The language the order was made in.', category: 'commerce')])
            ->merge(LocaleHelper::getSiteAndOtherLanguages())
            ->reject(fn($label) => is_array($label))
            ->map(fn($label, $value) => ['label' => $label, 'value' => $value])
            ->values()
            ->all();

        $siteOptions = [['label' => t('The site the order was made in.', category: 'commerce'), 'value' => '']];
        foreach (Sites::getAllSites() as $site) {
            $siteOptions[] = ['label' => $site->name, 'value' => $site->id];
        }

        $envTriggers = SelectOptions::getEnvTextExpanderTriggers();
        $isCustomRecipient = ($values['recipientType'] ?? EmailRecord::TYPE_CUSTOMER) === EmailRecord::TYPE_CUSTOM;

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if (!empty($values['id'])) {
            $formNodes[] = HiddenField::make('id');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('What this email will be called in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Status Email Address', category: 'commerce'), Text::make('senderAddress')
            ->placeholder(App::mailSettings()->fromEmail)
            ->textExpanderTriggers($envTriggers))
            ->instructions(t('The email address that order status emails are sent from. Leave blank to use the System Email Address defined in Craft’s General Settings.', category: 'commerce'));
        $formNodes[] = Field::make(t('From Name', category: 'commerce'), Text::make('senderName')
            ->placeholder(App::mailSettings()->fromName)
            ->textExpanderTriggers($envTriggers))
            ->instructions(t('The “From” name that will be used when sending order status emails. Leave blank to use the Sender Name defined in Craft’s General Settings.', category: 'commerce'));
        $formNodes[] = Field::make(t('Email Subject', category: 'commerce'), Text::make('subject'))
            ->instructions(t('The subject line of the email. Twig code can be used here.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Reply To', category: 'commerce'), Text::make('replyTo'))
            ->instructions(t('The reply to email address. Leave blank for normal reply to of email sender. Twig code can be used here.', category: 'commerce'));
        $formNodes[] = Field::make(t('Recipient', category: 'commerce'), Choice::make('recipientType')->options([
            ['label' => t('Send to the customer', category: 'commerce'), 'value' => EmailRecord::TYPE_CUSTOMER],
            ['label' => t('Send to custom recipient', category: 'commerce'), 'value' => EmailRecord::TYPE_CUSTOM],
        ])->reactive())
            ->instructions(t('The recipient of the email. Twig code can be used here.', category: 'commerce'))
            ->required();
        // The old template combined this with Recipient as one compound field, showing/hiding
        // this half inline based on the select above; here it's its own Field, toggled visible
        // via the Form's refreshable round trip instead.
        $formNodes[] = Field::make(t('Recipient', category: 'commerce'), Text::make('to')->textExpanderTriggers($envTriggers))
            ->visible($isCustomRecipient);
        $formNodes[] = Field::make(t('BCC’d Recipient', category: 'commerce'), Text::make('bcc')->textExpanderTriggers($envTriggers))
            ->instructions(t('Additional recipients that should receive this email. Twig code can be used here.', category: 'commerce'));
        $formNodes[] = Field::make(t('CC’d Recipient', category: 'commerce'), Text::make('cc')->textExpanderTriggers($envTriggers))
            ->instructions(t('Additional recipients that should receive this email. Twig code can be used here.', category: 'commerce'));
        $formNodes[] = Field::make(t('HTML Email Template Path', category: 'commerce'), Combobox::make('templatePath')->options(SelectOptions::getTemplateSuggestions()))
            ->instructions(t('The template to be used for HTML emails.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Plain Text Email Template Path', category: 'commerce'), Text::make('plainTextTemplatePath'))
            ->instructions(t('The template to be used for plain text emails. Twig code can be used here.', category: 'commerce'));
        $formNodes[] = Field::make(t('PDF Attachment', category: 'commerce'), Choice::make('pdfId')->options($pdfOptions))
            ->instructions(t('The PDF to attach to this email.', category: 'commerce'));
        $formNodes[] = Field::make(t('Language', category: 'commerce'), Choice::make('language')->options($languageOptions))
            ->instructions(t('The language to be used when this email is rendered.', category: 'commerce'));
        $formNodes[] = Field::make(t('Site', category: 'app'), Choice::make('renderSiteId')->options($siteOptions))
            ->instructions(t('The site to be used when this email is rendered.', category: 'commerce'));
        $formNodes[] = Field::make(t('Enabled?', category: 'commerce'), Lightswitch::make('enabled'))
            ->instructions(t('If disabled, this email will not send.', category: 'commerce'));

        return Form::make($formNodes);
    }

    public function save(Request $request): Response
    {
        $emailsService = app(Emails::class);
        $emailId = $request->input('id') ? (int)$request->input('id') : null;
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
            return $this->asModelFailure($email, t('Couldn’t save email.', category: 'commerce'), 'email');
        }

        return $this->asModelSuccess($email, t('Email saved.', category: 'commerce'), 'email');
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing email id');

        if (!app(Emails::class)->deleteEmailById((int)$id)) {
            return $this->asFailure(t('Couldn’t delete email.', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
