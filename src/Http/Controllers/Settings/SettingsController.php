<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use craft\commerce\Plugin;
use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Form\Controls\Combobox;
use CraftCms\Cms\Form\Enums\ControlMode;
use CraftCms\Cms\Form\Form;
use CraftCms\Cms\Form\FormContext;
use CraftCms\Cms\Form\FormResolver;
use CraftCms\Cms\Form\Nodes\Field;
use CraftCms\Cms\Form\Nodes\Heading;
use CraftCms\Cms\Form\Nodes\Separator;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Transfers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class SettingsController
{
    use RespondsWithFlash;

    private bool $readOnly;

    public function __construct(
        private GeneralConfig $generalConfig,
        private FormResolver $formResolver,
    )
    {
        $this->readOnly = !$generalConfig->allowAdminChanges;
    }

    public function edit($settings = null): CpScreenResponse
    {
        $settings = Plugin::getInstance()->getSettings();
        $config = Config::get('craft.commerce', null);

        $overrideWarning = function($key) use ($config) {
            if ($config && isset($config[$key])) {
                return t("This is being overridden by the {setting} config setting in `config/{file}.php`.", [
                    'setting' => $key,
                    'file' => 'commerce',
                ], category: 'commerce');
            }

            return null;
        };

        $form = Form::make([
            Heading::make('units-heading', t('Units', category: 'commerce'))->level(3),
            Field::make(t('Weight Unit'), Combobox::make('weightUnits')
                ->options(array_map(fn($unit, $label) => ['value' => $unit, 'label' => $label], array_keys($settings->getWeightUnitsOptions()), $settings->getWeightUnitsOptions()))
                ->showAllOnEmpty())
                ->required()
                ->instructions(t('The unit of measurement that should be used when specifying product weights.', category: 'commerce')),
            Field::make(t('Dimension Unit'), Combobox::make('dimensionUnits')
                ->options(array_map(fn($unit, $label) => ['value' => $unit, 'label' => $label], array_keys($settings->getDimensionUnits()), $settings->getDimensionUnits()))
                ->showAllOnEmpty())
                ->required()
                ->instructions(t('The unit of measurement that should be used when specifying product dimensions.', category: 'commerce')),
            Separator::make('default-view-separator'),
            Heading::make('default-view-heading', t('Control Panel Settings', category: 'commerce'))->level(3),
            Field::make(t('Default View'), Combobox::make('defaultView')
                ->options(array_map(fn($unit, $label) => ['value' => $unit, 'label' => $label], array_keys($settings->getDefaultViewOptions()), $settings->getDefaultViewOptions()))
                ->showAllOnEmpty())
                ->required()
                ->warning($overrideWarning('defaultView'))
                ->instructions(t('Default Commerce control panel view. If the user does not have permission it will fall back to a location they can access.', category: 'commerce')),
        ]);

        $form = $this->formResolver->resolve($form, new FormContext(
            namespace: 'settings',
            values: [
                'settings' => [
                    'weightUnits' => $settings->weightUnits,
                    'dimensionUnits' => $settings->dimensionUnits,
                    'defaultView' => $settings->defaultView,
                ],
            ],
            mode: $this->generalConfig->allowAdminChanges ? ControlMode::Editable : ControlMode::ReadOnly,
        ));

        return new CpScreenResponse()
            ->title(t('General Settings', category: 'commerce'))
            ->crumbs([
                ['label' => t('Commerce', category: 'commerce'), 'href' => Url::cpUrl('commerce')],
            ])
            ->redirectUrl('commerce/settings/general')
            ->inertiaPage('Form', [
                'form' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'saveSettings']),
                ],
            ]);
    }

    public function saveSettings(Request $request): Response|string
    {
        $plugin = Plugin::getInstance();
        $settings = $request->input('settings');
        $pluginSettingsSaved = Plugins::savePluginSettings($plugin, $settings);

        if (!$pluginSettingsSaved) {
            return $this->asFailure(t('Couldn’t save settings.', category: 'commerce'));
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
