<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\models\OrderStatus;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\Json;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Email\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class OrderStatusesController
{
    use RespondsWithFlash;

    private bool $readOnly;

    public function __construct(GeneralConfig $generalConfig)
    {
        $this->readOnly = !$generalConfig->allowAdminChanges;
    }

    public function index(): string
    {
        $orderStatuses = [];
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        $stores->each(function(Store $store) use (&$orderStatuses) {
            $orderStatuses[$store->handle] = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($store->id);
        });

        return pageTemplate('commerce/settings/orderstatuses/index', [
            'orderStatuses' => $orderStatuses,
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
            $orderStatus = Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($id, $store->id);
            abort_if($orderStatus === null, 404);
        } else {
            $orderStatus = \Craft::createObject([
                'class' => OrderStatus::class,
                'attributes' => ['storeId' => $store->id],
            ]);
        }

        $statusColors = ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'];
        $nextAvailableColor = null;

        if ($orderStatus->id) {
            $title = $orderStatus->name;
        } else {
            $title = t('Create a new order status', category: 'commerce');

            $availableColors = $statusColors;
            Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($store->id)->each(function(OrderStatus $status) use (&$availableColors) {
                $key = array_search($status->color, $availableColors, true);
                if ($key !== false) {
                    unset($availableColors[$key]);
                }
            });

            $nextAvailableColor = !empty($availableColors) ? array_shift($availableColors) : 'green';
        }

        $emails = Plugin::getInstance()->getEmails()->getAllEmails($store->id)->mapWithKeys(fn(Email $email) => [$email->id => $email->name])->all();

        return new CpScreenResponse()
            ->title($title)
            ->crumbs([
                ['label' => t('Commerce', category: 'commerce'), 'url' => 'commerce'],
                ['label' => t('Settings'), 'url' => 'commerce/settings', 'ariaLabel' => t('Commerce Settings', category: 'commerce')],
                ['label' => t('Order Statuses', category: 'commerce'), 'url' => 'commerce/settings/orderstatuses'],
            ])
            ->selectedSubnavItem('settings')
            ->action('commerce/order-statuses/save')
            ->redirectUrl('commerce/settings/orderstatuses')
            ->contentTemplate('commerce/settings/orderstatuses/_edit', [
                'orderStatus' => $orderStatus,
                'statusColors' => $statusColors,
                'nextAvailableColor' => $nextAvailableColor,
                'emails' => $emails,
                'readOnly' => $this->readOnly,
            ]);
    }

    public function save(Request $request): Response
    {
        $id = $request->input('id');
        $storeId = $request->input('storeId');
        $orderStatus = $id ? Plugin::getInstance()->getOrderStatuses()->getOrderStatusById($id, $storeId) : null;
        $orderStatus ??= new OrderStatus();

        $orderStatus->storeId = $storeId;
        $orderStatus->name = $request->input('name');
        $orderStatus->handle = $request->input('handle');
        $orderStatus->color = $request->input('color');
        $orderStatus->description = $request->input('description');
        $orderStatus->default = (bool)$request->input('default');
        $emailIds = $request->input('emails', []) ?: [];

        if (!$id) {
            $orderStatus->sortOrder = new Query()
                    ->from(Table::ORDERSTATUSES)
                    ->where(['storeId' => $storeId])
                    ->max('[[sortOrder]]') + 1;
        }

        if (!Plugin::getInstance()->getOrderStatuses()->saveOrderStatus($orderStatus, $emailIds)) {
            return $this->asModelFailure($orderStatus, t('Couldn\'t save order status.', category: 'commerce'), 'orderStatus');
        }

        return $this->asModelSuccess($orderStatus, t('Order status saved.', category: 'commerce'), 'orderStatus');
    }

    public function getOrderStatuses(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $storeId = $request->input('storeId');
        abort_if(!$storeId, 400, 'Missing store id');

        $store = Plugin::getInstance()->getStores()->getStoreById($storeId);
        $allowableStoreIds = Plugin::getInstance()->getStores()->getStoresByUserId(currentUser()?->id)->map(fn(Store $s) => $s->id)->all();

        if (!$store || !in_array($store->id, $allowableStoreIds)) {
            return $this->asFailure(t('Invalid store.', category: 'commerce'));
        }

        $orderStatuses = Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses($storeId)->all();

        return $this->asSuccess(data: ['orderStatuses' => $orderStatuses]);
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $ids = Json::decode($request->input('ids'));

        if (!Plugin::getInstance()->getOrderStatuses()->reorderOrderStatuses($ids)) {
            return $this->asFailure(t('Couldn\'t reorder Order Statuses.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $orderStatusId = $request->input('id');
        abort_if(!$orderStatusId, 400, 'Missing order status id');

        $storeId = DB::table(Table::ORDERSTATUSES)->where('id', $orderStatusId)->value('storeId');

        if (!$storeId || !Plugin::getInstance()->getOrderStatuses()->deleteOrderStatusById((int)$orderStatusId, $storeId)) {
            return $this->asFailure(t('Couldn\'t archive Order Status.', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
