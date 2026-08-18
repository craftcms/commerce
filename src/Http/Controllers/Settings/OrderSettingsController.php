<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\services\Orders;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Order\Elements\Order;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class OrderSettingsController
{
    use RespondsWithFlash;

    private bool $readOnly;

    public function __construct(GeneralConfig $generalConfig)
    {
        $this->readOnly = !$generalConfig->allowAdminChanges;
    }

    public function edit(): string
    {
        $fieldLayout = Fields::getLayoutByType(Order::class);

        return pageTemplate('commerce/settings/ordersettings/_edit', [
            'fieldLayout' => $fieldLayout,
            'title' => t('Order Settings', category: 'commerce'),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
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
            return $this->asFailure(t('Couldn\'t save order fields.', category: 'commerce'));
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
