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
 * @property bool $attachPdf
 * @property string $pdfTemplatePath
 * @property string $to
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Email extends ActiveRecord
{
    // Constants
    // =========================================================================

    const TYPE_CUSTOMER = 'customer';
    const TYPE_CUSTOM = 'custom';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::EMAILS;
    }
}
