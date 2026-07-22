<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\enums;

use craft\commerce\base\EnumHelpersTrait;

/**
 * Order Notice Type enum
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.7.0
 */
enum OrderNoticeType: string
{
    use EnumHelpersTrait;

    case Customer = 'customer';

    case Admin = 'admin';
}
