<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use craft\commerce\elements\Order;
use craft\helpers\Html;

/**
 * Gateway Order Condition
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.4.0
 */
class GatewayOrderCondition extends OrderCondition
{
    public function getBuilderHtml($readOnly = false): string
    {
        if ($readOnly) {
            return Html::disableInputs(fn() => parent::getBuilderHtml());
        }
        return parent::getBuilderHtml();
    }
}
