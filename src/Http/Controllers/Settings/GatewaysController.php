<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\gateways\Dummy;
use craft\commerce\gateways\MissingGateway;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class GatewaysController
{
    use RespondsWithFlash;

    private bool $readOnly;

    public function __construct(GeneralConfig $generalConfig)
    {
        $this->readOnly = !$generalConfig->allowAdminChanges;
    }

    public function index(): string
    {
        $gateways = app(Gateways::class)->getAllGateways();
        $archivedGateways = app(Gateways::class)->getAllArchivedGateways();

        if (!empty($archivedGateways)) {
            $gatewayIdsWithTransactions = DB::table(Table::TRANSACTIONS)
                ->select('gatewayId')
                ->groupBy('gatewayId')
                ->pluck('gatewayId')
                ->all();

            foreach ($archivedGateways as &$gateway) {
                $missing = $gateway instanceof MissingGateway;
                $gateway = [
                    'id' => $gateway->id,
                    'title' => Html::encode(t($gateway->name, category: 'site')),
                    'handle' => Html::encode($gateway->handle),
                    'type' => [
                        'missing' => $missing,
                        'name' => Html::encode($missing ? $gateway->expectedType : $gateway->displayName()),
                    ],
                    'hasTransactions' => in_array($gateway->id, $gatewayIdsWithTransactions),
                ];
            }
        }

        return pageTemplate('commerce/settings/gateways/index', [
            'gateways' => $gateways,
            'archivedGateways' => array_values($archivedGateways),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function edit(?int $id = null): string
    {
        $gatewayService = app(Gateways::class);

        if ($id) {
            $gateway = $gatewayService->getGatewayById($id);
            abort_if($gateway === null, 404, 'Gateway not found');
        } else {
            $gateway = $gatewayService->createGateway(['type' => Dummy::class]);
        }

        /** @var string[] $allGatewayTypes */
        $allGatewayTypes = $gatewayService->getAllGatewayTypes();

        // Make sure the selected gateway class is in there
        if (!in_array($gateway::class, $allGatewayTypes, true)) {
            $allGatewayTypes[] = $gateway::class;
        }

        $gatewayInstances = [];
        $gatewayOptions = [];

        foreach ($allGatewayTypes as $class) {
            if ($class === $gateway::class || $class::isSelectable()) {
                $gatewayInstances[$class] = $gatewayService->createGateway($class);

                $gatewayOptions[] = [
                    'value' => $class,
                    'label' => $class::displayName(),
                ];
            }
        }

        return pageTemplate('commerce/settings/gateways/_edit', [
            'id' => $id,
            'gateway' => $gateway,
            'gatewayTypes' => $allGatewayTypes,
            'gatewayInstances' => $gatewayInstances,
            'gatewayOptions' => $gatewayOptions,
            'title' => $gateway->id ? $gateway->name : t('Create a new gateway', category: 'commerce'),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function save(Request $request): Response
    {
        $gatewayService = app(Gateways::class);

        $type = $request->input('type');
        abort_if($type === null, 400, 'Missing gateway type');
        $gatewayId = $request->input('id');

        $config = [
            'id' => $gatewayId,
            'type' => $type,
            'name' => $request->input('name'),
            'handle' => $request->input('handle'),
            'paymentType' => $request->input('paymentTypes.' . $type . '.paymentType'),
            'isFrontendEnabled' => $request->input('isFrontendEnabled'),
            'settings' => $request->input('types.' . $type),
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
            $savedGateway = $gatewayService->getGatewayById($gatewayId);
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
