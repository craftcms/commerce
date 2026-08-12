<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\models\ShippingAddressZone;
use craft\commerce\Plugin;
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

readonly class ShippingZonesController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $shippingZones = Plugin::getInstance()->getShippingZones()->getAllShippingZones($store->id);

        $tableData = [];
        foreach ($shippingZones as $shippingZone) {
            $label = Html::encode(t($shippingZone->name, category: 'site'));
            $tableData[] = [
                'id' => $shippingZone->id,
                'title' => Html::a($label, $shippingZone->getCpEditUrl()),
                'url' => $shippingZone->getCpEditUrl(),
                'description' => Html::encode(t($shippingZone->description, category: 'site')),
            ];
        }

        $tableData = Json::encode($tableData);

        $js = <<<JS
    var columns = [
        { name: 'title', title: Craft.t('commerce', 'Name') },
        { name: 'description', title: Craft.t('commerce', 'Description') },
    ];

    new Craft.VueAdminTable({
        columns: columns,
        container: '#shipping-vue-admin-table',
        deleteAction: 'commerce/shipping-zones/delete',
        tableData: {$tableData},
    });
JS;

        \Craft::$app->getView()->registerJs($js, View::POS_END);
        \Craft::$app->getView()->registerTranslations('commerce', ['Name', 'Description']);

        return $this->storeManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(Html::a(t('New shipping zone', category: 'commerce'), $store->getStoreSettingsUrl('shippingzones/new'), ['class' => 'btn submit add icon']))
            ->contentHtml(NewHtml::tag('div', '', ['id' => 'shipping-vue-admin-table']));
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        if ($id) {
            $shippingZone = Plugin::getInstance()->getShippingZones()->getShippingZoneById($id, $store->id);
            abort_if($shippingZone === null, 404);
        } else {
            $shippingZone = \Craft::createObject([
                'class' => ShippingAddressZone::class,
                'attributes' => ['storeId' => $store->id],
            ]);
        }

        $title = $shippingZone->id ? $shippingZone->name : t('Create a shipping zone', category: 'commerce');

        $condition = $shippingZone->getCondition();
        $condition->mainTag = 'div';
        $condition->name = 'condition';
        $condition->id = 'condition';

        $metadata = [];
        if ($shippingZone->id) {
            $metadata = [
                t('Created at') => \Craft::$app->getFormatter()->asDatetime($shippingZone->dateCreated, 'short'),
                t('Updated at') => \Craft::$app->getFormatter()->asDatetime($shippingZone->dateUpdated, 'short'),
            ];
        }

        return $this->storeManagementCpScreen($storeHandle, false)
            ->title($title)
            ->addCrumb(t('Shipping Zones', category: 'commerce'), $store->getStoreSettingsUrl('shippingzones'))
            ->action('commerce/shipping-zones/save')
            ->redirectUrl($store->getStoreSettingsUrl('shippingzones'))
            ->metaSidebarHtml(\craft\helpers\Cp::metadataHtml($metadata))
            ->contentTemplate('commerce/store-management/shipping/shippingzones/_edit', [
                'shippingZone' => $shippingZone,
                'condition' => $condition,
                'store' => $store,
            ]);
    }

    public function save(Request $request): Response
    {
        $shippingZone = new ShippingAddressZone();

        $shippingZone->id = $request->input('shippingZoneId');
        $shippingZone->storeId = $request->input('storeId');
        $shippingZone->name = $request->input('name');
        $shippingZone->description = $request->input('description');
        $shippingZone->setCondition($request->input('condition'));

        if ($shippingZone->validate() && Plugin::getInstance()->getShippingZones()->saveShippingZone($shippingZone)) {
            return $this->asModelSuccess(
                $shippingZone,
                t('Shipping zone saved.', category: 'commerce'),
                'shippingZone',
                data: [
                    'id' => $shippingZone->id,
                    'name' => $shippingZone->name,
                ]
            );
        }

        return $this->asModelFailure(
            $shippingZone,
            t('Couldn\'t save shipping zone.', category: 'commerce'),
            'shippingZone'
        );
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing shipping zone id');

        if (!Plugin::getInstance()->getShippingZones()->deleteShippingZoneById((int)$id)) {
            return $this->asFailure(t('Could not delete shipping zone', category: 'commerce'));
        }

        return $this->asSuccess();
    }

    public function testZip(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $zipCodeFormula = (string)$request->input('zipCodeConditionFormula');
        $testZipCode = (string)$request->input('testZipCode');

        $params = ['zipCode' => $testZipCode];

        if (!Plugin::getInstance()->getFormulas()->evaluateCondition($zipCodeFormula, $params)) {
            return $this->asFailure('failed');
        }

        return $this->asSuccess();
    }
}
