<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\ViewModels;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Http\Requests\ElementIndexRequest;
use CraftCms\Cms\Http\ViewModels\ContentIndexViewModel;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use Illuminate\Support\Facades\Gate;
use Override;

use function CraftCms\Cms\t;

/**
 * The Inertia payload for the inventory transfers index (`commerce::inventory/transfers/Index`).
 */
class TransferIndexViewModel extends ContentIndexViewModel
{
    public function __construct(ElementIndexRequest $request)
    {
        parent::__construct(Transfer::class, $request);
    }

    /**
     * Public so the page can build its index route from it.
     */
    #[Override]
    public function indexUrl(): string
    {
        return Url::cpUrl('commerce/inventory/transfers');
    }

    public function newTransferUrl(): ?string
    {
        if (!Gate::allows('save', new Transfer())) {
            return null;
        }

        return Url::actionUrl('commerce/transfers/create');
    }

    public function newTransferLabel(): string
    {
        return t('New transfer', category: 'commerce');
    }

    /**
     * @return list<ActionItem|array<string, mixed>>
     */
    #[Override]
    public function crumbs(): array
    {
        return [
            new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
            ...parent::crumbs(),
        ];
    }
}
