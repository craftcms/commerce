<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Tax zone state record.
 *
 * @property int                  $taxZoneId
 * @property ActiveQueryInterface $state
 * @property ActiveQueryInterface $taxZone
 * @property int                  $stateId
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class TaxZoneState extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_taxzone_states}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['taxZoneId', 'stateId'], 'unique', 'targetAttribute' => ['taxZoneId', 'stateId']]
        ];
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getTaxZone(): ActiveQueryInterface
    {
        return $this->hasOne(TaxZone::class, ['id' => 'taxZoneId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getState(): ActiveQueryInterface
    {
        return $this->hasOne(State::class, ['id' => 'stateId']);
    }
}
