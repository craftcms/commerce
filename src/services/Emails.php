<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\EmailEvent;
use craft\commerce\events\MailEvent;
use craft\commerce\models\Email;
use craft\commerce\models\OrderHistory;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\mail\Message;
use DateTime;
use Throwable;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;

/**
 * Email service.
 *
 * @property array|Email[] $allEmails
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Emails extends Component
{
    /**
     * @event MailEvent The event that is raised before an email is sent.
     * You may set [[MailEvent::isValid]] to `false` to prevent the email from being sent.
     *
     * Plugins can get notified before an email is being sent out.
     *
     * ```php
     * use craft\commerce\events\MailEvent;
     * use craft\commerce\services\Emails;
     * use yii\base\Event;
     *
     * Event::on(Emails::class, Emails::EVENT_BEFORE_SEND_MAIL, function(MailEvent $e) {
     *      // Maybe prevent the email based on some business rules or client preferences.
     * });
     * ```
     */
    const EVENT_BEFORE_SEND_MAIL = 'beforeSendEmail';

    /**
     * @event MailEvent The event that is raised after an email is sent
     *
     * Plugins can get notified after an email has been sent out.
     *
     * ```php
     * use craft\commerce\events\MailEvent;
     * use craft\commerce\services\Emails;
     * use yii\base\Event;
     *
     * Event::on(Emails::class, Emails::EVENT_AFTER_SEND_MAIL, function(MailEvent $e) {
     *      // Perhaps add the email to a CRM system
     * });
     * ```
     */
    const EVENT_AFTER_SEND_MAIL = 'afterSendEmail';

    /**
     * @event EmailEvent The event that is triggered before an email is saved.
     */
    const EVENT_BEFORE_SAVE_EMAIL = 'beforeSaveEmail';

    /**
     * @event EmailEvent The event that is triggered after an email is saved.
     */
    const EVENT_AFTER_SAVE_EMAIL = 'afterSaveEmail';

    /**
     * @event EmailEvent The event that is triggered before an email is deleted.
     */
    const EVENT_BEFORE_DELETE_EMAIL = 'beforeDeleteEmail';

    /**
     * @event EmailEvent The event that is triggered after an email is deleted.
     */
    const EVENT_AFTER_DELETE_EMAIL = 'afterDeleteEmail';

    const CONFIG_EMAILS_KEY = 'commerce.emails';


    /**
     * Get an email by its ID.
     *
     * @param int $id
     * @return Email|null
     */
    public function getEmailById($id)
    {
        $result = $this->_createEmailQuery()
            ->where(['id' => $id])
            ->one();

        return $result ? new Email($result) : null;
    }

    /**
     * Get all emails.
     *
     * @return Email[]
     */
    public function getAllEmails(): array
    {
        $rows = $this->_createEmailQuery()->all();

        $emails = [];
        foreach ($rows as $row) {
            $emails[] = new Email($row);
        }

        return $emails;
    }

    /**
     * Get all emails that are enabled.
     *
     * @return Email[]
     */
    public function getAllEnabledEmails(): array
    {
        $rows = $this->_createEmailQuery()->andWhere(['enabled' => true])->all();

        $emails = [];
        foreach ($rows as $row) {
            $emails[] = new Email($row);
        }

        return $emails;
    }

    /**
     * Save an email.
     *
     * @param Email $email
     * @param bool $runValidation
     * @return bool
     * @throws Exception
     * @throws ErrorException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function saveEmail(Email $email, bool $runValidation = true): bool
    {
        $isNewEmail = !(bool)$email->id;

        // Fire a 'beforeSaveEmail' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_EMAIL)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_EMAIL, new EmailEvent([
                'email' => $email,
                'isNew' => $isNewEmail
            ]));
        }

        if ($runValidation && !$email->validate()) {
            Craft::info('Email not saved due to validation error(s).', __METHOD__);
            return false;
        }

        if ($isNewEmail) {
            $emailUid = StringHelper::UUID();
        } else {
            $emailUid = Db::uidById(Table::EMAILS, $email->id);
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $configData = [
            'name' => $email->name,
            'subject' => $email->subject,
            'recipientType' => $email->recipientType,
            'to' => $email->to,
            'bcc' => $email->bcc,
            'cc' => $email->cc,
            'replyTo' => $email->replyTo,
            'enabled' => (bool)$email->enabled,
            'templatePath' => $email->templatePath,
            'plainTextTemplatePath' => $email->plainTextTemplatePath ?? null,
            'attachPdf' => (bool)$email->attachPdf,
            'pdfTemplatePath' => $email->pdfTemplatePath,
        ];

        $configPath = self::CONFIG_EMAILS_KEY . '.' . $emailUid;
        $projectConfig->set($configPath, $configData);

        if ($isNewEmail) {
            $email->id = Db::idByUid(Table::EMAILS, $emailUid);
        }

        return true;
    }


    /**
     * Handle gateway status change.
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleChangedEmail(ConfigEvent $event)
    {
        $emailUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $emailRecord = $this->_getEmailRecord($emailUid);
            $isNewEmail = $emailRecord->getIsNewRecord();

            $emailRecord->name = $data['name'];
            $emailRecord->subject = $data['subject'];
            $emailRecord->recipientType = $data['recipientType'];
            $emailRecord->to = $data['to'];
            $emailRecord->bcc = $data['bcc'];
            $emailRecord->cc = $data['cc'] ?? null;
            $emailRecord->replyTo = $data['replyTo'] ?? null;
            $emailRecord->enabled = $data['enabled'];
            $emailRecord->templatePath = $data['templatePath'];
            $emailRecord->plainTextTemplatePath = $data['plainTextTemplatePath'] ?? null;
            $emailRecord->attachPdf = $data['attachPdf'];
            $emailRecord->pdfTemplatePath = $data['pdfTemplatePath'];
            $emailRecord->uid = $emailUid;

            $emailRecord->save(false);
            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Fire a 'afterSaveEmail' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_EMAIL)) {
            $this->trigger(self::EVENT_AFTER_SAVE_EMAIL, new EmailEvent([
                'email' => $this->getEmailById($emailRecord->id),
                'isNew' => $isNewEmail
            ]));
        }
    }

    /**
     * Delete an email by its ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteEmailById($id): bool
    {
        $email = EmailRecord::findOne($id);

        if ($email) {
            // Fire a 'beforeDeleteEmail' event
            if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_EMAIL)) {
                $this->trigger(self::EVENT_BEFORE_DELETE_EMAIL, new EmailEvent([
                    'email' => $this->getEmailById($id),
                ]));
            }

            Craft::$app->getProjectConfig()->remove(self::CONFIG_EMAILS_KEY . '.' . $email->uid);
        }

        return true;
    }

    /**
     * Handle email getting deleted.
     *
     * @param ConfigEvent $event
     * @return void
     */
    public function handleDeletedEmail(ConfigEvent $event)
    {
        $uid = $event->tokenMatches[0];
        $emailRecord = $this->_getEmailRecord($uid);

        if (!$emailRecord) {
            return;
        }

        $email = $this->getEmailById($emailRecord->id);
        $emailRecord->delete();

        // Fire a 'beforeDeleteEmail' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_EMAIL)) {
            $this->trigger(self::EVENT_AFTER_DELETE_EMAIL, new EmailEvent([
                'email' => $email
            ]));
        }
    }

    /**
     * Send a commerce email.
     *
     * @param Email $email
     * @param Order $order
     * @param OrderHistory $orderHistory
     * @param array $orderData Since the order may have changed by the time the email sends.
     * @return bool $result
     * @throws Exception
     * @throws Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function sendEmail($email, $order, $orderHistory = null, $orderData = null): bool
    {
        if (!$email->enabled) {
            return false;
        }

        // Set Craft to the site template mode
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
        $option = 'email';

        // Make sure date vars are in the correct format
        $dateFields = ['dateOrdered', 'datePaid'];
        foreach ($dateFields as $dateField) {
            if (isset($order->{$dateField}) && !($order->{$dateField} instanceof DateTime) && $order->{$dateField}) {
                $order->{$dateField} = DateTimeHelper::toDateTime($order->{$dateField});
            }
        }

        //sending emails
        $renderVariables = compact('order', 'orderHistory', 'option', 'orderData');

        $mailer = Craft::$app->getMailer();
        /** @var Message $newEmail */
        $newEmail = Craft::createObject(['class' => $mailer->messageClass, 'mailer' => $mailer]);

        $originalLanguage = Craft::$app->language;
        $craftMailSettings = App::mailSettings();

        $fromEmail = Plugin::getInstance()->getSettings()->emailSenderAddress ?: $craftMailSettings->fromEmail;
        $fromEmail = Craft::parseEnv($fromEmail);

        $fromName = Plugin::getInstance()->getSettings()->emailSenderName ?: $craftMailSettings->fromName;
        $fromName = Craft::parseEnv($fromName);

        if ($fromEmail) {
            $newEmail->setFrom($fromEmail);
        }

        if ($fromName && $fromEmail) {
            $newEmail->setFrom([$fromEmail => $fromName]);
        }

        if ($email->recipientType == EmailRecord::TYPE_CUSTOMER) {
            // use the order's language for template rendering the email fields and body.
            $orderLanguage = $order->orderLanguage ?: $originalLanguage;
            Craft::$app->language = $orderLanguage;

            if ($order->getCustomer()) {
                $newEmail->setTo($order->getEmail());
            }
        }

        if ($email->recipientType == EmailRecord::TYPE_CUSTOM) {
            // To:
            try {
                $emails = $view->renderString((string)$email->to, $renderVariables);
                $emails = preg_split('/[\s,]+/', $emails);

                $newEmail->setTo($emails);
            } catch (\Exception $e) {
                $error = Plugin::t('Email template parse error for custom email “{email}” in “To:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        if (!$newEmail->getTo()) {
            $error = Plugin::t('Email error. No email address found for order. Order: “{order}”', ['order' => $order->getShortNumber()]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // BCC:
        if ($email->bcc) {
            try {
                $bcc = $view->renderString((string)$email->bcc, $renderVariables);
                $bcc = str_replace(';', ',', $bcc);
                $bcc = preg_split('/[\s,]+/', $bcc);

                if (array_filter($bcc)) {
                    $newEmail->setBcc($bcc);
                }
            } catch (\Exception $e) {
                $error = Plugin::t('Email template parse error for email “{email}” in “BCC:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        // CC:
        if ($email->cc) {
            try {
                $cc = $view->renderString((string)$email->cc, $renderVariables);
                $cc = str_replace(';', ',', $cc);
                $cc = preg_split('/[\s,]+/', $cc);

                if (array_filter($cc)) {
                    $newEmail->setCc($cc);
                }
            } catch (\Exception $e) {
                $error = Plugin::t('Email template parse error for email “{email}” in “CC:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        if ($email->replyTo) {
            // Reply To:
            try {
                $newEmail->setReplyTo($view->renderString((string)$email->replyTo, $renderVariables));
            } catch (\Exception $e) {
                $error = Plugin::t('Email template parse error for email “{email}” in “ReplyTo:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        // Subject:
        try {
            $newEmail->setSubject($view->renderString((string)$email->subject, $renderVariables));
        } catch (\Exception $e) {
            $error = Plugin::t('Email template parse error for email “{email}” in “Subject:”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // Template Path
        try {
            $templatePath = $view->renderString((string)$email->templatePath, $renderVariables);
        } catch (\Exception $e) {
            $error = Plugin::t('Email template path parse error for email “{email}” in “Template Path”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // Email Body
        if (!$view->doesTemplateExist($templatePath)) {
            $error = Plugin::t('Email template does not exist at “{templatePath}” which resulted in “{templateParsedPath}” for email “{email}”. Order: “{order}”.', [
                'templatePath' => $email->templatePath,
                'templateParsedPath' => $templatePath,
                'email' => $email->name,
                'order' => $order->getShortNumber()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }
        // Plain Text Template Path
        $plainTextTemplatePath = null;
        try {
            $plainTextTemplatePath = $view->renderString((string)$email->plainTextTemplatePath, $renderVariables);
        } catch (\Exception $e) {
            $error = Plugin::t('Email plain text template path parse error for email “{email}” in “Template Path”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // Plain Text Body
        if ($plainTextTemplatePath && !$view->doesTemplateExist($plainTextTemplatePath)) {
            $error = Plugin::t('Email plain text template does not exist at “{templatePath}” which resulted in “{templateParsedPath}” for email “{email}”. Order: “{order}”.', [
                'templatePath' => $email->plainTextTemplatePath,
                'templateParsedPath' => $plainTextTemplatePath,
                'email' => $email->name,
                'order' => $order->getShortNumber()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        if ($email->attachPdf && $path = $email->pdfTemplatePath ?: Plugin::getInstance()->getSettings()->orderPdfPath) {
            // Email Body
            if (!$view->doesTemplateExist($path)) {
                $error = Plugin::t('Email PDF template does not exist at “{templatePath}” for email “{email}”. Order: “{order}”.', [
                    'templatePath' => $path,
                    'email' => $email->name,
                    'order' => $order->getShortNumber()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }

            try {
                $pdf = Plugin::getInstance()->getPdf()->renderPdfForOrder($order, 'email', $path);

                $tempPath = Assets::tempFilePath('pdf');

                file_put_contents($tempPath, $pdf);

                // Get a file name
                $filenameFormat = Plugin::getInstance()->getSettings()->orderPdfFilenameFormat;
                $fileName = $view->renderObjectTemplate($filenameFormat, $order);
                if (!$fileName) {
                    $fileName = 'Order-' . $order->number;
                }

                // Attachment information
                $options = ['fileName' => $fileName . '.pdf', 'contentType' => 'application/pdf'];
                $newEmail->attach($tempPath, $options);
            } catch (\Exception $e) {
                $error = Plugin::t('Email PDF generation error for email “{email}”. Order: “{order}”. PDF Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        // Render HTML body
        try {
            $body = $view->renderTemplate($templatePath, $renderVariables);
            $newEmail->setHtmlBody($body);
        } catch (\Exception $e) {
            $error = Plugin::t('Email template parse error for email “{email}”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                'email' => $email->name,
                'order' => $order->getShortNumber(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // Render Plain Text body
        if ($plainTextTemplatePath) {
            try {
                $plainTextBody = $view->renderTemplate($plainTextTemplatePath, $renderVariables);
                $newEmail->setTextBody($plainTextBody);
            } catch (\Exception $e) {
                $error = Plugin::t('Email plain text template parse error for email “{email}”. Order: “{order}”. Template error: “{message}” {file}:{line}', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber(),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        }

        try {
            //raising event
            $event = new MailEvent([
                'craftEmail' => $newEmail,
                'commerceEmail' => $email,
                'order' => $order,
                'orderHistory' => $orderHistory,
                'orderData' => $orderData
            ]);
            $this->trigger(self::EVENT_BEFORE_SEND_MAIL, $event);

            if (!$event->isValid) {
                $error = Plugin::t('Email “{email}”, for order "{order}" was cancelled by plugin.', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber()
                ]);

                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }

            if (!Craft::$app->getMailer()->send($newEmail)) {
                $error = Plugin::t('Commerce email “{email}” could not be sent for order “{order}”.', [
                    'email' => $email->name,
                    'order' => $order->getShortNumber()
                ]);

                Craft::error($error, __METHOD__);

                Craft::$app->language = $originalLanguage;
                $view->setTemplateMode($oldTemplateMode);

                return false;
            }
        } catch (\Exception $e) {
            $error = Plugin::t('Email “{email}” could not be sent for order “{order}”. Error: {error} {file}:{line}', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'email' => $email->name,
                'order' => $order->getShortNumber()
            ]);

            Craft::error($error, __METHOD__);

            Craft::$app->language = $originalLanguage;
            $view->setTemplateMode($oldTemplateMode);

            return false;
        }

        // Raise an 'afterSendEmail' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SEND_MAIL)) {
            $this->trigger(self::EVENT_AFTER_SEND_MAIL, new MailEvent([
                'craftEmail' => $newEmail,
                'commerceEmail' => $email,
                'order' => $order,
                'orderHistory' => $orderHistory,
                'orderData' => $orderData
            ]));
        }

        Craft::$app->language = $originalLanguage;
        $view->setTemplateMode($oldTemplateMode);

        // Clear out the temp PDF file if it was created.
        if (!empty($tempPath)) {
            unlink($tempPath);
        }

        return true;
    }

    /**
     * Get all emails by an order status ID.
     *
     * @param int $id
     * @return Email[]
     */
    public function getAllEmailsByOrderStatusId(int $id): array
    {
        $results = $this->_createEmailQuery()
            ->innerJoin(Table::ORDERSTATUS_EMAILS . ' statusEmails', '[[emails.id]] = [[statusEmails.emailId]]')
            ->innerJoin(Table::ORDERSTATUSES . ' orderStatuses', '[[statusEmails.orderStatusId]] = [[orderStatuses.id]]')
            ->where(['orderStatuses.id' => $id])
            ->all();

        $emails = [];

        foreach ($results as $row) {
            $emails[] = new Email($row);
        }

        return $emails;
    }


    /**
     * Returns a Query object prepped for retrieving Emails.
     *
     * @return Query
     */
    private function _createEmailQuery(): Query
    {
        return (new Query())
            ->select([
                'emails.id',
                'emails.name',
                'emails.subject',
                'emails.recipientType',
                'emails.to',
                'emails.bcc',
                'emails.cc',
                'emails.replyTo',
                'emails.enabled',
                'emails.templatePath',
                'emails.plainTextTemplatePath',
                'emails.attachPdf',
                'emails.pdfTemplatePath',
                'emails.uid',
            ])
            ->orderBy('name')
            ->from([Table::EMAILS . ' emails']);
    }


    /**
     * Gets an email record by uid.
     *
     * @param string $uid
     * @return EmailRecord
     */
    private function _getEmailRecord(string $uid): EmailRecord
    {
        if ($email = EmailRecord::findOne(['uid' => $uid])) {
            return $email;
        }

        return new EmailRecord();
    }
}
