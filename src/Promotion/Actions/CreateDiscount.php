<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Promotion\Actions;

use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Store\Stores;
use Symfony\Component\HttpFoundation\RedirectResponse;

use function CraftCms\Cms\t;

class CreateDiscount extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return t('Create discount…', category: 'commerce');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $currentStore = app(Stores::class)->getCurrentStore();

        $this->setResponse(new RedirectResponse(Url::cpUrl("commerce/store-management/$currentStore->handle/discounts/new", [
            'purchasableIds' => implode('|', $query->ids()),
        ])));

        return true;
    }
}
