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
    // Properties
    // =========================================================================

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

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['subject'], 'required'],
            [['recipientType'], 'in', 'range' => [EmailRecord::TYPE_CUSTOMER, EmailRecord::TYPE_CUSTOM]],
            [
                ['to'], 'required', 'when' => function($model) {
                return $model->recipientType == EmailRecord::TYPE_CUSTOM;
            }
            ],
            [['templatePath'], 'required']
        ];
    }
}
