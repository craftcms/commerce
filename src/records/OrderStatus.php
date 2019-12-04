<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\db\SoftDeleteTrait;
use yii\db\ActiveQueryInterface;
use yii2tech\ar\position\PositionBehavior;

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
 * @mixin PositionBehavior
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatus extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    use SoftDeleteTrait;

    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {

        $bahaviors = parent::behaviors();

        $bahaviors['position'] = [
            'class' => PositionBehavior::class,
            'positionAttribute' => 'sortOrder',
        ];

        return $bahaviors;
    }

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::ORDERSTATUSES;
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getEmails(): ActiveQueryInterface
    {
        return $this->hasMany(Email::class, ['id' => 'emailId'])->viaTable(Table::ORDERSTATUS_EMAILS, ['orderStatusId' => 'id']);
    }
}
