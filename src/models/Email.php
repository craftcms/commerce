<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\HasStoreInterface;
use craft\commerce\base\Model;
use craft\commerce\base\StoreTrait;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;

/**
 * Email model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read string $pdfTemplatePath
 * @property-read null|Pdf $pdf
 * @property-read array $config
 * @property string|null $senderAddress
 */
class Email extends Model implements HasStoreInterface
{
    use StoreTrait;

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Subject
     */
    public ?string $subject = null;

    /**
     * @var string Recipient Type
     */
    public string $recipientType = EmailRecord::TYPE_CUSTOMER;

    /**
     * @var string|null To
     */
    public ?string $to = null;

    /**
     * @var string|null Bcc
     */
    public ?string $bcc = null;

    /**
     * @var string|null Cc
     */
    public ?string $cc = null;

    /**
     * @var string|null Reply to
     */
    public ?string $replyTo = null;

    /**
     * @var bool Is Enabled
     */
    public bool $enabled = true;

    /**
     * @var string|null Template path
     */
    public ?string $templatePath = null;

    /**
     * @var string|null Plain Text Template path
     */
    public ?string $plainTextTemplatePath = null;

    /**
     * @var int|null The PDF UID.
     */
    public ?int $pdfId = null;

    /**
     * @var string The language.
     */
    public string $language = EmailRecord::LOCALE_ORDER_LANGUAGE;

    /**
     * @var string|null
     * @since 5.0.0
     * @see setSenderAddress()
     * @see getSenderAddress()
     */
    private ?string $_senderAddress = null;

    /**
     * @var string|null
     * @since 5.0.0
     * @see setSenderName()
     * @see getSenderName()
     */
    private ?string $_senderName = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields = parent::extraFields();
        $fields[] = 'pdf';
        $fields[] = 'config';

        return $fields;
    }

    /**
     * Determines the language this pdf, if
     *
     * @param Order|null $order
     */
    public function getRenderLanguage(Order $order = null): string
    {
        $language = $this->language;

        if ($order == null && $language == EmailRecord::LOCALE_ORDER_LANGUAGE) {
            throw new InvalidArgumentException('Can not get language for this email without providing an order');
        }

        if ($order && $language == EmailRecord::LOCALE_ORDER_LANGUAGE) {
            $language = $order->orderLanguage;
        }

        return $language;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['subject', 'name', 'templatePath', 'language'], 'required'],
            [['recipientType'], 'in', 'range' => [EmailRecord::TYPE_CUSTOMER, EmailRecord::TYPE_CUSTOM]],
            [
                ['to'],
                'required',
                'when' => static function($model) {
                    return $model->recipientType == EmailRecord::TYPE_CUSTOM;
                },
            ],
            [
                [
                    'bcc',
                    'cc',
                    'enabled',
                    'id',
                    'language',
                    'name',
                    'pdfId',
                    'plainTextTemplatePath',
                    'recipientType',
                    'replyTo',
                    'senderAddress',
                    'senderName',
                    'storeId',
                    'subject',
                    'templatePath',
                    'to',
                    'uid',
                ],
                'safe',
            ],
        ];
    }

    /**
     * @throws InvalidConfigException
     */
    public function getPdf(): ?Pdf
    {
        if (!$this->pdfId) {
            return null;
        }
        return Plugin::getInstance()->getPdfs()->getPdfById($this->pdfId, $this->storeId);
    }

    /**
     * @param string|null $senderAddress
     * @return void
     * @since 5.0.0
     */
    public function setSenderAddress(?string $senderAddress): void
    {
        $this->_senderAddress = $senderAddress;
    }

    /**
     * @param bool $parse
     * @return string|null Default email address Commerce system messages should be sent from.
     *
     * If `null` (default), Craft’s [MailSettings::$fromEmail](craft4:craft\models\MailSettings::$fromEmail) will be used.
     *
     * @since 5.0.0
     */
    public function getSenderAddress(bool $parse = true): ?string
    {
        if (!$parse) {
            return $this->_senderAddress;
        }

        if (!$senderAddress = App::parseEnv($this->_senderAddress)) {
            $senderAddress = App::parseEnv(App::mailSettings()->fromEmail);
        }

        return $senderAddress;
    }

    /**
     * @param string|null $senderName
     * @return void
     * @since 5.0.0
     */
    public function setSenderName(?string $senderName): void
    {
        $this->_senderName = $senderName;
    }

    /**
     * @param bool $parse
     * @return string|null Placeholder value displayed for the sender name control panel settings field.
     *
     * If `null` (default), Craft’s [MailSettings::$fromName](craft4:craft\models\MailSettings::$fromName) will be used.

     * @since 5.0.0
     */
    public function getSenderName(bool $parse = true): ?string
    {
        if (!$parse) {
            return $this->_senderName;
        }

        if (!$senderName = App::parseEnv($this->_senderName)) {
            $senderName = App::parseEnv(App::mailSettings()->fromName);
        }

        return $senderName;
    }

    /**
     * Returns the field layout config for this email.
     *
     * @throws InvalidConfigException
     * @since 3.2.0
     */
    public function getConfig(): array
    {
        return [
            'bcc' => $this->bcc ?: null,
            'cc' => $this->cc ?: null,
            'senderAddress' => $this->getSenderAddress(false) ?: null,
            'senderName' => $this->getSenderName(false) ?: null,
            'enabled' => $this->enabled,
            'language' => $this->language,
            'name' => $this->name,
            'pdf' => $this->getPdf()?->uid,
            'plainTextTemplatePath' => $this->plainTextTemplatePath ?? null,
            'recipientType' => $this->recipientType,
            'replyTo' => $this->replyTo ?: null,
            'store' => $this->getStore()->uid,
            'subject' => $this->subject,
            'templatePath' => $this->templatePath ?: null,
            'to' => $this->to ?: null,
        ];
    }

    /**
     * @return string
     * @throws InvalidConfigException
     * @since 5.0.0
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/emails/' . $this->getStore()->handle . '/' . $this->id);
    }
}
