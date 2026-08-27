<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer;

use craft\events\ConfigEvent;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\FieldLayoutTab;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Transfer\Data\TransferDetail;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\FieldLayoutElements\TransferManagementField;
use Illuminate\Support\Facades\DB;

use function CraftCms\Cms\t;

class Transfers
{
    public const string CONFIG_FIELDLAYOUT_KEY = 'commerce.transfers.fieldLayouts';

    /**
     * Handle field layout change
     *
     * @throws \Exception
     *
     * @todo `ConfigEvent`/`ProjectConfigHelper` are still legacy `craft\` classes because this method
     * is wired up as a listener against the legacy project config service in `src-yii2/Plugin.php`
     * (`ProjectConfig::onAdd()`), which hasn't migrated to `src/` yet. Update both once it has.
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfigHelper::ensureAllFieldsProcessed();

        if (empty($data) || empty(reset($data))) {
            // Delete the field layout
            Fields::deleteLayoutsByType(Transfer::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = Fields::getLayoutByType(Transfer::class)->id;
        $layout->type = Transfer::class;
        $layout->uid = key($data);
        Fields::saveLayout($layout, false);
    }

    /**
     * Handle field layout being deleted
     */
    public function handleDeletedFieldLayout(): void
    {
        Fields::deleteLayoutsByType(Transfer::class);
    }

    public function getFieldLayout(): FieldLayout
    {
        $fieldLayout = Fields::getLayoutByType(Transfer::class);

        if (!$fieldLayout->isFieldIncluded('transfer-management')) {
            $layoutTabs = $fieldLayout->getTabs();
            $transfersTabName = t('Manage', category: 'commerce');
            if (Arr::contains($layoutTabs, 'name', $transfersTabName)) {
                $transfersTabName .= ' ' . Str::random(10);
            }

            $contentTab = new FieldLayoutTab();
            $contentTab->setLayout($fieldLayout);
            $contentTab->name = $transfersTabName;
            $contentTab->setElements([
                ['type' => TransferManagementField::class],
            ]);

            $layoutTabs[] = $contentTab;
            $fieldLayout->setTabs($layoutTabs);
        }

        return $fieldLayout;
    }

    /**
     * @return TransferDetail[]
     */
    public function getTransferDetailsByTransferId(int $transferId): array
    {
        $results = DB::table(Table::TRANSFERDETAILS)
            ->select([
                'id',
                'transferId',
                'inventoryItemId',
                'inventoryItemDescription',
                'quantity',
                'quantityAccepted',
                'quantityRejected',
                'uid',
            ])
            ->where('transferId', $transferId)
            ->get();

        $transferDetails = [];

        foreach ($results as $result) {
            $transferDetails[] = new TransferDetail((array)$result);
        }

        return $transferDetails;
    }
}
