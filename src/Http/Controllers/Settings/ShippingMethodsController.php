<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\View;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Html as NewHtml;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use Illuminate\Http\Request;
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

        \Craft::$app->getView()->registerTranslations('commerce', [
            'Disabled',
            'Enabled',
            'Handle',
            'Name',
            'Set status',
            'Type',
        ]);

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

        \Craft::$app->getView()->registerJs($js, View::POS_END);

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

        \Craft::$app->getView()->registerTranslations('commerce', [
            'Couldn\'t reorder rules.',
            'Description',
            'No shipping rules exist yet.',
            'Rules reordered.',
            'Shipping Rule',
        ]);

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
                t('Created at') => \Craft::$app->getFormatter()->asDatetime($shippingMethod->dateCreated, 'short'),
                t('Updated at') => \Craft::$app->getFormatter()->asDatetime($shippingMethod->dateUpdated, 'short'),
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

        $transaction = \Craft::$app->getDb()->beginTransaction();
        $shippingMethods = ShippingMethodRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var ShippingMethodRecord $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethod->enabled = ($status == 'enabled');
            $shippingMethod->save();
        }
        $transaction->commit();

        return $this->asSuccess(t('Shipping methods updated.', category: 'commerce'));
    }
}
