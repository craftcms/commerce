<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use yii\db\ActiveQueryInterface;

/**
 * Order settings record.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $handle
 * @property int         $fieldLayoutId
 *
 * @property FieldLayout $fieldLayout
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class OrderSettings extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_ordersettings}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['handle'], 'unique']
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}