<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\records\Email as EmailRecord;

/**
 * Email model.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
    public $enabled;

    /**
     * @var string Template path
     */
    public $templatePath;

    /**
     * @var string Plain Text Template path
     */
    public $plainTextTemplatePath;

    /**
     * @var bool Whether the email should attach a pdf template
     */
    public $attachPdf;

    /**
     * @var string Template path to the pdf.
     */
    public $pdfTemplatePath;

    /**
     * @var string UID
     */
    public $uid;


    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name'], 'required'];
        $rules[] = [['subject'], 'required'];
        $rules[] = [['recipientType'], 'in', 'range' => [EmailRecord::TYPE_CUSTOMER, EmailRecord::TYPE_CUSTOM]];
        $rules[] = [
            ['to'], 'required', 'when' => static function($model) {
                return $model->recipientType == EmailRecord::TYPE_CUSTOM;
            }
        ];
        $rules[] = [['templatePath'], 'required'];
        return $rules;
    }
}
