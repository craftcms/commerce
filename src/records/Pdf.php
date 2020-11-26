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
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property string $templatePath
 * @property string $fileNameFormat
 * @property string $sortOrder
 * @property bool $enabled
 * @property bool $isDefault
 * @property string $locale
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2
 */
class Pdf extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::PDFS;
    }
}
