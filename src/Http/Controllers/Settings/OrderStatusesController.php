<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\db\Query;
use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Handle;
use CraftCms\Cms\Form\Controls\Lightswitch;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Enums\ChoicePresentation;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Commerce\Database\Table as DbTable;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Support\ObjectState;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\currentUser;
use function CraftCms\Cms\t;

class OrderStatusesController extends BaseSettingsController
{
    private const array STATUS_COLORS = ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'];

    protected function getSectionCrumb(): array
    {
        return ['label' => t('Order Statuses', category: 'commerce'), 'href' => cp_url('commerce/settings/orderstatuses')];
    }

    public function index(): CpScreenResponse
    {
        $stores = app(Stores::class)->getAllStores();
        $isMultiStore = $stores->count() > 1;

        // Every store's table can plant its own create action in the page's shared actions
        // slot, so with more than one store, only the first store's table gets one — a single
        // combined "New order status" menu covering every store, rather than one button apiece.
        $createMenuItems = $this->readOnly ? [] : $stores->map(fn(Store $store) => [
            'label' => $store->name,
            'url' => cp_url("commerce/settings/orderstatuses/{$store->handle}/new"),
        ])->all();
        $createMenuAssigned = false;

        $nodes = [];
        $stores->each(function(Store $store) use (&$nodes, $isMultiStore, $createMenuItems, &$createMenuAssigned) {
            if ($isMultiStore) {
                $nodes[] = Heading::make("{$store->handle}-heading", $store->name);
            }

            $rows = app(OrderStatuses::class)->getAllOrderStatuses($store->id)
                ->map(function(OrderStatus $orderStatus) {
                    $emailCount = count($orderStatus->getEmailIds());

                    return [
                        'id' => $orderStatus->id,
                        // getLabelHtml() already Html::encode()s the name it interpolates — safe to
                        // wrap directly, same as the pre-conversion Twig index did.
                        'name' => ['html' => Html::a($orderStatus->getLabelHtml(), $orderStatus->getCpEditUrl(), ['class' => 'cell-bold'])],
                        'handle' => ['html' => FormFields::copytextHtml(['value' => $orderStatus->handle, 'monospace' => true])],
                        'hasEmails' => $emailCount > 0 ? $emailCount : '',
                        'default' => $orderStatus->default ? ['icon' => 'check', 'label' => t('Yes')] : '',
                    ];
                })
                // getAllOrderStatuses() ends in a ->filter(), which (like the rest of Illuminate's
                // Collection) preserves original keys rather than reindexing — so with an item
                // filtered out upstream, ->all() can hand back a non-sequential array that
                // json_encodes as a JS object instead of an array. ->values() guarantees a plain list.
                ->values()
                ->all();

            $nodes[] = Table::make("{$store->handle}-order-statuses")
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'handle', 'label' => t('Handle')],
                    ['key' => 'hasEmails', 'label' => t('Has Emails?', category: 'commerce')],
                    ['key' => 'default', 'label' => t('Default Status?', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No order statuses exist yet.', category: 'commerce'))
                ->when(!$createMenuAssigned && $createMenuItems, function(Table $table) use ($createMenuItems, &$createMenuAssigned) {
                    $table->createActionMenu(t('New order status', category: 'commerce'), $createMenuItems);
                    $createMenuAssigned = true;
                })
                ->when(!$this->readOnly, fn(Table $table) => $table
                    ->reorderable(action([self::class, 'reorder']))
                    ->deletable(action([self::class, 'delete'])));
        });

        $title = t('Order Statuses', category: 'commerce');

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs())
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
            ]);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        if ($storeHandle === null || !$store = app(Stores::class)->getStoreByHandle($storeHandle)) {
            $store = app(Stores::class)->getPrimaryStore();
        }

        if ($id) {
            $orderStatus = app(OrderStatuses::class)->getOrderStatusById($id, $store->id);
            abort_if($orderStatus === null, 404);
        } else {
            $orderStatus = new OrderStatus(['storeId' => $store->id]);
        }

        if ($orderStatus->id) {
            $title = $orderStatus->name;
            $statusColor = $orderStatus->color;
        } else {
            $title = t('Create a new order status', category: 'commerce');

            $availableColors = self::STATUS_COLORS;
            app(OrderStatuses::class)->getAllOrderStatuses($store->id)->each(function(OrderStatus $status) use (&$availableColors) {
                $key = array_search($status->color, $availableColors, true);
                if ($key !== false) {
                    unset($availableColors[$key]);
                }
            });

            $statusColor = !empty($availableColors) ? array_shift($availableColors) : 'green';
        }

        $colorOptions = array_map(fn(string $color) => [
            'label' => ucfirst(t($color, category: 'commerce')),
            'labelHtml' => Html::tag('span', '', ['class' => "status $color"]) . Html::encode(ucfirst(t($color, category: 'commerce'))),
            'value' => $color,
        ], self::STATUS_COLORS);

        $emailOptions = app(Emails::class)->getAllEmails($store->id)
            ->map(fn(Email $email) => ['label' => $email->name, 'value' => $email->id])
            ->all();

        $emailsNode = Field::make(t('Status Emails', category: 'commerce'), Choice::make('emails')->multiple()->options($emailOptions))
            ->instructions(t('Select the emails that will be sent when transitioning to this status.', category: 'commerce'))
            ->warning($emailOptions === [] ? t('You currently have no emails configured to select for this status.', category: 'commerce') : null);

        // An existing default status can't be un-defaulted from its own screen — promote a
        // different status to default instead. A brand new status defaults to on when it'll be
        // the store's first (nothing else to be the default), same as the legacy behavior.
        $isDefault = $orderStatus->default
            || app(OrderStatuses::class)->getAllOrderStatuses($store->id)->count() === 0;

        $defaultNode = $orderStatus->default
            ? HiddenField::make('default')
            : Field::make(t('New orders get this status by default', category: 'commerce'), Lightswitch::make('default'));

        $handle = Handle::make('handle');
        if (!$orderStatus->id) {
            $handle->source('name');
        }

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($orderStatus->id) {
            $formNodes[] = HiddenField::make('sortOrder');
            $formNodes[] = HiddenField::make('id');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('What this status will be called in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Handle', category: 'commerce'), $handle)
            ->instructions(t('How you’ll refer to this status in the templates.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Description', category: 'commerce'), Text::make('description'))
            ->instructions(t('Order Status description.', category: 'commerce'));
        $formNodes[] = Field::make(t('Color', category: 'commerce'), Choice::make('color')->presentation(ChoicePresentation::Radios)->options($colorOptions))
            ->instructions(t('Choose a color to represent the order’s status', category: 'commerce'));
        $formNodes[] = $emailsNode;
        $formNodes[] = $defaultNode;

        $form = $this->formResolver->resolve(Form::make($formNodes), new FormContext(
            values: [
                'storeId' => $store->id,
                'sortOrder' => $orderStatus->sortOrder,
                'id' => $orderStatus->id,
                'name' => $orderStatus->name,
                'handle' => $orderStatus->handle,
                'description' => $orderStatus->description,
                'color' => $statusColor,
                'emails' => $orderStatus->getEmailIds(),
                'default' => $isDefault,
            ],
            mode: $this->generalConfig->allowAdminChanges ? ControlMode::Editable : ControlMode::ReadOnly,
        ));

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($orderStatus->id ? $this->crumbs(['label' => $title]) : $this->crumbs())
            ->action('commerce/order-statuses/save')
            ->redirectUrl('commerce/settings/orderstatuses')
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
            ]);
    }

    public function save(Request $request): Response
    {
        $id = $request->input('id') ? (int)$request->input('id') : null;
        $storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $this->requireStoreAccess($storeId);
        $orderStatus = $id ? app(OrderStatuses::class)->getOrderStatusById($id, $storeId) : null;
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
                    ->from(DbTable::ORDERSTATUSES)
                    ->where(['storeId' => $storeId])
                    ->max('[[sortOrder]]') + 1;
        }

        if (!app(OrderStatuses::class)->saveOrderStatus($orderStatus, $emailIds)) {
            return $this->asModelFailure($orderStatus, t('Couldn’t save order status.', category: 'commerce'), 'orderStatus');
        }

        return $this->asModelSuccess($orderStatus, t('Order status saved.', category: 'commerce'), 'orderStatus');
    }

    public function getOrderStatuses(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $storeId = $request->input('storeId');
        abort_if(!$storeId, 400, 'Missing store id');
        $storeId = (int)$storeId;

        $store = app(Stores::class)->getStoreById($storeId);
        $allowableStoreIds = app(Stores::class)->getStoresByUserId(currentUser()?->getCraftUserId())->map(fn(Store $s) => $s->id)->all();

        if (!$store || !in_array($store->id, $allowableStoreIds)) {
            return $this->asFailure(t('Invalid store.', category: 'commerce'));
        }

        $orderStatuses = app(OrderStatuses::class)->getAllOrderStatuses($storeId)->all();

        return $this->asSuccess(data: ['orderStatuses' => $orderStatuses]);
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = Json::decode($request->input('ids'));

        if (!app(OrderStatuses::class)->reorderOrderStatuses($ids)) {
            return $this->asFailure(t('Couldn’t reorder Order Statuses.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $orderStatusId = $request->input('id');
        abort_if(!$orderStatusId, 400, 'Missing order status id');

        $storeId = DB::table(DbTable::ORDERSTATUSES)->where('id', $orderStatusId)->value('storeId');

        if ($storeId) {
            $this->requireStoreAccess((int)$storeId);
        }

        if (!$storeId || !app(OrderStatuses::class)->deleteOrderStatusById((int)$orderStatusId, $storeId)) {
            return $this->asFailure(t('Couldn’t archive Order Status.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    private function requireStoreAccess(?int $storeId): void
    {
        if (!ObjectState::has($this, 'allowableStoreIds')) {
            ObjectState::set($this, 'allowableStoreIds', app(Stores::class)->getStoresByUserId(currentUser()?->getCraftUserId())->map(fn(Store $s) => $s->id)->all());
        }

        $allowableStoreIds = ObjectState::get($this, 'allowableStoreIds');

        abort_unless($storeId !== null && in_array($storeId, $allowableStoreIds, true), 403, t('You are not permitted to perform this action for this store.', category: 'commerce'));
    }
}
