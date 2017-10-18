<?php

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;

/**
 * Class Create Sale
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.elementactions
 * @since     1.0
 */
class CreateSale extends ElementAction
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return Craft::t('commerce', 'Create sale…');
    }

    /**
     * @inheritDoc
     */
    public function getTriggerHtml()
    {
        $js = <<<EOT
(function()
{
    var trigger = new Craft.ElementActionTrigger({
        handle: 'Commerce_CreateSale',
        batch: true,
        activate: function(\$selectedItems)
        {
            Craft.redirectTo(Craft.getUrl('commerce/promotions/sales/new', 'productIds='+Craft.elementIndex.getSelectedElementIds().join('|')));
        }
    });
})();
EOT;

        Craft::$app->getView()->registerJs($js);
    }
}
