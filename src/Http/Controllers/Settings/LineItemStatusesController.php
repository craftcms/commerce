<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

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
use CraftCms\Commerce\Order\Data\LineItemStatus;
use CraftCms\Commerce\Order\LineItemStatuses;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

class LineItemStatusesController extends BaseSettingsController
{
    private const array STATUS_COLORS = ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'];

    protected function getSectionCrumb(): array
    {
        return ['label' => t('Line Item Statuses', category: 'commerce'), 'href' => cp_url('commerce/settings/lineitemstatuses')];
    }

    public function index(): CpScreenResponse
    {
        $stores = app(Stores::class)->getAllStores();
        $isMultiStore = $stores->count() > 1;

        // Every store's table can plant its own create action in the page's shared actions
        // slot, so with more than one store, only the first store's table gets one — a single
        // combined "New line item status" menu covering every store, rather than one button apiece.
        $createMenuItems = $this->readOnly ? [] : $stores->map(fn(Store $store) => [
            'label' => $store->name,
            'url' => cp_url("commerce/settings/lineitemstatuses/{$store->handle}/new"),
        ])->all();
        $createMenuAssigned = false;

        $nodes = [];
        $stores->each(function(Store $store) use (&$nodes, $isMultiStore, $createMenuItems, &$createMenuAssigned) {
            if ($isMultiStore) {
                $nodes[] = Heading::make("{$store->handle}-heading", $store->name);
            }

            $rows = app(LineItemStatuses::class)->getAllLineItemStatuses($store->id)
                ->map(fn(LineItemStatus $lineItemStatus) => [
                    'id' => $lineItemStatus->id,
                    // getLabelHtml() already encodes the name it interpolates — safe to wrap directly.
                    'name' => ['html' => Html::a($lineItemStatus->getLabelHtml(), $lineItemStatus->getCpEditUrl(), ['class' => 'cell-bold'])],
                    'handle' => ['html' => FormFields::copytextHtml(['value' => $lineItemStatus->handle, 'monospace' => true])],
                    'default' => $lineItemStatus->default ? ['icon' => 'check', 'label' => t('Yes')] : '',
                ])
                ->all();

            $nodes[] = Table::make("{$store->handle}-line-item-statuses")
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'handle', 'label' => t('Handle')],
                    ['key' => 'default', 'label' => t('Default Status?', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No line item statuses exist yet.', category: 'commerce'))
                ->when(!$createMenuAssigned && $createMenuItems, function(Table $table) use ($createMenuItems, &$createMenuAssigned) {
                    $table->createActionMenu(t('New line item status', category: 'commerce'), $createMenuItems)
                        ->createActionInPageHeader();
                    $createMenuAssigned = true;
                })
                ->when(!$this->readOnly, fn(Table $table) => $table
                    ->reorderable(action([self::class, 'reorder']))
                    ->deletable(
                        action([self::class, 'archive']),
                        t('Are you sure you want to delete this line item status? This will set all line items with this status to no status.', category: 'commerce'),
                    ));
        });

        $title = t('Line Item Statuses', category: 'commerce');

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs())
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
                'contentMaxWidth' => false,
            ]);
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
            $lineItemStatus = new LineItemStatus(['storeId' => $store->id]);
        }

        if ($lineItemStatus->id) {
            $title = $lineItemStatus->name;
            $statusColor = $lineItemStatus->color;
        } else {
            $title = t('Create a new line item status', category: 'commerce');

            $availableColors = self::STATUS_COLORS;
            app(LineItemStatuses::class)->getAllLineItemStatuses($store->id)->each(function(LineItemStatus $status) use (&$availableColors) {
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

        $handle = Handle::make('handle');
        if (!$lineItemStatus->id) {
            $handle->source('name');
        }

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($lineItemStatus->id) {
            $formNodes[] = HiddenField::make('sortOrder');
            $formNodes[] = HiddenField::make('id');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('What this status will be called in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Handle', category: 'commerce'), $handle)
            ->instructions(t('How you’ll refer to this status in the templates.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Color', category: 'commerce'), Choice::make('color')->presentation(ChoicePresentation::Radios)->options($colorOptions))
            ->instructions(t('Choose a color to represent the order’s status', category: 'commerce'));
        $formNodes[] = Field::make(t('New line items get this status by default when the order is completed', category: 'commerce'), Lightswitch::make('default'));

        $form = $this->formResolver->resolve(Form::make($formNodes), new FormContext(
            values: [
                'storeId' => $store->id,
                'sortOrder' => $lineItemStatus->sortOrder,
                'id' => $lineItemStatus->id,
                'name' => $lineItemStatus->name,
                'handle' => $lineItemStatus->handle,
                'color' => $statusColor,
                'default' => $lineItemStatus->default,
            ],
            mode: $this->generalConfig->allowAdminChanges ? ControlMode::Editable : ControlMode::ReadOnly,
        ));

        return $this->cpScreenResponse(subnav: false)
            ->title($title)
            ->crumbs($lineItemStatus->id ? $this->crumbs(['label' => $title]) : $this->crumbs())
            ->action('commerce/line-item-statuses/save')
            ->redirectUrl('commerce/settings/lineitemstatuses')
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
        $lineItemStatus = $id ? app(LineItemStatuses::class)->getLineItemStatusById($id, $storeId) : null;
        $lineItemStatus ??= new LineItemStatus();

        $lineItemStatus->storeId = $storeId;
        $lineItemStatus->name = $request->input('name');
        $lineItemStatus->handle = $request->input('handle');
        $lineItemStatus->color = $request->input('color');
        $lineItemStatus->default = (bool)$request->input('default');

        if (!app(LineItemStatuses::class)->saveLineItemStatus($lineItemStatus)) {
            return $this->asModelFailure($lineItemStatus, t('Couldn’t save line item status.', category: 'commerce'), 'lineItemStatus');
        }

        return $this->asModelSuccess($lineItemStatus, t('Line item status saved.', category: 'commerce'), 'lineItemStatus');
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);
        abort_unless($request->input('ids'), 400, 'Missing ids');

        $ids = Json::decode($request->input('ids'));

        if (!app(LineItemStatuses::class)->reorderLineItemStatuses($ids)) {
            // The double space before "Line" matches lang/en/commerce.php's existing key
            // exactly (a pre-existing typo carried over from the legacy Yii2 controller) —
            // don't "fix" the spacing here without also updating the lang file.
            return $this->asFailure(t('Couldn’t reorder  Line Item Statuses.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function archive(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $lineItemStatusId = $request->input('id');
        abort_if(!$lineItemStatusId, 400, 'Missing line item status id');

        $storeId = DB::table(DbTable::LINEITEMSTATUSES)->where('id', $lineItemStatusId)->value('storeId');

        if (!$storeId || !app(LineItemStatuses::class)->archiveLineItemStatusById((int)$lineItemStatusId, $storeId)) {
            return $this->asFailure(t('Couldn’t archive Line Item Status.', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
