<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Transfers;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

class TransferSettingsController extends SettingsController
{
    use RespondsWithFlash;

    public function saveTransferSettings(): Response
    {
        $fieldLayout = Fields::assembleLayoutFromPost();

        $fieldLayout->reservedFieldHandles = [
            'originLocationId',
            'originLocation',
            'destinationLocationId',
            'destinationLocation',
        ];

        $fieldLayout->type = Transfer::class;

        if (!$fieldLayout->validate()) {
            return $this->asFailure(t('Couldn’t save transfer fields.', category: 'commerce'));
        }

        if ($currentTransfersFieldLayout = ProjectConfig::get(Transfers::CONFIG_FIELDLAYOUT_KEY)) {
            $uid = array_key_first($currentTransfersFieldLayout);
        } else {
            $uid = (string)Str::uuid();
        }

        $configData = [$uid => $fieldLayout->getConfig()];
        $result = ProjectConfig::set(Transfers::CONFIG_FIELDLAYOUT_KEY, $configData, force: true);

        if (!$result) {
            return $this->asFailure(t('Couldn’t save transfer fields.', category: 'commerce'));
        }

        return $this->asSuccess(t('Transfer fields saved.', category: 'commerce'));
    }

    public function editTransferSettings(): string
    {
        $fieldLayout = app(Transfers::class)->getFieldLayout();

        return pageTemplate('commerce/settings/transfers/_edit', [
            'fieldLayout' => $fieldLayout,
            'title' => t('Transfer Settings', category: 'commerce'),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }
}
