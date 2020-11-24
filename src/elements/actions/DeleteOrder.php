<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\actions;

use craft\elements\actions\Delete;

/**
 * Delete Element Action
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @deprecated since 3.2.4 Not needed, since we can just use the Delete action and pass in our confirmation/success strings
 */
class DeleteOrder extends Delete
{
}
