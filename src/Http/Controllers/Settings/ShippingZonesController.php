<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html as NewHtml;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Commerce\Formula\Formulas;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Shipping\Data\ShippingAddressZone;
use CraftCms\Commerce\Shipping\ShippingZones;

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

        $shippingZones = app(ShippingZones::class)->getAllShippingZones($store->id);

        $tableData = [];
        foreach ($shippingZones as $shippingZone) {
            $label = NewHtml::encode(t($shippingZone->name, category: 'site'));
            $tableData[] = [
                'id' => $shippingZone->id,
                'title' => NewHtml::a($label, $shippingZone->getCpEditUrl()),
                'url' => $shippingZone->getCpEditUrl(),
                'description' => NewHtml::encode(t($shippingZone->description, category: 'site')),
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

        HtmlStack::js($js, Position::BodyEnd);

        return $this->storeManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(NewHtml::a(t('New shipping zone', category: 'commerce'), $store->getStoreSettingsUrl('shippingzones/new'), ['class' => 'btn submit add icon']))
            ->contentHtml(NewHtml::tag('div', '', ['id' => 'shipping-vue-admin-table']));
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        if ($id) {
            $shippingZone = app(ShippingZones::class)->getShippingZoneById($id, $store->id);
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
                t('Created at') => I18N::getFormatter()->asDatetime($shippingZone->dateCreated, 'short'),
                t('Updated at') => I18N::getFormatter()->asDatetime($shippingZone->dateUpdated, 'short'),
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

        $shippingZone->id = $request->input('shippingZoneId') ? (int)$request->input('shippingZoneId') : null;
        $shippingZone->storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $shippingZone->name = $request->input('name');
        $shippingZone->description = $request->input('description');
        $shippingZone->setCondition($request->input('condition'));

        if ($shippingZone->validate() && app(ShippingZones::class)->saveShippingZone($shippingZone)) {
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

        if (!app(ShippingZones::class)->deleteShippingZoneById((int)$id)) {
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

        if (!app(Formulas::class)->evaluateCondition($zipCodeFormula, $params)) {
            return $this->asFailure('failed');
        }

        return $this->asSuccess();
    }
}
