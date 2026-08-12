<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\elements\Order;
use craft\commerce\models\OrderHistory;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\events\ConfigEvent;
use craft\helpers\Db as CraftDb;
use craft\mail\Message;
use CraftCms\Cms\Asset\AssetsHelper as Assets;
use CraftCms\Cms\Support\DateTimeHelper;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Email\Events\EmailEvent;
use CraftCms\Commerce\Email\Events\MailEvent;
use CraftCms\Commerce\Email\Models\Email;
use CraftCms\Commerce\Helpers\Locale;
use CraftCms\Commerce\Helpers\ProjectConfigData;
use DateTime;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use yii\base\Exception;
use function CraftCms\Cms\t;

#[Singleton]
class Emails
{
    public const string EVENT_BEFORE_SEND_MAIL = 'beforeSendEmail';

    public const string EVENT_AFTER_SEND_MAIL = 'afterSendEmail';

    public const string EVENT_BEFORE_SAVE_EMAIL = 'beforeSaveEmail';

    public const string EVENT_AFTER_SAVE_EMAIL = 'afterSaveEmail';

    public const string EVENT_BEFORE_DELETE_EMAIL = 'beforeDeleteEmail';

    public const string EVENT_AFTER_DELETE_EMAIL = 'afterDeleteEmail';

    public const string CONFIG_EMAILS_KEY = 'commerce.emails';

    /**
     * @var array<int, Collection<int, Email>>|null
     */
    private ?array $allEmails = null;

    /**
     * Get an email by its ID.
     */
    public function getEmailById(int $id, ?int $storeId = null): ?Email
    {
        return $this->getAllEmails($storeId)->firstWhere('id', $id);
    }

    /**
     * Get all emails.
     *
     * @return Collection<int, Email>
     */
    public function getAllEmails(?int $storeId = null): Collection
    {
        $storeId ??= Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->allEmails === null || !isset($this->allEmails[$storeId])) {
            $results = $this->query()->where('emails.storeId', $storeId)->get();

            $this->allEmails ??= [];

            foreach ($results as $result) {
                $email = new Email((array)$result);

                $this->allEmails[$email->storeId] ??= collect();
                $this->allEmails[$email->storeId]->push($email);
            }
        }

        return $this->allEmails[$storeId] ?? collect();
    }

    /**
     * Get all emails that are enabled.
     *
     * @return Collection<int, Email>
     */
    public function getAllEnabledEmails(?int $storeId = null): Collection
    {
        return $this->getAllEmails($storeId)->where('enabled', true);
    }

    /**
     * Save an email.
     */
    public function saveEmail(Email $email, bool $runValidation = true): bool
    {
        $isNewEmail = !(bool)$email->id;

        // Raise 'beforeSaveEmail' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getEmails()->hasEventHandlers(self::EVENT_BEFORE_SAVE_EMAIL)) {
            $beforeEvent = new EmailEvent(
                email: $email,
                isNew: $isNewEmail,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getEmails()->trigger(self::EVENT_BEFORE_SAVE_EMAIL, $beforeEvent);
        }

        if ($runValidation && !$email->validate()) {
            Log::info('Email not saved due to validation error(s).');
            return false;
        }

        if ($isNewEmail) {
            $email->uid = Str::uuid()->toString();
        }

        $configPath = self::CONFIG_EMAILS_KEY . '.' . $email->uid;
        $configData = $email->getConfig();
        \Craft::$app->getProjectConfig()->set($configPath, $configData);

        if ($isNewEmail) {
            $email->id = CraftDb::idByUid(Table::EMAILS, $email->uid);
        }

        return true;
    }

    /**
     * Handle email status change.
     *
     * @throws Throwable if reasons
     */
    public function handleChangedEmail(ConfigEvent $event): void
    {
        ProjectConfigData::ensureAllStoresProcessed();

        $emailUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $pdfUid = $data['pdf'] ?? null;
        if ($pdfUid) {
            \Craft::$app->getProjectConfig()->processConfigChanges(Pdfs::CONFIG_PDFS_KEY . '.' . $pdfUid);
        }

        $transaction = \Craft::$app->getDb()->beginTransaction();
        try {
            $emailRecord = $this->getEmailRecord($emailUid);
            $isNewEmail = $emailRecord->getIsNewRecord();
            $store = Plugin::getInstance()->getStores()->getStoreByUid($data['store']);
            $renderSite = array_key_exists('renderSite', $data) && $data['renderSite'] !== null ? \Craft::$app->getSites()->getSiteByUid($data['renderSite']) : null;

            $emailRecord->storeId = $store->id;
            $emailRecord->name = $data['name'];
            $emailRecord->subject = $data['subject'];
            $emailRecord->recipientType = $data['recipientType'];
            $emailRecord->to = $data['to'];
            $emailRecord->bcc = $data['bcc'];
            $emailRecord->cc = $data['cc'] ?? null;
            $emailRecord->replyTo = $data['replyTo'] ?? null;
            $emailRecord->enabled = $data['enabled'];
            $emailRecord->senderAddress = $data['senderAddress'];
            $emailRecord->senderName = $data['senderName'];
            $emailRecord->templatePath = $data['templatePath'];
            $emailRecord->plainTextTemplatePath = $data['plainTextTemplatePath'] ?? null;
            $emailRecord->uid = $emailUid;
            $emailRecord->pdfId = $pdfUid ? CraftDb::idByUid(Table::PDFS, $pdfUid) : null;
            $emailRecord->language = $data['language'] ?? EmailRecord::LOCALE_ORDER_LANGUAGE;
            $emailRecord->renderSiteId = $renderSite?->id ?? null;

            $emailRecord->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Raise 'afterSaveEmail' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getEmails()->hasEventHandlers(self::EVENT_AFTER_SAVE_EMAIL)) {
            $afterEvent = new EmailEvent(
                email: $this->getEmailById($emailRecord->id, $emailRecord->storeId),
                isNew: $isNewEmail,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getEmails()->trigger(self::EVENT_AFTER_SAVE_EMAIL, $afterEvent);
        }

        $this->clearCache();
    }

    /**
     * Delete an email by its ID.
     */
    public function deleteEmailById(int $id): bool
    {
        $email = EmailRecord::findOne($id);

        if ($email) {
            // Raise 'beforeDeleteEmail' event
            // TODO: migrate event firing to Laravel once event system is bridged
            /** @phpstan-ignore-next-line */
            if (Plugin::getInstance()->getEmails()->hasEventHandlers(self::EVENT_BEFORE_DELETE_EMAIL)) {
                $event = new EmailEvent(
                    email: $this->getEmailById($id, $email->storeId),
                );
                /** @phpstan-ignore-next-line */
                Plugin::getInstance()->getEmails()->trigger(self::EVENT_BEFORE_DELETE_EMAIL, $event);
            }

            \Craft::$app->getProjectConfig()->remove(self::CONFIG_EMAILS_KEY . '.' . $email->uid);
        }

        return true;
    }

    /**
     * Handle email getting deleted.
     *
     * @throws Throwable
     */
    public function handleDeletedEmail(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $emailRecord = $this->getEmailRecord($uid);

        if (!$emailRecord->id) {
            return;
        }

        $email = $this->getEmailById($emailRecord->id, $emailRecord->storeId);
        $emailRecord->delete();

        // Raise 'afterDeleteEmail' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getEmails()->hasEventHandlers(self::EVENT_AFTER_DELETE_EMAIL)) {
            $afterEvent = new EmailEvent(
                email: $email,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getEmails()->trigger(self::EVENT_AFTER_DELETE_EMAIL, $afterEvent);
        }

        $this->clearCache();
    }

    /**
     * Send a commerce email.
     *
     * @param array|null $orderData Since the order may have changed by the time the email sends.
     * @param string $error The reason this method failed.
     * @throws Exception
     * @throws Throwable
     */
    public function sendEmail(Email $email, Order $order, ?OrderHistory $orderHistory = null, ?array $orderData = null, string &$error = ''): bool
    {
        if (!$email->enabled) {
            $error = t('Email is not enabled.', category: 'commerce');
            return false;
        }

        if ($email->storeId !== $order->getStore()->id) {
            $error = t('Email unavailable.', category: 'commerce');
            return false;
        }

        // Set Craft to the site template mode
        $view = \Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
        $option = 'email';
        $generalConfig = \Craft::$app->getConfig()->getGeneral();
        // Temporarily disable lazy transform generation
        $generateTransformsBeforePageLoad = $generalConfig->generateTransformsBeforePageLoad;
        $generalConfig->generateTransformsBeforePageLoad = true;

        // Make sure date vars are in the correct format
        $dateFields = ['dateOrdered', 'datePaid', 'dateFirstPaid'];
        foreach ($dateFields as $dateField) {
            if (isset($order->{$dateField}) && !($order->{$dateField} instanceof DateTime) && $order->{$dateField}) {
                $order->{$dateField} = DateTimeHelper::toDateTime($order->{$dateField});
            }
        }

        //sending emails
        $renderVariables = compact('order', 'orderHistory', 'option', 'orderData');

        $mailer = \Craft::$app->getMailer();
        /** @var Message $newEmail */
        $newEmail = \Craft::createObject(['class' => $mailer->messageClass, 'mailer' => $mailer]);

        $originalLanguage = \Craft::$app->language;
        $originalFormattingLanguage = \Craft::$app->formattingLocale;
        $emailLanguage = $email->getRenderLanguage($order);
        $emailSite = $email->getRenderSite($order);

        Locale::switchAppLanguage($emailLanguage);

        $fromEmail = $email->getSenderAddress();
        $fromName = $email->getSenderName();

        if ($fromEmail) {
            $newEmail->setFrom($fromEmail);
        }

        if ($fromName && $fromEmail) {
            $newEmail->setFrom([$fromEmail => $fromName]);
        }

        if ($email->recipientType == EmailRecord::TYPE_CUSTOMER) {
            if ($order->getCustomer()) {
                $newEmail->setTo($order->getEmail());
            }
        }

        if ($email->recipientType == EmailRecord::TYPE_CUSTOM) {
            // To:
            try {
                $emails = $view->renderSandboxedString($email->getTo(), $renderVariables);
                $emails = preg_split('/[\s,]+/', (string)$emails);

                $newEmail->setTo($emails);
            } catch (\Exception $e) {
                \Craft::$app->getErrorHandler()->logException($e);

                $error = t('Email template parse error for custom email "{email}" in "To:". Order: "{order}". Template error: "{message}" {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], category: 'commerce');
                Log::error($error);

                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }
        }

        if (!$newEmail->getTo()) {
            $error = t('Email error. No email address found for order. Order: "{order}"', ['order' => $order->getShortNumber()], category: 'commerce');
            Log::error($error);

            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
            $view->setTemplateMode($oldTemplateMode);
            $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

            return false;
        }

        // BCC:
        if ($bccSetting = $email->getBcc()) {
            try {
                $bcc = $view->renderSandboxedString($bccSetting, $renderVariables);
                $bcc = str_replace(';', ',', $bcc);
                $bcc = preg_split('/[\s,]+/', $bcc);

                if (array_filter($bcc)) {
                    $newEmail->setBcc($bcc);
                }
            } catch (\Exception $e) {
                \Craft::$app->getErrorHandler()->logException($e);

                $error = t('Email template parse error for email "{email}" in "BCC:". Order: "{order}". Template error: "{message}" {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], category: 'commerce');
                Log::error($error);

                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }
        }

        // CC:
        if ($ccSetting = $email->getCc()) {
            try {
                $cc = $view->renderSandboxedString($ccSetting, $renderVariables);
                $cc = str_replace(';', ',', $cc);
                $cc = preg_split('/[\s,]+/', $cc);

                if (array_filter($cc)) {
                    $newEmail->setCc($cc);
                }
            } catch (\Exception $e) {
                \Craft::$app->getErrorHandler()->logException($e);

                $error = t('Email template parse error for email "{email}" in "CC:". Order: "{order}". Template error: "{message}" {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], category: 'commerce');
                Log::error($error);

                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }
        }

        if ($email->replyTo) {
            // Reply To:
            try {
                $newEmail->setReplyTo($view->renderSandboxedString($email->replyTo, $renderVariables));
            } catch (\Exception $e) {
                \Craft::$app->getErrorHandler()->logException($e);

                $error = t('Email template parse error for email "{email}" in "ReplyTo:". Order: "{order}". Template error: "{message}" {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], category: 'commerce');
                Log::error($error);

                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }
        }

        // Subject:
        try {
            $newEmail->setSubject($view->renderSandboxedString($email->subject, $renderVariables));
        } catch (\Exception $e) {
            \Craft::$app->getErrorHandler()->logException($e);

            $error = t('Email template parse error for email "{email}" in "Subject:". Order: "{order}". Template error: "{message}" {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], category: 'commerce');
            Log::error($error);

            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
            $view->setTemplateMode($oldTemplateMode);
            $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

            return false;
        }

        // Template Path
        try {
            $templatePath = $view->renderSandboxedString($email->templatePath, $renderVariables);
        } catch (\Exception $e) {
            \Craft::$app->getErrorHandler()->logException($e);

            $error = t('Email template path parse error for email "{email}" in "Template Path". Order: "{order}". Template error: "{message}" {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], category: 'commerce');
            Log::error($error);

            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
            $view->setTemplateMode($oldTemplateMode);
            $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

            return false;
        }

        // Email Body
        if (!$view->doesTemplateExist($templatePath)) {
            $error = t('Email template does not exist at "{templatePath}" which resulted in "{templateParsedPath}" for email "{email}". Order: "{order}".', [
                'templatePath' => $email->templatePath,
                'templateParsedPath' => $templatePath,
                'email' => $email->name,
                'order' => $order->getShortNumber(),
            ], category: 'commerce');
            Log::error($error);

            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
            $view->setTemplateMode($oldTemplateMode);
            $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

            return false;
        }
        // Plain Text Template Path
        $plainTextTemplatePath = null;

        if ($email->plainTextTemplatePath) {
            try {
                $plainTextTemplatePath = $view->renderSandboxedString($email->plainTextTemplatePath, $renderVariables);
            } catch (\Exception $e) {
                \Craft::$app->getErrorHandler()->logException($e);

                $error = t('Email plain text template path parse error for email "{email}" in "Template Path". Order: "{order}". Template error: "{message}" {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], category: 'commerce');
                Log::error($error);

                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }

            // Plain Text Body
            if ($plainTextTemplatePath && !$view->doesTemplateExist($plainTextTemplatePath)) {
                $error = t('Email plain text template does not exist at "{templatePath}" which resulted in "{templateParsedPath}" for email "{email}". Order: "{order}".', [
                    'templatePath' => $email->plainTextTemplatePath,
                    'templateParsedPath' => $plainTextTemplatePath,
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                ], category: 'commerce');
                Log::error($error);

                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }
        }

        if ($pdf = $email->getPdf()) {
            // Email Body
            if (!$view->doesTemplateExist($pdf->templatePath)) {
                $error = t('Email PDF template does not exist at "{templatePath}" for email "{email}". Order: "{order}".', [
                    'templatePath' => $pdf->templatePath,
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                ], category: 'commerce');
                Log::error($error);

                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }

            try {
                $renderedPdf = app(Pdfs::class)->renderPdfForOrder($order, 'email', null, [], $pdf);

                $tempPath = Assets::tempFilePath('pdf');

                file_put_contents($tempPath, $renderedPdf);

                $fileName = '';
                $defaultFileName = $pdf->handle . '-' . $order->number;
                if ($pdf->fileNameFormat) {
                    try {
                        $fileName = $view->renderSandboxedObjectTemplate($pdf->fileNameFormat, $order);
                    } catch (\Throwable) {
                        $fileName = $defaultFileName;
                    }
                }

                if (!$fileName) {
                    $fileName = $defaultFileName;
                }

                // Attachment information
                $options = ['fileName' => $fileName . '.pdf', 'contentType' => 'application/pdf'];
                $newEmail->attach($tempPath, $options);
            } catch (\Exception $e) {
                \Craft::$app->getErrorHandler()->logException($e);

                $error = t('Email PDF generation error for email "{email}". Order: "{order}". PDF Template error: "{message}" {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], category: 'commerce');
                Log::error($error);

                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }
        }

        $originalSiteId = \Craft::$app->getSites()->getCurrentSite()->id;
        \Craft::$app->getSites()->setCurrentSite($emailSite);

        // Render HTML body
        try {
            $body = $view->renderTemplate($templatePath, $renderVariables);
            $newEmail->setHtmlBody($body);
        } catch (\Exception $e) {
            \Craft::$app->getErrorHandler()->logException($e);

            $error = t('Email template parse error for email "{email}". Order: "{order}". Template error: "{message}" {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], category: 'commerce');
            Log::error($error);

            \Craft::$app->getSites()->setCurrentSite($originalSiteId);
            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
            $view->setTemplateMode($oldTemplateMode);
            $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

            return false;
        }

        // Render Plain Text body
        if ($plainTextTemplatePath) {
            try {
                $plainTextBody = $view->renderTemplate($plainTextTemplatePath, $renderVariables);
                $newEmail->setTextBody($plainTextBody);
            } catch (\Exception $e) {
                \Craft::$app->getErrorHandler()->logException($e);

                $error = t('Email plain text template parse error for email "{email}". Order: "{order}". Template error: "{message}" {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], category: 'commerce');
                Log::error($error);

                \Craft::$app->getSites()->setCurrentSite($originalSiteId);
                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }
        }

        try {
            // Raise 'beforeSendEmail' event
            $event = new MailEvent(
                craftEmail: $newEmail,
                commerceEmail: $email,
                order: $order,
                orderHistory: $orderHistory,
                orderData: $orderData,
            );

            // TODO: migrate event firing to Laravel once event system is bridged
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getEmails()->trigger(self::EVENT_BEFORE_SEND_MAIL, $event);

            if (!$event->isValid) {
                $notice = t('Email "{email}" for order {order} was cancelled.', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                ], category: 'commerce');

                Log::info($notice);

                \Craft::$app->getSites()->setCurrentSite($originalSiteId);
                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                // Plugins that stop a email being sent should not declare that the sending failed, just that it would blocking of the send.
                // The blocking of the send will still be logged as an error though for now.
                // @TODO Clean up this behavior in Commerce 6.0 so plugins that block a send can signal "blocked" distinctly from "failed" without it being logged as an error #COM-49
                // https://github.com/craftcms/commerce/issues/1842
                return true;
            }

            if (!\Craft::$app->getMailer()->send($newEmail)) {
                $error = t('Commerce email "{email}" could not be sent for order "{order}".', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                ], category: 'commerce');

                Log::error($error);

                \Craft::$app->getSites()->setCurrentSite($originalSiteId);
                Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
                $view->setTemplateMode($oldTemplateMode);
                $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

                return false;
            }
        } catch (\Exception $e) {
            \Craft::$app->getErrorHandler()->logException($e);

            $error = t('Email "{email}" could not be sent for order "{order}". Error: {error} {file}:{line}', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'email' => $email->name,
                'order' => $order->getShortNumber(),
            ], category: 'commerce');

            Log::error($error);

            \Craft::$app->getSites()->setCurrentSite($originalSiteId);
            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
            $view->setTemplateMode($oldTemplateMode);
            $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

            return false;
        }

        // Raise 'afterSendEmail' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getEmails()->hasEventHandlers(self::EVENT_AFTER_SEND_MAIL)) {
            $afterEvent = new MailEvent(
                craftEmail: $newEmail,
                commerceEmail: $email,
                order: $order,
                orderHistory: $orderHistory,
                orderData: $orderData,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getEmails()->trigger(self::EVENT_AFTER_SEND_MAIL, $afterEvent);
        }

        \Craft::$app->getSites()->setCurrentSite($originalSiteId);
        Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
        $view->setTemplateMode($oldTemplateMode);
        $generalConfig->generateTransformsBeforePageLoad = $generateTransformsBeforePageLoad;

        // Clear out the temp PDF file if it was created.
        if (!empty($tempPath)) {
            unlink($tempPath);
        }

        return true;
    }

    /**
     * Get all emails by an order status ID.
     *
     * @return Email[]
     */
    public function getAllEmailsByOrderStatusId(int $id): array
    {
        $results = $this->query()
            ->join(Table::ORDERSTATUS_EMAILS . ' as statusEmails', 'emails.id', '=', 'statusEmails.emailId')
            ->join(Table::ORDERSTATUSES . ' as orderStatuses', 'statusEmails.orderStatusId', '=', 'orderStatuses.id')
            ->where('orderStatuses.id', $id)
            ->get();

        $emails = [];

        foreach ($results as $row) {
            $emails[] = new Email((array)$row);
        }

        return $emails;
    }

    private function query(): Builder
    {
        return DB::table(Table::EMAILS . ' as emails')
            ->select([
                'emails.bcc',
                'emails.cc',
                'emails.enabled',
                'emails.id',
                'emails.language',
                'emails.name',
                'emails.pdfId',
                'emails.plainTextTemplatePath',
                'emails.recipientType',
                'emails.renderSiteId',
                'emails.replyTo',
                'emails.senderAddress',
                'emails.senderName',
                'emails.storeId',
                'emails.subject',
                'emails.templatePath',
                'emails.to',
                'emails.uid',
            ])
            ->orderBy('emails.name');
    }

    /**
     * Gets an email record by uid.
     */
    private function getEmailRecord(string $uid): EmailRecord
    {
        if ($email = EmailRecord::findOne(['uid' => $uid])) {
            return $email;
        }

        return new EmailRecord();
    }

    protected function clearCache(): void
    {
        $this->allEmails = null;
    }
}
