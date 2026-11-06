<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\orders;

use craft\helpers\Html;

/**
 * The order condition a checkout uses to decide whether an order may be paid for.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.8.0
 */
class CheckoutOrderCondition extends OrderCondition
{
    /**
     * @inheritdoc
     */
    public function getBuilderHtml($readOnly = false): string
    {
        if ($readOnly) {
            return Html::disableInputs(fn() => parent::getBuilderHtml());
        }

        return parent::getBuilderHtml();
    }
}
