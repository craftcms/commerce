<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Email record.
 *
 * @property int    $id
 * @property string $name
 * @property string $subject
 * @property string $recipientType
 * @property string $to
 * @property string $bcc
 * @property bool   $enabled
 * @property string $templatePath
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Email extends ActiveRecord
{
    const TYPE_CUSTOMER = 'customer';
    const TYPE_CUSTOM = 'custom';

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_emails}}';
    }

}