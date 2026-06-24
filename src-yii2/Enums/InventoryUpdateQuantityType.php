<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\enums;

use craft\commerce\base\EnumHelpersTrait;

enum InventoryUpdateQuantityType: string
{
    use EnumHelpersTrait;

    case ADJUST = 'adjust';
    case SET = 'set';
}
