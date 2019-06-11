<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\db\ActiveRecord;
use DateTime;
use yii\db\ActiveQueryInterface;

/**
 * Product type record.
 *
 * @property int $id
 * @property int $gatewayId
 * @property string $name
 * @property string $handle
 * @property string $planInformationId
 * @property string $reference
 * @property bool $enabled
 * @property bool $isArchived
 * @property DateTime $dateArchived
 * @property string $planData
 * @property int $sortOrder
 * @property ActiveQueryInterface $gateway
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Plan extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_plans}}';
    }

    /**
     * Return the subscription plan's gateway
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getGateway(): ActiveQueryInterface
    {
        return $this->hasOne(Gateway::class, ['gatewayId' => 'id']);
    }
}
