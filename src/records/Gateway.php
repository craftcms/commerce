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
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Gateway extends ActiveRecord
{
    /**
     * @return string
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
            [['name'], 'unique', 'targetAttribute' => ['name']]
        ];
    }
}
