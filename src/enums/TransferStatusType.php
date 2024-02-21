<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\enums;

use Craft;
use craft\commerce\base\EnumHelpersTrait;

enum TransferStatusType: string
{
    use EnumHelpersTrait;

    case DRAFT = 'draft';
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case RECEIVED = 'received';

    public function label(): string
    {
        // for each case, return a nicer label
        return match ($this) {
            self::DRAFT => Craft::t('commerce', 'Draft'),
            self::PENDING => Craft::t('commerce', 'Pending'),
            self::PARTIAL => Craft::t('commerce', 'Partial'),
            self::RECEIVED => Craft::t('commerce', 'Received'),
        };
    }

    public function color(): string
    {
        // for each case, return a nicer label
        return match ($this) {
            self::DRAFT => 'blue',
            self::PENDING => 'yellow',
            self::PARTIAL => 'orange',
            self::RECEIVED => 'green',
        };
    }
}
