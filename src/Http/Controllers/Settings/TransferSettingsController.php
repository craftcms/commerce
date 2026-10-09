<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Settings;

use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Ui\Controls\FieldLayoutDesigner;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\Field;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Transfers;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\cp_url;
use function CraftCms\Cms\t;

class TransferSettingsController extends BaseSettingsController
{
    use RespondsWithFlash;

    protected function getSectionCrumb(): array
    {
        return ['label' => t('Transfer Fields', category: 'commerce'), 'href' => cp_url('commerce/settings/transfers')];
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

    public function editTransferSettings(): CpScreenResponse
    {
        $fieldLayout = app(Transfers::class)->getFieldLayout();

        $form = Ui::make([
            Field::make(null, FieldLayoutDesigner::make('fieldLayout')
                ->elementType(Transfer::class)
                ->withCardViewDesigner()),
        ]);

        $form = $this->formResolver->resolve($form, new UiContext(
            values: [
                'fieldLayout' => [
                    'id' => $fieldLayout->id,
                    'uid' => $fieldLayout->uid,
                    ...($fieldLayout->getConfig() ?? []),
                ],
            ],
            mode: $this->generalConfig->allowAdminChanges ? ControlMode::Editable : ControlMode::ReadOnly,
        ));

        $title = t('Transfer Settings', category: 'commerce');

        return $this->cpScreenResponse()
            ->title($title)
            ->crumbs($this->crumbs())
            ->redirectUrl('commerce/settings/transfers')
            ->inertiaPage('Ui', [
                'ui' => $form,
                'submit' => [
                    'method' => 'post',
                    'url' => action([self::class, 'saveTransferSettings']),
                ],
            ]);
    }
}
