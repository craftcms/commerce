<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Form\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Orders;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

class OrderSettingsController extends BaseSettingsController
{
    protected function getSectionCrumb(): array
    {
        return ['label' => t('Order Fields', category: 'commerce'), 'href' => cp_url('commerce/settings/ordersettings')];
    }

    public function edit(): CpScreenResponse
    {
        $fieldLayout = Fields::getLayoutByType(Order::class);
        $title = t('Order Settings', category: 'commerce');

        $form = Form::make([
            Field::make(null, FieldLayoutDesigner::make('fieldLayout')
                ->elementType(Order::class)
                ->withCardViewDesigner()),
        ]);

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs())
            ->redirectUrl('commerce/settings/ordersettings')
            ->inertiaPage('Form', [
                'form' => $this->formResolver->resolve($form, new FormContext(
                    values: [
                        'fieldLayout' => [
                            'id' => $fieldLayout->id,
                            'uid' => $fieldLayout->uid,
                            ...($fieldLayout->getConfig() ?? []),
                        ],
                    ],
                    mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
                )),
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'save']),
                ],
            ]);
    }

    public function save(): Response
    {
        $fieldLayout = Fields::assembleLayoutFromPost();

        $fieldLayout->reservedFieldHandles = [
            'billingAddress',
            'customer',
            'estimatedBillingAddress',
            'estimatedShippingAddress',
            'paymentAmount',
            'paymentCurrency',
            'paymentSource',
            'recalculationMode',
            'shippingAddress',
        ];

        if (!$fieldLayout->validate()) {
            return $this->asFailure(t('Couldn’t save order fields.', category: 'commerce'));
        }

        if ($currentOrderFieldLayout = ProjectConfig::get(Orders::CONFIG_FIELDLAYOUT_KEY)) {
            $uid = array_key_first($currentOrderFieldLayout);
        } else {
            $uid = (string)Str::uuid();
        }

        $configData = [$uid => $fieldLayout->getConfig()];
        ProjectConfig::set(Orders::CONFIG_FIELDLAYOUT_KEY, $configData);

        return $this->asSuccess(t('Order fields saved.', category: 'commerce'));
    }
}
