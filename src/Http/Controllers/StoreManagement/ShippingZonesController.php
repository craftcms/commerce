<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\StoreManagement;

use CraftCms\Cms\Cp\Html\ContentHtml;
use CraftCms\Cms\Form\Controls\ConditionBuilder;
use CraftCms\Cms\Form\Controls\Text;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\HiddenField;
use CraftCms\Cms\Form\Nodes\Table;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Translation\Formatter;
use CraftCms\Commerce\Address\Conditions\ZoneAddressCondition;
use CraftCms\Commerce\Formula\Formulas;
use CraftCms\Commerce\Shipping\Data\ShippingAddressZone;
use CraftCms\Commerce\Shipping\ShippingZones;
use CraftCms\Commerce\Store\Data\Store;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\t;

readonly class ShippingZonesController extends BaseStoreManagementController
{
    protected function getSectionCrumb(Store $store): array
    {
        return ['label' => t('Shipping Zones', category: 'commerce'), 'href' => $store->getStoreSettingsUrl('shippingzones')];
    }

    public function index(?string $storeHandle = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        $rows = app(ShippingZones::class)->getAllShippingZones($store->id)
            ->map(fn(ShippingAddressZone $shippingZone) => [
                'id' => $shippingZone->id,
                'name' => ['html' => Html::a(Html::encode(t($shippingZone->name, category: 'site')), $shippingZone->getCpEditUrl(), ['class' => 'cell-bold'])],
                'description' => t($shippingZone->description, category: 'site'),
            ])
            ->values()
            ->all();

        $nodes = [
            Table::make('shipping-zones')
                ->columns([
                    ['key' => 'name', 'label' => t('Name')],
                    ['key' => 'description', 'label' => t('Description', category: 'commerce')],
                ])
                ->rows($rows)
                ->emptyMessage(t('No shipping zones exist yet.', category: 'commerce'))
                ->createAction(t('New shipping zone', category: 'commerce'), $store->getStoreSettingsUrl('shippingzones/new'))
                ->deletable(action([self::class, 'delete'])),
        ];

        $title = t('Shipping Zones', category: 'commerce');

        return $this->cpScreenResponse($store)
            ->title($title)
            ->crumbs($this->crumbs($store))
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve(Form::make($nodes), new FormContext()),
                'contentMaxWidth' => false,
            ]);
    }

    public function edit(?string $storeHandle = null, ?int $id = null): CpScreenResponse
    {
        $store = $this->resolveStore($storeHandle);

        if ($id) {
            $shippingZone = app(ShippingZones::class)->getShippingZoneById($id, $store->id);
            abort_if($shippingZone === null, 404);
        } else {
            $shippingZone = new ShippingAddressZone(['storeId' => $store->id]);
        }

        $title = $shippingZone->id ? $shippingZone->name : t('Create a shipping zone', category: 'commerce');

        $formatter = app(Formatter::class);
        $metadataHtml = $shippingZone->id ? app(ContentHtml::class)->metadataHtml([
            t('Created at') => $formatter->asDateTime($shippingZone->dateCreated, 'short'),
            t('Updated at') => $formatter->asDateTime($shippingZone->dateUpdated, 'short'),
        ]) : null;

        $formNodes = [
            HiddenField::make('storeId'),
        ];

        if ($shippingZone->id) {
            $formNodes[] = HiddenField::make('shippingZoneId');
        }

        $formNodes[] = Field::make(t('Name', category: 'commerce'), Text::make('name')->autofocus())
            ->instructions(t('What this shipping zone will be called in the control panel.', category: 'commerce'))
            ->required();
        $formNodes[] = Field::make(t('Description', category: 'commerce'), Text::make('description'))
            ->instructions(t('Describe this shipping zone.', category: 'commerce'));
        // Zones aren't project-config-tracked (Zone::setCondition() hardcodes forProjectConfig
        // to false), so this deliberately doesn't call ->forProjectConfig() either.
        $formNodes[] = Field::make(t('Address Condition'), ConditionBuilder::make('condition')
            ->conditionClass(ZoneAddressCondition::class)
            ->value($shippingZone->getCondition()->getConfig()));

        $values = [
            'storeId' => $store->id,
            'shippingZoneId' => $shippingZone->id,
            'name' => $shippingZone->name,
            'description' => $shippingZone->description,
        ];

        $form = $this->formResolver->resolve(Form::make($formNodes), new FormContext(values: $values));

        return $this->cpScreenResponse($store)
            ->title($title)
            ->crumbs($this->crumbs($store, ...($shippingZone->id ? [['label' => $title]] : [])))
            ->action('commerce/shipping-zones/save')
            ->redirectUrl($store->getStoreSettingsUrl('shippingzones'))
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
                'metadataHtml' => $metadataHtml,
            ]);
    }

    public function save(Request $request): Response
    {
        $shippingZone = new ShippingAddressZone();

        $shippingZone->id = $request->input('shippingZoneId') ? (int)$request->input('shippingZoneId') : null;
        $shippingZone->storeId = $request->input('storeId') ? (int)$request->input('storeId') : null;
        $this->requireStoreAccess($shippingZone->storeId);
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
            t('Couldn’t save shipping zone.', category: 'commerce'),
            'shippingZone'
        );
    }

    public function delete(Request $request): Response
    {
        abort_unless($request->expectsJson(), 400);

        $id = $request->input('id');
        abort_if(!$id, 400, 'Missing shipping zone id');

        $shippingZone = app(ShippingZones::class)->getShippingZoneById((int)$id);
        if ($shippingZone) {
            $this->requireStoreAccess($shippingZone->storeId);
        }

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
