<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Gateway record.
 *
 * @property \DateTime $dateArchived
 * @property bool $frontendEnabled
 * @property string $handle
 * @property int $id
 * @property bool $isArchived
 * @property string $name
 * @property string $paymentType
 * @property bool $sendCartInfo
 * @property array $settings
 * @property int $sortOrder
 * @property string $type
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Gateway extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_gateways}}';
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['handle'], 'unique', 'targetAttribute' => ['handle']]
        ];
    }
}
