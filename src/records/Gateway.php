<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Gateway record.
 *
 * @property int    $id
 * @property string $name
 * @property string $handle
 * @property string $paymentType
 * @property array  $settings
 * @property string $type
 * @property bool   $frontendEnabled
 * @property bool   $sendCartInfo
 * @property bool   $isArchived
 * @property bool   $dateArchived
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
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
