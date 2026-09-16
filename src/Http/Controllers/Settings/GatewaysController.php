<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Cp\SelectOptions;
use CraftCms\Cms\Form\Controls\Choice;
use CraftCms\Cms\Form\Controls\Combobox;
use CraftCms\Cms\Form\Controls\ConditionBuilder;
use CraftCms\Cms\Form\Controls\Handle;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Group;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Address\Conditions\GatewayAddressCondition;
use CraftCms\Commerce\Database\Table as DbTable;
use CraftCms\Commerce\Order\Conditions\GatewayOrderCondition;
use CraftCms\Commerce\Payment\Gateway\Contracts\GatewayInterface;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Payment\Gateway\Types\MissingGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

class GatewaysController extends BaseSettingsController
{
    protected function getSectionCrumb(): array
    {
        return ['label' => t('Gateways', category: 'commerce'), 'href' => cp_url('commerce/settings/gateways')];
    }

    public function index(): CpScreenResponse
    {
        $gatewayService = app(Gateways::class);
        $gateways = $gatewayService->getAllGateways();
        $archivedGateways = $gatewayService->getAllArchivedGateways();

        $nameCell = fn(GatewayInterface $gateway) => ['html' => Html::a(
            Html::encode(t($gateway->name, category: 'site')),
            $gateway->getCpEditUrl(),
            ['class' => 'cell-bold'],
        )];
        $typeCell = fn(GatewayInterface $gateway) => ['html' => $gateway instanceof MissingGateway
            ? Html::tag('span', Html::encode($gateway->expectedType), ['class' => 'error'])
            : Html::encode($gateway::displayName()), ];

        $rows = $gateways->map(fn(GatewayInterface $gateway) => [
            'id' => $gateway->id,
            'name' => $nameCell($gateway),
            'handle' => ['html' => FormFields::copytextHtml(['value' => $gateway->handle, 'monospace' => true])],
            'type' => $typeCell($gateway),
            'customerEnabled' => $gateway->getIsFrontendEnabled() ? ['icon' => 'check', 'label' => t('Yes')] : '',
        ])->values()->all();

        $nodes = [
            Table::make('gateways')
                ->columns([
                    ['key' => 'id', 'label' => t('ID')],
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'handle', 'label' => t('Handle')],
                    ['key' => 'type', 'label' => t('Type', category: 'commerce')],
                    ['key' => 'customerEnabled', 'label' => t('Customer Enabled?', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No gateways exist yet.', category: 'commerce'))
                ->when(!$this->readOnly, fn(Table $table) => $table
                    ->createAction(t('New gateway', category: 'commerce'), cp_url('commerce/settings/gateways/new'))
                    ->reorderable(action([self::class, 'reorder']))
                    ->deletable(action([self::class, 'archive']))),
        ];

        if ($archivedGateways !== []) {
            $gatewayIdsWithTransactions = DB::table(DbTable::TRANSACTIONS)
                ->select('gatewayId')
                ->groupBy('gatewayId')
                ->pluck('gatewayId')
                ->all();

            $archivedRows = array_values(array_map(fn(GatewayInterface $gateway) => [
                'id' => $gateway->id,
                'name' => $nameCell($gateway),
                'handle' => ['html' => FormFields::copytextHtml(['value' => $gateway->handle, 'monospace' => true])],
                'type' => $typeCell($gateway),
                'hasTransactions' => in_array($gateway->id, $gatewayIdsWithTransactions, true) ? ['icon' => 'check', 'label' => t('Yes')] : '',
            ], $archivedGateways));

            $nodes[] = Group::make('archived-gateways', [
                Table::make('archived-gateways-table')
                    ->columns([
                        ['key' => 'id', 'label' => t('ID')],
                        ['key' => 'name', 'label' => t('Name')],
                        ['key' => 'handle', 'label' => t('Handle')],
                        ['key' => 'type', 'label' => t('Type', category: 'commerce')],
                        ['key' => 'hasTransactions', 'label' => t('Has Transactions?', category: 'commerce')],
                    ])
                    ->rows($archivedRows),
            ])->label(t('Show archived gateways', category: 'commerce'))->collapsible();
        }

        $title = t('Gateways', category: 'commerce');

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs())
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
            ]);
    }

    public function edit(?int $id = null): CpScreenResponse
    {
        $gatewayService = app(Gateways::class);

        if ($id) {
            $gateway = $gatewayService->getGatewayById($id);
            abort_if($gateway === null, 404, 'Gateway not found');
        } else {
            $gateway = $gatewayService->createGateway(['type' => Dummy::class]);
        }

        $title = $gateway->id ? $gateway->name : t('Create a new gateway', category: 'commerce');

        // The Combobox only recognizes its own '1'/'0' option values (or an env var
        // reference) — a literal bool default (unparsed) needs stringifying first, or it
        // shows as free-typed text instead of resolving to the matching Yes/No option.
        $isFrontendEnabled = $gateway->getIsFrontendEnabled(false);
        if (is_bool($isFrontendEnabled)) {
            $isFrontendEnabled = $isFrontendEnabled ? '1' : '0';
        }

        $values = [
            'id' => $gateway->id,
            'name' => $gateway->name,
            'handle' => $gateway->handle,
            'type' => $gateway::class,
            'paymentType' => $gateway->paymentType,
            'isFrontendEnabled' => $isFrontendEnabled,
            'orderCondition' => $gateway->getOrderCondition()->getConfig(),
            'billingAddressCondition' => $gateway->getBillingAddressCondition()->getConfig(),
            'shippingAddressCondition' => $gateway->getShippingAddressCondition()->getConfig(),
            ...$gateway->getSettings(),
        ];

        $form = $this->formResolver->resolve($this->buildForm($values), new FormContext(
            values: $values,
            mode: $this->generalConfig->allowAdminChanges ? ControlMode::Editable : ControlMode::ReadOnly,
            refreshable: !$this->readOnly,
        ));

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($gateway->id ? $this->crumbs(['label' => $title]) : $this->crumbs())
            ->action('commerce/gateways/save')
            ->redirectUrl('commerce/settings/gateways')
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'refreshUrl' => $this->readOnly ? null : action([self::class, 'renderForm']),
            ]);
    }

    /**
     * Re-resolves the {@see edit()} Form tree for the values currently in progress on the
     * client, so switching the Gateway type can swap in that type's own settings fields
     * without a full page reload.
     */
    public function renderForm(Request $request): JsonResponse
    {
        // Validate for shape only — Request::validate() returns just the ruled subset, which
        // would silently strip every field but the ones named here back out of `values`.
        // Reading the raw input keeps the full posted values intact for buildForm()'s branching.
        $request->validate([
            'values' => ['required', 'array'],
            'scope' => ['present', 'array', 'size:0'],
        ]);

        $values = $request->input('values');

        $form = $this->formResolver->resolve($this->buildForm($values), new FormContext(
            values: $values,
            mode: ControlMode::Editable,
            refreshable: true,
        ));

        return new JsonResponse(['form' => $form]);
    }

    /**
     * @param  array<string, mixed>  $values  Passed by reference so a stale `paymentType`
     *   left over from a since-changed Gateway type — no longer one of its options — can be
     *   corrected here and reflected back in the FormContext both callers resolve against.
     */
    private function buildForm(array &$values): Form
    {
        $gatewayService = app(Gateways::class);
        $type = $values['type'] ?? Dummy::class;

        /** @var string[] $allGatewayTypes */
        $allGatewayTypes = $gatewayService->getAllGatewayTypes();

        // Make sure the selected gateway class is in there, even if it's no longer selectable
        // (e.g. a plugin gateway type that's since been superseded).
        if (!in_array($type, $allGatewayTypes, true)) {
            $allGatewayTypes[] = $type;
        }

        $gatewayOptions = [];
        foreach ($allGatewayTypes as $class) {
            if ($class === $type || $class::isSelectable()) {
                $gatewayOptions[] = ['value' => $class, 'label' => $class::displayName()];
            }
        }

        // Rebuild a live instance of the currently selected type, seeded with whatever's been
        // posted so far — mirrors save()'s own config-building, so switching types (or a
        // reactive round trip mid-edit) always reflects what's actually in the form right now.
        $bareGateway = $gatewayService->createGateway($type);
        // getSettings() (rather than settingsAttributes()) since a gateway type may back its
        // settings with private properties exposed only through a custom getSettings()
        // override — Manual's onlyAllowForZeroPriceOrders being a case in point.
        $settingsKeys = array_keys($bareGateway->getSettings());

        $gateway = $gatewayService->createGateway([
            'type' => $type,
            'id' => $values['id'] ?? null,
            'name' => $values['name'] ?? null,
            'handle' => $values['handle'] ?? null,
            'paymentType' => $values['paymentType'] ?? null,
            'isFrontendEnabled' => $values['isFrontendEnabled'] ?? null,
            'settings' => Arr::only($values, $settingsKeys),
        ]);

        $handle = Handle::make('handle');
        if (empty($values['id'])) {
            $handle->source('name');
        }

        $formNodes = [];

        if (!empty($values['id'])) {
            $formNodes[] = HiddenField::make('id');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('What this gateway will be called in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Handle', category: 'commerce'), $handle)
            ->instructions(t('How you’ll refer to this gateway in the templates.', category: 'commerce'))
            ->required();

        if ($gateway->id && $gateway->supportsWebhooks()) {
            $formNodes[] = Field::make(t('Webhook URL', category: 'commerce'), Text::make('webhookUrl')
                ->mode(ControlMode::ReadOnly)
                ->value(Url::siteUrl('commerce/webhooks/process-webhook/gateway/' . $gateway->id)))
                ->instructions(t('The webhook URL for this gateway.', category: 'commerce'));
        }

        $formNodes[] = Field::make(t('Gateway', category: 'commerce'), Choice::make('type')->options($gatewayOptions)->reactive())
            ->warning(!empty($values['id']) ? t('Changing this value may affect your ability to refund existing transactions.', category: 'commerce') : null)
            ->required();

        $paymentTypeOptions = collect($gateway->getPaymentTypeOptions())->map(fn($label, $value) => ['label' => $label, 'value' => $value])->values();

        // Switching Gateway type can leave a paymentType selected that the new type doesn't
        // offer (e.g. Manual only offers "authorize"); fall back to that type's first option
        // rather than silently submitting a value it never presented as a choice.
        if (!$paymentTypeOptions->contains('value', $values['paymentType'] ?? null)) {
            $values['paymentType'] = $paymentTypeOptions->first()['value'] ?? null;
        }

        $formNodes[] = Field::make(t('Credit Card Payment Type', category: 'commerce'), Choice::make('paymentType')->options($paymentTypeOptions->all()))
            ->instructions(t('If set to Authorize Only, you will need to manually capture payments before the funds will be transferred to your account. The Gateway needs to support the selected option.', category: 'commerce'))
            ->required();

        $settingsForm = $gateway->settingsForm(new FormContext(
            values: $values,
            mode: ControlMode::Editable,
        ));
        if ($settingsForm !== null) {
            array_push($formNodes, ...$settingsForm->nodes());
        }

        // getBooleanEnvOptions()'s optgroup carries its own `options` as a Collection (fine for
        // its one existing caller, which reshapes it before use) — a Form control's options
        // must be plain-array JSON-safe all the way down, so that gets flattened here rather
        // than changing the shared method's return shape for every other caller.
        $envOptions = array_map(fn(array $group) => [...$group, 'options' => $group['options']->all()], SelectOptions::getBooleanEnvOptions());

        $formNodes[] = Field::make(t('Enabled for customers to select during checkout?', category: 'commerce'), Combobox::make('isFrontendEnabled')->options([
            ['label' => t('Yes'), 'value' => '1'],
            ['label' => t('No'), 'value' => '0'],
            ...$envOptions,
        ]));

        $formNodes[] = Field::make(t('Match Order', category: 'commerce'), ConditionBuilder::make('orderCondition')
            ->conditionClass(GatewayOrderCondition::class)
            ->forProjectConfig()
            ->value($values['orderCondition'] ?? []))
            ->instructions(t('Create rules that allow this gateway to match the order.', category: 'commerce'));
        $formNodes[] = Field::make(t('Match Billing Address', category: 'commerce'), ConditionBuilder::make('billingAddressCondition')
            ->conditionClass(GatewayAddressCondition::class)
            ->forProjectConfig()
            ->value($values['billingAddressCondition'] ?? []))
            ->instructions(t('Create rules that allow this gateway to match the billing address.', category: 'commerce'));
        $formNodes[] = Field::make(t('Match Shipping Address', category: 'commerce'), ConditionBuilder::make('shippingAddressCondition')
            ->conditionClass(GatewayAddressCondition::class)
            ->forProjectConfig()
            ->value($values['shippingAddressCondition'] ?? []))
            ->instructions(t('Create rules that allow this gateway to match the shipping address.', category: 'commerce'));

        return Form::make($formNodes);
    }

    public function save(Request $request): Response
    {
        $gatewayService = app(Gateways::class);

        $type = $request->input('type');
        abort_if($type === null, 400, 'Missing gateway type');
        $gatewayId = $request->input('id');

        $bareGateway = $gatewayService->createGateway($type);

        // A Gateway type switch can leave a paymentType posted that the new type doesn't
        // offer (e.g. Manual only offers "authorize") — the client corrects its display once
        // the reactive round trip returns the new options, but a submit racing ahead of that
        // (or bypassing it) shouldn't be able to persist a value the type never presented as
        // a choice, so this falls back to that type's first option instead.
        $paymentType = $request->input('paymentType');
        if (!array_key_exists($paymentType, $bareGateway->getPaymentTypeOptions())) {
            $paymentType = array_key_first($bareGateway->getPaymentTypeOptions());
        }

        $config = [
            'id' => $gatewayId,
            'type' => $type,
            'name' => $request->input('name'),
            'handle' => $request->input('handle'),
            'paymentType' => $paymentType,
            'isFrontendEnabled' => $request->input('isFrontendEnabled'),
            'settings' => Arr::only($request->input(), array_keys($bareGateway->getSettings())),
        ];

        // Handle order condition if it's in the request
        $orderCondition = $request->input('orderCondition');
        if ($orderCondition !== null) {
            $config['orderCondition'] = $orderCondition;
        }

        // Handle billing address condition if it's in the request
        $billingAddressCondition = $request->input('billingAddressCondition');
        if ($billingAddressCondition !== null) {
            $config['billingAddressCondition'] = $billingAddressCondition;
        }

        // Handle shipping address condition if it's in the request
        $shippingAddressCondition = $request->input('shippingAddressCondition');
        if ($shippingAddressCondition !== null) {
            $config['shippingAddressCondition'] = $shippingAddressCondition;
        }

        // For new gateway avoid NULL value.
        if (!$request->input('id')) {
            $config['isArchived'] = false;
        }

        // If this is an existing gateway, populate with properties unchangeable by this action.
        if ($gatewayId) {
            $savedGateway = $gatewayService->getGatewayById((int)$gatewayId);
            $config['uid'] = $savedGateway->uid;
            $config['sortOrder'] = $savedGateway->sortOrder;
        }

        $gateway = $gatewayService->createGateway($config);

        if (!$gatewayService->saveGateway($gateway)) {
            return $this->asModelFailure($gateway, t('Couldn\'t save gateway.', category: 'commerce'), 'gateway');
        }

        return $this->asModelSuccess($gateway, t('Gateway saved.', category: 'commerce'), 'gateway');
    }

    public function archive(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing gateway id');

        if (!app(Gateways::class)->archiveGatewayById((int)$id)) {
            return $this->asFailure(t('Could not archive gateway.', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function reorder(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $ids = json_decode($request->input('ids'), true);

        if (!app(Gateways::class)->reorderGateways($ids)) {
            return $this->asFailure(t('Couldn\'t reorder gateways.', category: 'commerce'));
        }

        return $this->asSuccess();
    }
}
