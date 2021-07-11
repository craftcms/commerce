<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use yii\base\InvalidArgumentException;

/**
 * Email model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read string $pdfTemplatePath
 * @property-read null|Pdf $pdf
 * @property-read array $config
 */
class Email extends Model
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Subject
     */
    public $subject;

    /**
     * @var string Recipient Type
     */
    public $recipientType;

    /**
     * @var string To
     */
    public $to;

    /**
     * @var string Bcc
     */
    public $bcc;

    /**
     * @var string Cc
     */
    public $cc;

    /**
     * @var string Reply to
     */
    public $replyTo;

    /**
     * @var bool Is Enabled
     */
    public $enabled = true;

    /**
     * @var string Template path
     */
    public $templatePath;

    /**
     * @var string Plain Text Template path
     */
    public $plainTextTemplatePath;

    /**
     * @var int The PDF UID.
     */
    public $pdfId;

    /**
     * @var string The language.
     */
    public $language;

    /**
     * @var string UID
     */
    public $uid;

    /**
     * Determines the language this pdf, if
     *
     * @param Order|null $order
     * @return string
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
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['subject', 'name', 'templatePath', 'language'], 'required'];
        $rules[] = [['recipientType'], 'in', 'range' => [EmailRecord::TYPE_CUSTOMER, EmailRecord::TYPE_CUSTOM]];
        $rules[] = [
            ['to'], 'required', 'when' => static function($model) {
                return $model->recipientType == EmailRecord::TYPE_CUSTOM;
            }
        ];
        return $rules;
    }

    /**
     * @return Pdf|null
     */
    public function getPdf()
    {
        if (!$this->pdfId) {
            return null;
        }
        return Plugin::getInstance()->getPdfs()->getPdfById($this->pdfId);
    }

    /**
     * Returns the field layout config for this email.
     *
     * @return array
     * @since 3.2.0
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'subject' => $this->subject,
            'recipientType' => $this->recipientType,
            'to' => $this->to ?: null,
            'bcc' => $this->bcc ?: null,
            'cc' => $this->cc ?: null,
            'replyTo' => $this->replyTo ?: null,
            'enabled' => (bool)$this->enabled,
            'plainTextTemplatePath' => $this->plainTextTemplatePath ?? null,
            'templatePath' => $this->templatePath ?: null,
            'language' => $this->language
        ];

        if ($pdf = $this->getPdf()) {
            $config['pdf'] = $pdf->uid;
        }

        return $config;
    }
}
