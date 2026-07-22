<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\enums;

use Craft;
use craft\commerce\base\EnumHelpersTrait;

/**
 * Contains Purchasables Match enum
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 */
enum ContainsPurchasablesMatch: string
{
    use EnumHelpersTrait;

    case Any = 'any';
    case All = 'all';
    case Only = 'only';

    public function label(): string
    {
        return match ($this) {
            self::Any => Craft::t('commerce', 'any'),
            self::All => Craft::t('commerce', 'all'),
            self::Only => Craft::t('commerce', 'only'),
        };
    }
}
