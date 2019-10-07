<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\db\SoftDeleteTrait;
use yii\db\ActiveQueryInterface;

/**
 * Order status record.
 *
 * @property string $color
 * @property bool $default
 * @property Email[] $emails
 * @property string $handle
 * @property int $id
 * @property bool $dateDeleted
 * @property string $name
 * @property int $sortOrder
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatus extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    use SoftDeleteTrait;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_orderstatuses}}';
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getEmails(): ActiveQueryInterface
    {
        return $this->hasMany(Email::class, ['id' => 'emailId'])->viaTable('{{%commerce_orderstatus_emails}}', ['orderStatusId' => 'id']);
    }
}
