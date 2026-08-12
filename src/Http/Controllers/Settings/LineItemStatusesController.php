<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\models\LineItemStatus;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\helpers\Json;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Database\Table;
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
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        $stores->each(function(Store $store) use (&$lineItemStatuses) {
            $lineItemStatuses[$store->handle] = Plugin::getInstance()->getLineItemStatuses()->getAllLineItemStatuses($store->id);
        });

        return pageTemplate('commerce/settings/lineitemstatuses/index', [
            'lineItemStatuses' => $lineItemStatuses,
            'stores' => $stores->all(),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        if ($id) {
            $lineItemStatus = Plugin::getInstance()->getLineItemStatuses()->getLineItemStatusById($id, $store->id);
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
            Plugin::getInstance()->getLineItemStatuses()->getAllLineItemStatuses($store->id)->each(function(LineItemStatus $status) use (&$availableColors) {
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
        $id = $request->input('id');
        $lineItemStatus = $id ? Plugin::getInstance()->getLineItemStatuses()->getLineItemStatusById($id, $request->input('storeId')) : null;
        $lineItemStatus ??= new LineItemStatus();

        $lineItemStatus->storeId = $request->input('storeId');
        $lineItemStatus->name = $request->input('name');
        $lineItemStatus->handle = $request->input('handle');
        $lineItemStatus->color = $request->input('color');
        $lineItemStatus->default = (bool)$request->input('default');

        if (!Plugin::getInstance()->getLineItemStatuses()->saveLineItemStatus($lineItemStatus)) {
            return $this->asModelFailure($lineItemStatus, t('Couldn\'t save line item status.', category: 'commerce'), 'lineItemStatus');
        }

        return $this->asModelSuccess($lineItemStatus, t('Order status saved.', category: 'commerce'), 'lineItemStatus');
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $ids = Json::decode($request->input('ids'));

        if (!Plugin::getInstance()->getLineItemStatuses()->reorderLineItemStatuses($ids)) {
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

        if (!$storeId || !Plugin::getInstance()->getLineItemStatuses()->archiveLineItemStatusById((int)$lineItemStatusId, $storeId)) {
            return $this->asFailure(t('Couldn\'t archive Line Item Status.', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
