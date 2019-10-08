<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Tax zone state record.
 *
 * @property int $id
 * @property ActiveQueryInterface $state
 * @property int $stateId
 * @property ActiveQueryInterface $taxZone
 * @property int $taxZoneId
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxZoneState extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::TAXZONE_STATES;
    }

    /**
     * @inheritdoc
     */
    public function rules()
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
