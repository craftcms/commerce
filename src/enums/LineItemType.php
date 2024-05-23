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
 * Line Item Type enum
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.1.0
 */
enum LineItemType: string
{
    use EnumHelpersTrait;

    case Custom = 'custom';

    case Purchasable = 'purchasable';

    /**
     * @return array
     */
    public static function types(): array
    {
        return [
            self::Custom->value => Craft::t('commerce', 'Custom'),
            self::Purchasable->value => Craft::t('commerce', 'Purchasable'),
        ];
    }

    /**
     * @return string
     */
    public function typeAsLabel(): string
    {
        return match ($this) {
            self::Custom => Craft::t('commerce', 'Custom'),
            self::Purchasable => Craft::t('commerce', 'Purchasable'),
        };
    }
}
