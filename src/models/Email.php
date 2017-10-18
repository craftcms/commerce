<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\records\Email as EmailRecord;

/**
 * Email model.
 *
 * @property int    $id
 * @property string $name
 * @property string $subject
 * @property string $recipientType
 * @property string $to
 * @property string $bcc
 * @property bool   $enabled
 * @property string $templatePath
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2017, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.commerce
 * @since     2.0
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
     * @var bool Is Enabled
     */
    public $enabled;

    /**
     * @var string Template path
     */
    public $templatePath;

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['id'], 'required'],
            [['name'], 'required'],
            [['subject'], 'required'],
            [['recipientType'], 'in', 'range' => [EmailRecord::TYPE_CUSTOMER, EmailRecord::TYPE_CUSTOM]],
            [['to'], 'required'],
            [['enabled'], 'required'],
            [['templatePath'], 'required']
        ];
    }
}
