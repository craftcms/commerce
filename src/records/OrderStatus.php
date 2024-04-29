<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\base\StoreRecordTrait;
use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\db\SoftDeleteTrait;
use yii\base\InvalidConfigException;
use yii\db\ActiveQueryInterface;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * Order status record.
 *
 * @property string $color
 * @property bool $default
 * @property Email[] $emails
 * @property string $handle
 * @property string $description
 * @property int $id
 * @property bool $dateDeleted
 * @property string $name
 * @property int $sortOrder
 * @property int $storeId
 * @mixin SoftDeleteBehavior
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatus extends ActiveRecord
{
    use SoftDeleteTrait;
    use StoreRecordTrait;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::ORDERSTATUSES;
    }

    /**
     * @throws InvalidConfigException
     */
    public function getEmails(): ActiveQueryInterface
    {
        return $this->hasMany(Email::class, ['id' => 'emailId'])->viaTable(Table::ORDERSTATUS_EMAILS, ['orderStatusId' => 'id']);
    }
}
