<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\helpers\Json;
use craft\helpers\UrlHelper;

/**
 * CopyUrl represents a Copy URL element action.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3
 *
 * @property-read null $triggerHtml
 * @property-read string $triggerLabel
 */
class CopyLoadCartUrl extends ElementAction
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('commerce', 'Share cart…');
    }

    /**
     * @inheritdoc
     */
    public function getTriggerHtml(): ?string
    {
        $type = Json::encode(static::class);

        $url = UrlHelper::actionUrl('commerce/cart/load-cart', ['number' => '{number}']);
        $js = <<<JS
(() => {
    var url = "$url";
    new Craft.ElementActionTrigger({
        type: {$type},
        batch: false,
        validateSelection: function(\$selectedItems)
        {
            return !!\$selectedItems.find('.element').data('number');
        },
        activate: function(\$selectedItems)
        {
            Craft.ui.createCopyTextPrompt({
                label: Craft.t('commerce', 'Copy the URL'),
                instructions: Craft.t('commerce', 'This URL will load the cart into the user’s session, making it the active cart.'),
                value: url.replace("{number}", \$selectedItems.find('.element').data('number')),
            });
        }
    });
})();
JS;

        Craft::$app->getView()->registerJs($js);
        return null;
    }
}
