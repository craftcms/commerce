<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\commerce\Plugin;
use craft\helpers\Json;

/**
 * Class Create Sale
 *
 * @property void $triggerHtml the action’s trigger HTML
 * @property string $triggerLabel the action’s trigger label
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CreateSale extends ElementAction
{
    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('commerce', 'Create sale…');
    }

    /**
     * @inheritdoc
     */
    public function getTriggerHtml(): ?string
    {
        $currentStore = Plugin::getInstance()->getStores()->getCurrentStore();
        $type = Json::encode(static::class);
        $url = Json::encode('commerce/store-management/' . $currentStore->handle . '/sales/new');
        $js = <<<EOT
(function()
{
    var trigger = new Craft.ElementActionTrigger({
        type: $type,
        batch: true,
        activate: function(\$selectedItems)
        {
            Craft.redirectTo(Craft.getUrl($url, 'purchasableIds='+Craft.elementIndex.getSelectedElementIds().join('|')));
        }
    });
})();
EOT;

        Craft::$app->getView()->registerJs($js);

        return null;
    }
}
