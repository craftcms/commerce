<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Ui\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Number;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\License\Elements\License;
use CraftCms\Commerce\License\Licenses;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

/**
 * The licenses settings screen: how licenses are issued and assigned, and the license field layout.
 */
class LicenseSettingsController extends BaseSettingsController
{
    /** The Commerce settings this screen edits. */
    private const array SETTINGS = [
        'generateLicenseOnOrderPaid',
        'requireLoggedInUserForLicenses',
        'autoAssignLicensesOnPurchase',
        'autoAssignLicensesOnUserRegistration',
        'licenseKeyCharacters',
        'licenseKeyLength',
    ];

    protected function getSectionCrumb(): array
    {
        return ['label' => t('Licenses', category: 'commerce'), 'href' => cp_url('commerce/settings/licenses')];
    }

    public function edit(): CpScreenResponse
    {
        $settings = $this->plugin->getSettings();
        $fieldLayout = app(Licenses::class)->getFieldLayout();

        $ui = Ui::make()
            ->addTab(t('Settings'), [
                Field::make(t('Generate licenses when orders are paid', category: 'commerce'), Lightswitch::make('settings.generateLicenseOnOrderPaid'))
                    ->instructions(t('When disabled, licenses are generated when orders are completed.', category: 'commerce')),
                Field::make(t('Require a logged-in user to purchase licensed variants', category: 'commerce'), Lightswitch::make('settings.requireLoggedInUserForLicenses')),
                Field::make(t('Assign licenses to the user with the owner email', category: 'commerce'), Lightswitch::make('settings.autoAssignLicensesOnPurchase')),
                Field::make(t('Assign licenses to users when they activate their account', category: 'commerce'), Lightswitch::make('settings.autoAssignLicensesOnUserRegistration')),
                Field::make(t('License Key Characters', category: 'commerce'), Text::make('settings.licenseKeyCharacters')->monospace())
                    ->required(),
                Field::make(t('License Key Length', category: 'commerce'), Number::make('settings.licenseKeyLength')->min(1)->max(255)->size(3))
                    ->required(),
            ])
            ->addTab(t('License Fields', category: 'commerce'), [
                Field::make(null, FieldLayoutDesigner::make('fieldLayout')
                    ->elementType(License::class)
                    ->withCardViewDesigner()),
            ]);

        $ui = $this->uiResolver->resolve($ui, new UiContext(
            values: [
                'settings' => collect(self::SETTINGS)->mapWithKeys(fn(string $key) => [$key => $settings->$key])->all(),
                'fieldLayout' => [
                    'id' => $fieldLayout->id,
                    'uid' => $fieldLayout->uid,
                    ...($fieldLayout->getConfig() ?? []),
                ],
            ],
            mode: $this->readOnly ? ControlMode::ReadOnly : ControlMode::Editable,
        ));

        return $this->cpScreenResponse()
            ->title(t('Licenses', category: 'commerce'))
            ->crumbs($this->crumbs())
            ->redirectUrl('commerce/settings/licenses')
            ->ui($ui, props: [
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'store']),
                ],
            ]);
    }

    public function store(Request $request): Response
    {
        $posted = collect($request->input('settings', []))->only(self::SETTINGS)->all();

        if (!Plugins::savePluginSettings($this->plugin, $posted)) {
            return $this->asFailure(t('Couldn’t save settings.', category: 'commerce'));
        }

        $fieldLayout = Fields::assembleLayoutFromPost();
        $fieldLayout->type = License::class;
        $fieldLayout->reservedFieldHandles = ['variant', 'product', 'order', 'owner', 'licenseKey', 'ownerName', 'ownerEmail'];

        if (!$fieldLayout->validate()) {
            return $this->asFailure(t('Couldn’t save license fields.', category: 'commerce'));
        }

        $current = ProjectConfig::get(Licenses::FIELD_LAYOUT_CONFIG_KEY);
        $uid = $current ? array_key_first($current) : (string)Str::uuid();
        ProjectConfig::set(Licenses::FIELD_LAYOUT_CONFIG_KEY, [$uid => $fieldLayout->getConfig()], force: true);

        return $this->asSuccess(t('Settings saved.', category: 'commerce'));
    }
}
