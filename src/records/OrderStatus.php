<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Order status record.
 *
 * @property int     $id
 * @property string  $name
 * @property string  $handle
 * @property string  $color
 * @property int     $sortOrder
 * @property bool    $default
 *
 * @property Email[] $emails
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class OrderStatus extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
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
