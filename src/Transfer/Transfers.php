<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer;

use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\FieldLayout\FieldLayoutTab;
use CraftCms\Cms\ProjectConfig\Events\ConfigEvent;
use CraftCms\Cms\ProjectConfig\ProjectConfigHelper;
use CraftCms\Cms\Support\Arr;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Str;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Data\InventoryTransferMovement;
use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Transfer\Data\TransferDetail;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;
use CraftCms\Commerce\Transfer\FieldLayoutElements\TransferManagementField;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function CraftCms\Cms\t;

#[Singleton]
class Transfers
{
    public const string CONFIG_FIELDLAYOUT_KEY = 'commerce.transfers.fieldLayouts';

    /**
     * Handle field layout change
     *
     * @throws \Exception
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

    /**
     * Marks a draft transfer as pending, which moves its quantities into the destination's incoming stock.
     */
    public function markAsPending(Transfer $transfer): bool
    {
        if (!$transfer->isTransferDraft()) {
            return false;
        }

        $transfer->setTransferStatus(TransferStatusType::PENDING);

        if (!DB::transaction(fn(): bool => Elements::saveElement($transfer))) {
            $transfer->setTransferStatus(TransferStatusType::DRAFT);
            return false;
        }

        return true;
    }

    /**
     * Receives inventory for a pending or partially received transfer.
     *
     * Accepted quantities move from the destination's incoming stock to its available stock; rejected
     * quantities are removed from the destination's incoming stock.
     *
     * @param array<string, array{accept?: int|string|null, reject?: int|string|null}> $quantities keyed by transfer detail UID
     */
    public function receive(Transfer $transfer, array $quantities): void
    {
        $destinationLocation = $transfer->getDestinationLocation();

        if ($destinationLocation === null) {
            throw new RuntimeException('The transfer has no destination location.');
        }

        $inventoryMovements = new InventoryMovementCollection();
        $inventoryUpdates = new UpdateInventoryLevelCollection();
        $details = $transfer->getDetails();

        foreach ($details as $detail) {
            $acceptedQuantity = (int)($quantities[$detail->uid]['accept'] ?? 0);
            $rejectedQuantity = (int)($quantities[$detail->uid]['reject'] ?? 0);

            if ($acceptedQuantity > 0) {
                $detail->quantityAccepted += $acceptedQuantity;

                $inventoryMovement = new InventoryTransferMovement([
                    'quantity' => $acceptedQuantity,
                    'transferId' => $transfer->id,
                    'fromInventoryLocation' => $destinationLocation,
                    'toInventoryLocation' => $destinationLocation,
                    'fromInventoryTransactionType' => InventoryTransactionType::INCOMING,
                    'toInventoryTransactionType' => InventoryTransactionType::AVAILABLE,
                ]);
                $inventoryMovement->setInventoryItem($detail->getInventoryItem());
                $inventoryMovements->push($inventoryMovement);
            }

            if ($rejectedQuantity > 0) {
                $detail->quantityRejected += $rejectedQuantity;

                $inventoryUpdate = new UpdateInventoryLevel([
                    'type' => InventoryTransactionType::INCOMING->value,
                    'updateAction' => InventoryUpdateQuantityType::ADJUST,
                    'inventoryItemId' => $detail->inventoryItemId,
                    'transferId' => $transfer->id,
                    'quantity' => $rejectedQuantity * -1,
                ]);
                $inventoryUpdate->setInventoryLocation($destinationLocation);
                $inventoryUpdates->push($inventoryUpdate);
            }
        }

        $transfer->setDetails($details);

        DB::transaction(function() use ($transfer, $inventoryMovements, $inventoryUpdates): void {
            if (!app(Inventory::class)->executeInventoryMovements($inventoryMovements)) {
                throw new RuntimeException('The received inventory movements are invalid.');
            }

            app(Inventory::class)->executeUpdateInventoryLevels($inventoryUpdates);

            if (!Elements::saveElement($transfer, false)) {
                throw new RuntimeException('The transfer could not be saved.');
            }
        });
    }
}
