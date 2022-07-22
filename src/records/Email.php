<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;

/**
 * Email record.
 *
 * @property string $bcc
 * @property string $cc
 * @property string $replyTo
 * @property bool $enabled
 * @property int $id
 * @property string $name
 * @property string $recipientType
 * @property string $subject
 * @property string $templatePath
 * @property string $plainTextTemplatePath
 * @property int|null $pdfId
 * @property string $to
 * @property string $language
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Email extends ActiveRecord
{
    public const LOCALE_ORDER_LANGUAGE = 'orderLanguage';

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_CUSTOM = 'custom';

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::EMAILS;
    }
}
