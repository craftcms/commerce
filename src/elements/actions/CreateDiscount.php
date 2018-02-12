<?php

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;

/**
 * Class Create Discount
 *
 * @property void   $triggerHtml  the action’s trigger HTML
 * @property string $triggerLabel the action’s trigger label
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class CreateDiscount extends ElementAction
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('commerce', 'Create discount…');
    }

    /**
     * @inheritdoc
     */
    public function getTriggerHtml()
    {
        $js = <<<EOT
(function()
{
    var trigger = new Craft.ElementActionTrigger({
        handle: 'CreateDiscount',
        batch: true,
        activate: function(\$selectedItems)
        {
            Craft.redirectTo(Craft.getUrl('commerce/promotions/discounts/new', 'purchasableIds='+Craft.elementIndex.getSelectedElementIds().join('|')));
        }
    });
})();
EOT;

        Craft::$app->getView()->registerJs($js);
    }
}
