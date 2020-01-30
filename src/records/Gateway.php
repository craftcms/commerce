<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use DateTime;

/**
 * Gateway record.
 *
 * @property DateTime $dateArchived
 * @property bool $isFrontendEnabled
 * @property string $handle
 * @property int $id
 * @property bool $isArchived
 * @property string $name
 * @property string $paymentType
 * @property array $settings
 * @property int $sortOrder
 * @property string $type
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Gateway extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::GATEWAYS;
    }
}
