<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use craft\records\Element;
use yii\db\ActiveQueryInterface;

/**
 * Purchasable record.
 *
 * @property int                          $id
 * @property string                       $sku
 * @property float                        $price
 *
 * @property \yii\db\ActiveQueryInterface $element
 * @property Variant                      $implicit
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Purchasable extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_purchasables}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['sku'], 'unique']
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }
}