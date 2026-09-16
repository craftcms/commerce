<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Actions;

use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Url;

use function CraftCms\Cms\t;

class CopyLoadCartUrl extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return t('Share cart…', category: 'commerce');
    }

    public function getTriggerHtml(): ?string
    {
        $type = Json::encode(static::class);
        $actionUrl = Json::encode(Url::actionUrl('commerce/orders/get-load-cart-url'));

        $jsTemplate = <<<'JS'
(() => {
    new Craft.ElementActionTrigger({
        type: %s,
        batch: false,
        validateSelection: function($selectedItems)
        {
            return !!$selectedItems.find('.element').data('number');
        },
        activate: function($selectedItems)
        {
            var number = $selectedItems.find('.element').data('number');
            Craft.sendActionRequest('GET', %s, {params: {number: number}}).then(function(response) {
                Craft.ui.createCopyTextPrompt({
                    label: Craft.t('commerce', 'Copy the URL'),
                    instructions: Craft.t('commerce', "This URL will load the cart into the user's session, making it the active cart."),
                    value: response.data.url,
                });
            });
        }
    });
})();
JS;

        HtmlStack::js(sprintf($jsTemplate, $type, $actionUrl));

        return null;
    }
}
