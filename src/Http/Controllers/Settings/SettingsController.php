<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\elements\Transfer;
use craft\commerce\Plugin;
use craft\commerce\services\Transfers;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\View\TemplateMode;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class SettingsController
{
    use RespondsWithFlash;

    private bool $readOnly;

    public function __construct(GeneralConfig $generalConfig)
    {
        $this->readOnly = !$generalConfig->allowAdminChanges;
    }

    public function edit(): string
    {
        return pageTemplate('commerce/settings/general', [
            'settings' => Plugin::getInstance()->getSettings(),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

    public function saveSettings(Request $request): Response|string
    {
        $plugin = Plugin::getInstance();
        $settings = $request->input('settings');
        $pluginSettingsSaved = Plugins::savePluginSettings($plugin, $settings);

        if (!$pluginSettingsSaved) {
            return pageTemplate('commerce/settings/general/index', ['settings' => $plugin->getSettings()], TemplateMode::Cp);
        }

        return $this->asSuccess(t('Settings saved.', category: 'commerce'));
    }

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
            return $this->asFailure(t('Couldn\'t save transfer fields.', category: 'commerce'));
        }

        if ($currentTransfersFieldLayout = ProjectConfig::get(Transfers::CONFIG_FIELDLAYOUT_KEY)) {
            $uid = array_key_first($currentTransfersFieldLayout);
        } else {
            $uid = (string)Str::uuid();
        }

        $configData = [$uid => $fieldLayout->getConfig()];
        $result = ProjectConfig::set(Transfers::CONFIG_FIELDLAYOUT_KEY, $configData, force: true);

        if (!$result) {
            return $this->asFailure(t('Couldn\'t save transfer fields.'));
        }

        return $this->asSuccess(t('Transfer fields saved.', category: 'commerce'));
    }

    public function editTransferSettings(): string
    {
        $fieldLayout = Plugin::getInstance()->getTransfers()->getFieldLayout();

        return pageTemplate('commerce/settings/transfers/_edit', [
            'fieldLayout' => $fieldLayout,
            'title' => t('Transfer Settings', category: 'commerce'),
            'readOnly' => $this->readOnly,
        ], TemplateMode::Cp);
    }

}
