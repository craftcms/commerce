<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\conditions\addresses;

use craft\elements\conditions\addresses\AddressCondition;
use craft\helpers\Html;

/**
 * Gateway Address Condition
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.5
 */
class GatewayAddressCondition extends AddressCondition
{
    public function getBuilderHtml($readOnly = false): string
    {
        if ($readOnly) {
            return Html::disableInputs(fn() => parent::getBuilderHtml());
        }
        return parent::getBuilderHtml();
    }
}
