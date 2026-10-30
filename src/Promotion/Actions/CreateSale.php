<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Actions;

use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Store\Stores;

use function CraftCms\Cms\t;

class CreateSale extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return t('Create sale…', category: 'commerce');
    }

    public function getTriggerHtml(): ?string
    {
        $currentStore = app(Stores::class)->getCurrentStore();
        $type = Json::encode(static::class);
        $url = Json::encode('commerce/store-management/' . $currentStore->handle . '/sales/new');
        $js = <<<JS
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
JS;

        HtmlStack::js($js);

        return null;
    }
}
