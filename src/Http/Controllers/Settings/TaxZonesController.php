<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\helpers\Cp;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Support\Html as NewHtml;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\View\Enums\Position;
use CraftCms\Commerce\Formula\Formulas;
use CraftCms\Commerce\Http\Controllers\Concerns\HasStoreManagementScreen;
use CraftCms\Commerce\Tax\Data\TaxAddressZone;
use CraftCms\Commerce\Tax\TaxZones;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class TaxZonesController
{
    use HasStoreManagementScreen;
    use RespondsWithFlash;

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $taxZones = app(TaxZones::class)->getAllTaxZones($store->id);

        $tableData = [];
        foreach ($taxZones as $taxZone) {
            $label = NewHtml::encode(t($taxZone->name, category: 'site'));
            $tableData[] = [
                'id' => $taxZone->id,
                'title' => NewHtml::a($label, $taxZone->getCpEditUrl()),
                'url' => $taxZone->getCpEditUrl(),
                'description' => NewHtml::encode(t($taxZone->description, category: 'site')),
                'default' => $taxZone->default,
            ];
        }

        $tableData = Json::encode($tableData);

        $js = <<<JS
var columns = [
    { name: 'title', title: Craft.t('commerce', 'Name') },
    { name: 'description', title: Craft.t('commerce', 'Description') },
    {
        name: 'default',
        title: Craft.t('commerce', 'Default Zone'),
        callback: function(value) {
            if (value) {
                return '<div data-icon="check"></div>';
            }
        }
    },
];

new Craft.VueAdminTable({
    columns: columns,
    container: '#tax-vue-admin-table',
    deleteAction: 'commerce/tax-zones/delete',
    tableData: {$tableData},
    });
JS;
        HtmlStack::js($js, Position::BodyEnd);

        return $this->storeManagementCpScreen($storeHandle)
            ->additionalButtonsHtml(NewHtml::a(t('New tax zone', category: 'commerce'), $store->getStoreSettingsUrl('taxzones/new'), ['class' => 'btn submit add icon']))
            ->contentHtml(NewHtml::tag('div', '', ['id' => 'tax-vue-admin-table']));
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);
        $storeHandle = $store->handle;

        if ($id) {
            $taxZone = app(TaxZones::class)->getTaxZoneById($id, $store->id);
            abort_if($taxZone === null, 404);
        } else {
            $taxZone = \Craft::createObject([
                'class' => TaxAddressZone::class,
                'storeId' => $store->id,
            ]);
        }

        $title = $taxZone->id ? $taxZone->name : t('Create a tax zone', category: 'commerce');

        $condition = $taxZone->getCondition();
        $condition->mainTag = 'div';
        $condition->name = 'condition';
        $condition->id = 'condition';

        $metaSidebar = '';
        if ($taxZone->id) {
            $metaSidebar = Cp::metadataHtml([
                t('Created at') => I18N::getFormatter()->asDatetime($taxZone->dateCreated, 'short'),
                t('Updated at') => I18N::getFormatter()->asDatetime($taxZone->dateUpdated, 'short'),
            ]);
        }

        return $this->storeManagementCpScreen($storeHandle, false)
            ->title($title)
            ->addCrumb(t('Tax Zones', category: 'commerce'), $store->getStoreSettingsUrl('taxzones'))
            ->selectedSubnavItem('store-management')
            ->action('commerce/tax-zones/save')
            ->redirectUrl($store->getStoreSettingsUrl('taxzones'))
            ->metaSidebarHtml($metaSidebar)
            ->contentTemplate('commerce/store-management/tax/taxzones/_edit', [
                'taxZone' => $taxZone,
                'store' => $store,
                'condition' => $condition,
            ]);
    }

    public function save(Request $request): Response
    {
        $taxZone = new TaxAddressZone();

        $taxZone->id = $request->input('taxZoneId') ? (int)$request->input('taxZoneId') : null;
        $taxZone->storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $taxZone->name = $request->input('name');
        $taxZone->description = $request->input('description');
        $taxZone->default = (bool)$request->input('default');
        $taxZone->setCondition($request->input('condition'));

        if ($taxZone->validate() && app(TaxZones::class)->saveTaxZone($taxZone)) {
            return $this->asModelSuccess(
                $taxZone,
                t('Tax zone saved.', category: 'commerce'),
                'taxZone',
                data: [
                    'id' => $taxZone->id,
                    'name' => $taxZone->name,
                ]
            );
        }

        return $this->asModelFailure(
            $taxZone,
            t('Couldn\'t save tax zone.', category: 'commerce'),
            'taxZone'
        );
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing tax zone id');

        app(TaxZones::class)->deleteTaxZoneById((int)$id);
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
