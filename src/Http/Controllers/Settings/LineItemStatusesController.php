<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Data\LineItemStatus;
use CraftCms\Commerce\Order\LineItemStatuses;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class LineItemStatusesController
{
    use RespondsWithFlash;

    private bool $readOnly;

    public function __construct(GeneralConfig $generalConfig)
    {
        $this->readOnly = !$generalConfig->allowAdminChanges;
    }

    public function index(): string
    {
        $lineItemStatuses = [];
        $stores = app(Stores::class)->getAllStores();

        $stores->each(function(Store $store) use (&$lineItemStatuses) {
            $lineItemStatuses[$store->handle] = app(LineItemStatuses::class)->getAllLineItemStatuses($store->id);
        });

        return pageTemplate('commerce/settings/lineitemstatuses/index', [
            'lineItemStatuses' => $lineItemStatuses,
            'stores' => $stores->all(),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        if ($storeHandle === null || !$store = app(Stores::class)->getStoreByHandle($storeHandle)) {
            $store = app(Stores::class)->getPrimaryStore();
        }

        if ($id) {
            $lineItemStatus = app(LineItemStatuses::class)->getLineItemStatusById($id, $store->id);
            abort_if($lineItemStatus === null, 404);
        } else {
            $lineItemStatus = \Craft::createObject([
                'class' => LineItemStatus::class,
                'storeId' => $store->id,
            ]);
        }

        $statusColors = ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'];
        $nextAvailableColor = null;

        if ($lineItemStatus->id) {
            $title = $lineItemStatus->name;
        } else {
            $title = t('Create a new line item status', category: 'commerce');

            $availableColors = $statusColors;
            app(LineItemStatuses::class)->getAllLineItemStatuses($store->id)->each(function(LineItemStatus $status) use (&$availableColors) {
                $key = array_search($status->color, $availableColors, true);
                if ($key !== false) {
                    unset($availableColors[$key]);
                }
            });

            $nextAvailableColor = !empty($availableColors) ? array_shift($availableColors) : 'green';
        }

        return new CpScreenResponse()
            ->title($title)
            ->crumbs([
                ['label' => t('Commerce', category: 'commerce'), 'url' => 'commerce'],
                ['label' => t('Settings'), 'url' => 'commerce/settings', 'ariaLabel' => t('Commerce Settings', category: 'commerce')],
                ['label' => t('Line Item Statuses', category: 'commerce'), 'url' => 'commerce/settings/lineitemstatuses'],
            ])
            ->selectedSubnavItem('settings')
            ->action('commerce/line-item-statuses/save')
            ->redirectUrl('commerce/settings/lineitemstatuses')
            ->contentTemplate('commerce/settings/lineitemstatuses/_edit', [
                'lineItemStatus' => $lineItemStatus,
                'statusColors' => $statusColors,
                'nextAvailableColor' => $nextAvailableColor,
                'readOnly' => $this->readOnly,
            ]);
    }

    public function save(Request $request): Response
    {
        $id = $request->input('id') ? (int)$request->input('id') : null;
        $storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $lineItemStatus = $id ? app(LineItemStatuses::class)->getLineItemStatusById($id, $storeId) : null;
        $lineItemStatus ??= new LineItemStatus();

        $lineItemStatus->storeId = $storeId;
        $lineItemStatus->name = $request->input('name');
        $lineItemStatus->handle = $request->input('handle');
        $lineItemStatus->color = $request->input('color');
        $lineItemStatus->default = (bool)$request->input('default');

        if (!app(LineItemStatuses::class)->saveLineItemStatus($lineItemStatus)) {
            return $this->asModelFailure($lineItemStatus, t('Couldn\'t save line item status.', category: 'commerce'), 'lineItemStatus');
        }

        return $this->asModelSuccess($lineItemStatus, t('Order status saved.', category: 'commerce'), 'lineItemStatus');
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = Json::decode($request->input('ids'));

        if (!app(LineItemStatuses::class)->reorderLineItemStatuses($ids)) {
            return $this->asFailure(t('Couldn\'t reorder Line Item Statuses.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function archive(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $lineItemStatusId = $request->input('id');
        abort_if(!$lineItemStatusId, 400, 'Missing line item status id');

        $storeId = DB::table(Table::LINEITEMSTATUSES)->where('id', $lineItemStatusId)->value('storeId');

        if (!$storeId || !app(LineItemStatuses::class)->archiveLineItemStatusById((int)$lineItemStatusId, $storeId)) {
            return $this->asFailure(t('Couldn\'t archive Line Item Status.', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
