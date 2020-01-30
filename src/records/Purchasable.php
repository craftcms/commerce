<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;

/**
 * Purchasable record.
 *
 * @property ActiveQueryInterface $element
 * @property int $id
 * @property string $description
 * @property float $price
 * @property string $sku
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Purchasable extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::PURCHASABLES;
    }

    /**
     * @return ActiveQuery
     */
    public static function find()
    {
        return parent::find()
            ->innerJoinWith(['element element'])
            ->where(['element.dateDeleted' => null]);
    }

    /**
     * @return ActiveQuery
     */
    public static function findWithTrashed(): ActiveQuery
    {
        return static::find()->where([]);
    }

    /**
     * @return ActiveQuery
     */
    public static function findTrashed(): ActiveQuery
    {
        return static::find()->where(['not', ['element.dateDeleted' => null]]);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}
