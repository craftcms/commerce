<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html as NewHtml;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Shipping\Records\ShippingMethod as ShippingMethodRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\t;

readonly class ShippingMethodsController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $shippingMethods = Plugin::getInstance()->getShippingMethods()->getAllShippingMethods($store->id);

        $tableData = [];
        foreach ($shippingMethods as $shippingMethod) {
            $label = Html::encode(t($shippingMethod->name, category: 'site'));
            $tableData[] = [
                'id' => $shippingMethod->id,
                'title' => $label,
                'chip' => Cp::chipHtml($shippingMethod, [
                    'showStatus' => true,
                    'showThumb' => true,
                    'labelHtml' => Html::a($label, $shippingMethod->getCpEditUrl(), [
                        'class' => ['chip-label', 'cell-bold'],
                    ]),
                ]),
                'url' => $shippingMethod->getCpEditUrl(),
                'handle' => $shippingMethod->handle,
                'type' => $shippingMethod->getType(),
                'status' => $shippingMethod->enabled,
            ];
        }

        $tableData = Json::encode($tableData);

        $js = <<<JS
    var columns = [
        { name: 'chip', title: Craft.t('commerce', 'Name') },
        { name: '__slot:handle', title: Craft.t('commerce', 'Handle') },
        { name: 'type', title: Craft.t('commerce', 'Type') },
    ];

    var actions = [
        {
            label: Craft.t('commerce', 'Set status'),
            actions: [
                {
                    label: Craft.t('commerce', 'Enabled'),
                    action: 'commerce/shipping-methods/update-status',
                    param: 'status',
                    value: 'enabled',
                    status: 'enabled'
                },
                {
                    label: Craft.t('commerce', 'Disabled'),
                    action: 'commerce/shipping-methods/update-status',
                    param: 'status',
                    value: 'disabled',
                    status: 'disabled'
                }
            ]
        }
    ];

    new Craft.VueAdminTable({
        actions: actions,
        checkboxes: true,
        columns: columns,
        container: '#shipping-vue-admin-table',
        deleteAction: 'commerce/shipping-methods/delete',
        padded: true,
        tableData: {$tableData}
    });
JS;

        HtmlStack::js($js, Position::BodyEnd);

        return $this->storeManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(Html::a(t('New shipping method', category: 'commerce'), $store->getStoreSettingsUrl('shippingmethods/new'), ['class' => 'btn submit add icon']))
            ->contentHtml(NewHtml::tag('div', '', ['id' => 'shipping-vue-admin-table']));
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        if ($id) {
            $shippingMethod = Plugin::getInstance()->getShippingMethods()->getShippingMethodById($id, $store->id);
            abort_if($shippingMethod === null, 404);
        } else {
            $shippingMethod = \Craft::createObject([
                'class' => ShippingMethod::class,
                'attributes' => ['storeId' => $store->id],
            ]);
        }

        $title = $shippingMethod->id ? $shippingMethod->name : t('Create a new shipping method', category: 'commerce');

        $shippingRules = $shippingMethod->id !== null
            ? Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($shippingMethod->id)
            : [];

        $metaDataHtml = Html::beginTag('div', ['class' => 'meta']) .
            Cp::lightswitchFieldHtml([
                'label' => t('Enable this shipping method on the front end', category: 'commerce'),
                'id' => 'enabled',
                'name' => 'enabled',
                'on' => $shippingMethod->enabled,
                'errors' => $shippingMethod->getErrors('enabled'),
            ]) .
            Html::endTag('div');

        if ($shippingMethod->id) {
            $metaDataHtml .= Cp::metadataHtml([
                t('Created at') => I18N::getFormatter()->asDatetime($shippingMethod->dateCreated, 'short'),
                t('Updated at') => I18N::getFormatter()->asDatetime($shippingMethod->dateUpdated, 'short'),
            ]);
        }

        return $this->storeManagementCpScreen($storeHandle, false)
            ->title($title)
            ->action('commerce/shipping-methods/save')
            ->redirectUrl($store->getStoreSettingsUrl('shippingmethods/{id}#rules'))
            ->addCrumb(t('Shipping Methods', category: 'commerce'), $store->getStoreSettingsUrl('shippingmethods'))
            ->metaSidebarHtml($metaDataHtml)
            ->submitButtonLabel($shippingMethod->id ? t('Save and set rules', category: 'commerce') : t('Save'))
            ->contentTemplate('commerce/store-management/shipping/shippingmethods/_edit', [
                'shippingMethod' => $shippingMethod,
                'shippingRules' => $shippingRules,
                'store' => $store,
                'storeHandle' => $storeHandle,
            ]);
    }

    public function save(Request $request): Response
    {
        $shippingMethod = new ShippingMethod();

        $shippingMethod->id = $request->input('shippingMethodId');
        $shippingMethod->name = $request->input('name');
        $shippingMethod->handle = $request->input('handle');
        $shippingMethod->icon = $request->input('icon');
        $shippingMethod->color = $request->input('color');
        $shippingMethod->storeId = $request->input('storeId');
        $shippingMethod->setOrderCondition($request->input('orderCondition'));
        $shippingMethod->setCustomerCondition($request->input('customerCondition'));
        $shippingMethod->enabled = (bool)$request->input('enabled');

        if (!Plugin::getInstance()->getShippingMethods()->saveShippingMethod($shippingMethod)) {
            return $this->asModelFailure($shippingMethod, t('Couldn\'t save shipping method.', category: 'commerce'), 'shippingMethod');
        }

        return $this->asModelSuccess($shippingMethod, t('Shipping method saved.', category: 'commerce'), 'shippingMethod');
    }

    public function delete(Request $request): Response
    {
        $id = $request->input('id');
        $ids = $request->input('ids');

        abort_if((!$id && empty($ids)) || ($id && !empty($ids)), 400, 'id or ids must be specified.');

        if ($id) {
            abort_unless($request->expectsJson(), 400);
            $ids = [$id];
        }

        $failedIds = [];
        foreach ($ids as $deleteId) {
            if (!Plugin::getInstance()->getShippingMethods()->deleteShippingMethodById($deleteId)) {
                $failedIds[] = $deleteId;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(t('Could not delete {count, number} shipping {count, plural, one{method} other{methods}} and rules.', [
                'count' => count($failedIds),
            ], category: 'commerce'));
        }

        return $this->asSuccess(t('Shipping methods and rules deleted.', category: 'commerce'));
    }

    public function updateStatus(Request $request): Response
    {
        $ids = $request->input('ids');
        $status = $request->input('status');

        abort_if(empty($ids), 400, 'Missing ids');

        DB::transaction(function() use ($ids, $status) {
            $shippingMethods = ShippingMethodRecord::whereIn('id', $ids)->get();

            foreach ($shippingMethods as $shippingMethod) {
                $shippingMethod->enabled = ($status == 'enabled');
                $shippingMethod->save();
            }
        });

        return $this->asSuccess(t('Shipping methods updated.', category: 'commerce'));
    }
}
