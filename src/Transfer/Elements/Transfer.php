<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Elements;

use CraftCms\Cms\Cp\Data\ActionItem;
use CraftCms\Cms\Cp\Html\StatusHtml;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\Element\Enums\ElementActionContext;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Http\ViewModels\TransferEditViewModel;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevelInTransfer;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Transfer\Conditions\TransferCondition;
use CraftCms\Commerce\Transfer\Data\TransferDetail;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;
use CraftCms\Commerce\Transfer\Models\Transfer as TransferRecord;
use CraftCms\Commerce\Transfer\Models\TransferDetail as TransferDetailRecord;
use CraftCms\Commerce\Transfer\Queries\TransferQuery;
use CraftCms\Commerce\Transfer\Transfers;
use CraftCms\Commerce\Transfer\Validation\TransferRules;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Override;

use function CraftCms\Cms\t;

/**
 * @property-read ?InventoryLocation $originLocation
 * @property-read ?InventoryLocation $destinationLocation
 */
#[Ruleset(TransferRules::class)]
class Transfer extends Element
{
    public TransferStatusType $transferStatus = TransferStatusType::DRAFT;

    public ?int $originLocationId = null;

    public ?int $destinationLocationId = null;

    /** @var TransferDetail[]|null */
    private ?array $_details = null;

    #[Override]
    public function __toString(): string
    {
        $originLocation = $this->getOriginLocation();
        $destinationLocation = $this->getDestinationLocation();

        if ($originLocation === null || $destinationLocation === null) {
            return t('Transfer', category: 'commerce');
        }

        return t('{from} to {to}', [
            'from' => $originLocation->getUiLabel(),
            'to' => $destinationLocation->getUiLabel(),
        ], category: 'commerce');
    }

    #[Override]
    public static function displayName(): string
    {
        return t('Transfer', category: 'commerce');
    }

    #[Override]
    public static function lowerDisplayName(): string
    {
        return t('transfer', category: 'commerce');
    }

    #[Override]
    public static function pluralDisplayName(): string
    {
        return t('Transfers', category: 'commerce');
    }

    #[Override]
    public static function pluralLowerDisplayName(): string
    {
        return t('transfers', category: 'commerce');
    }

    #[Override]
    public static function refHandle(): ?string
    {
        return 'transfer';
    }

    #[Override]
    public static function find(): TransferQuery
    {
        return new TransferQuery();
    }

    #[Override]
    public static function createCondition(): ElementConditionInterface
    {
        return new TransferCondition(static::class);
    }

    #[Override]
    protected static function defineSources(string $context): array
    {
        $transferStatusSources = [];
        foreach (TransferStatusType::cases() as $status) {
            $transferStatusSources[] = [
                'key' => $status->value,
                'status' => $status->color(),
                'label' => $status->label(),
                'badgeCount' => static::find()->transferStatus($status->value)->count(),
                'criteria' => [
                    'transferStatus' => $status->value,
                ],
            ];
        }

        return [
            [
                'key' => '*',
                'label' => t('All Transfers', category: 'commerce'),
                'criteria' => [],
            ],
            [
                'heading' => t('Transfer Status', category: 'commerce'),
            ],
            ...$transferStatusSources,
        ];
    }

    #[Override]
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => t('Date Created', category: 'app'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => t('Date Updated', category: 'app'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    #[Override]
    protected static function defineTableAttributes(): array
    {
        return [
            'id' => ['label' => t('ID', category: 'app')],
            'uid' => ['label' => t('UID', category: 'app')],
            'originLocation' => ['label' => t('Origin', category: 'commerce')],
            'destinationLocation' => ['label' => t('Destination', category: 'commerce')],
            'dateCreated' => ['label' => t('Date Created', category: 'app')],
            'dateUpdated' => ['label' => t('Date Updated', category: 'app')],
            'received' => ['label' => t('Received', category: 'commerce')],
        ];
    }

    #[Override]
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'id',
            'dateCreated',
            'received',
        ];
    }

    #[Override]
    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'originLocation' => $this->getOriginLocation()?->getUiLabel() ?? '',
            'destinationLocation' => $this->getDestinationLocation()?->getUiLabel() ?? '',
            'received' => $this->getTotalReceived() . '/' . $this->getTotalQuantity(),
            default => parent::attributeHtml($attribute),
        };
    }

    #[Override]
    protected function cpEditUrl(): ?string
    {
        return Url::cpUrl("commerce/inventory/transfers/{$this->getCanonicalId()}");
    }

    #[Override]
    public function getPostEditUrl(): ?string
    {
        return Url::cpUrl('commerce/inventory/transfers');
    }

    #[Override]
    protected function metadata(): array
    {
        $metadata = parent::metadata();

        $statusHtml = app(StatusHtml::class)->statusIndicatorHtml($this->getTransferStatus()->label(), [
            'color' => $this->getTransferStatus()->color(),
        ]) . ' ' . Html::tag('span', $this->getTransferStatus()->label());

        $metadata[t('Transfer Status', category: 'commerce')] = $statusHtml;

        return $metadata;
    }

    #[Override]
    public static function editViewModelClass(): string
    {
        return TransferEditViewModel::class;
    }

    /**
     * @return list<ActionItem>
     */
    #[Override]
    protected function crumbs(): array
    {
        return [
            new ActionItem()->label(t('Commerce', category: 'commerce'))->href(Url::cpUrl('commerce')),
            new ActionItem()->label(static::pluralDisplayName())->href(Url::cpUrl('commerce/inventory/transfers')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Override]
    protected function extraActionMenuDescriptors(ElementActionContext $context = ElementActionContext::Editor): array
    {
        if (!$this->id) {
            return [];
        }

        if ($this->canBeReceived()) {
            return [
                [
                    'label' => t('Receive Inventory', category: 'commerce'),
                    'icon' => 'arrow-down',
                    'behavior' => [
                        'type' => 'formModal',
                        'modalUrl' => Url::actionUrl('commerce/transfers/prepare-receive-modal'),
                        'actionUrl' => Url::actionUrl('commerce/transfers/receive-transfer'),
                        'params' => ['transferId' => $this->id],
                    ],
                ],
            ];
        }

        if (!$this->isTransferDraft() || count($this->getDetails()) === 0) {
            return [];
        }

        return [
            [
                'label' => t('Mark as Pending', category: 'commerce'),
                'icon' => 'arrow-right',
                'behavior' => [
                    'type' => 'submit',
                    'actionUrl' => Url::actionUrl('commerce/transfers/mark-as-pending'),
                    'params' => ['transferId' => $this->id],
                    'confirm' => t('Are you sure you want to mark this transfer as pending? This will show as incoming at the destination.', category: 'commerce'),
                ],
            ],
        ];
    }

    #[Override]
    protected function safeActionMenuItems(): array
    {
        $safeActions = parent::safeActionMenuItems();

        if ($this->isTransferDraft() && count($this->getDetails()) > 0) {
            $safeActions['mark-as-pending'] = [
                'action' => 'commerce/transfers/mark-as-pending',
                'label' => t('Mark as Pending', category: 'commerce'),
                'confirm' => t('Are you sure you want to mark this transfer as pending? This will show as incoming at the destination.', category: 'commerce'),
                'params' => [
                    'transferId' => $this->id,
                ],
                'redirect' => 'commerce/inventory/transfers/' . $this->id,
            ];
        }

        return $safeActions;
    }

    #[Override]
    public function getFieldLayout(): ?FieldLayout
    {
        return app(Transfers::class)->getFieldLayout();
    }

    #[Override]
    public function afterValidate(?Validator $validator = null): void
    {
        if ($this->ruleset->inScenarios(TransferRules::SCENARIO_LIVE)) {
            $this->validateLocations();
            $this->validateDetails();
        }

        parent::afterValidate($validator);
    }

    public function validateLocations(): void
    {
        if ($this->originLocationId === $this->destinationLocationId) {
            $this->errors()->add('originLocationId', t('Origin and destination cannot be the same.', category: 'commerce'));
        }
    }

    public function validateDetails(): void
    {
        if ($this->sumDetailsQuanity() < 1) {
            $this->errors()->add('details', t('Transfer must have at least one item.', category: 'commerce'));
        }

        foreach ($this->getDetails() as $detail) {
            if (!$detail->validate()) {
                $this->addModelErrors($detail, 'details');
            }
        }
    }

    /**
     * @return TransferDetail[]
     */
    public function getDetails(): array
    {
        if ($this->_details === null) {
            $this->_details = app(Transfers::class)->getTransferDetailsByTransferId($this->id);
        }

        return $this->_details;
    }

    /**
     * @param TransferDetail[]|array<int, array<string, mixed>> $value
     */
    public function setDetails(array $value): void
    {
        foreach ($value as $key => $detail) {
            if (!$detail instanceof TransferDetail) {
                $value[$key] = new TransferDetail($detail);
            }

            $value[$key]->setTransfer($this);

            if (!$value[$key]->inventoryItemId) {
                unset($value[$key]);
            }
        }

        $this->_details = $value;
    }

    public function sumDetailsQuanity(): int
    {
        $sum = 0;
        foreach ($this->getDetails() as $detail) {
            $sum += $detail->quantity;
        }
        return $sum;
    }

    public function addDetail(TransferDetail $detail): void
    {
        if (!$this->_details) {
            $this->_details = [];
        }

        foreach ($this->_details as $existingDetail) {
            if ($existingDetail->inventoryItemId == $detail->inventoryItemId) {
                $existingDetail->quantity += $detail->quantity;
                return;
            }
        }

        $this->_details[] = $detail;
    }

    public function getOriginLocation(): ?InventoryLocation
    {
        if (!$this->originLocationId) {
            return null;
        }

        return app(InventoryLocations::class)->getInventoryLocationById($this->originLocationId);
    }

    public function getDestinationLocation(): ?InventoryLocation
    {
        if (!$this->destinationLocationId) {
            return null;
        }

        return app(InventoryLocations::class)->getInventoryLocationById($this->destinationLocationId);
    }

    public function getTransferStatus(): TransferStatusType
    {
        return $this->transferStatus;
    }

    public function setTransferStatus(TransferStatusType|string $status): void
    {
        if (is_string($status)) {
            $status = TransferStatusType::from($status);
        }

        $this->transferStatus = $status;
    }

    /**
     * Updates the status to partial or received if all items have been received.
     */
    public function updateTransferStatus(): void
    {
        // only pending can become partial or received.
        if ($this->isTransferDraft()) {
            return;
        }

        $this->setTransferStatus(TransferStatusType::PENDING);

        if ($this->isAllReceived()) {
            $this->setTransferStatus(TransferStatusType::RECEIVED);
        }

        if ($this->getTotalReceived() > 0 && $this->getTotalReceived() < $this->getTotalQuantity()) {
            $this->setTransferStatus(TransferStatusType::PARTIAL);
        }
    }

    public function isTransferDraft(): bool
    {
        return $this->getTransferStatus() === TransferStatusType::DRAFT;
    }

    public function isTransferPending(): bool
    {
        return $this->getTransferStatus() === TransferStatusType::PENDING;
    }

    public function isTransferPartial(): bool
    {
        return $this->getTransferStatus() === TransferStatusType::PARTIAL;
    }

    public function isTransferReceived(): bool
    {
        return $this->getTransferStatus() === TransferStatusType::RECEIVED;
    }

    /**
     * Whether the transfer has been sent and is still waiting on some of its items.
     */
    public function canBeReceived(): bool
    {
        return $this->isTransferPending() || $this->isTransferPartial();
    }

    public function getTotalRejected(): int
    {
        $totalRejected = 0;
        foreach ($this->getDetails() as $detail) {
            $totalRejected += $detail->quantityRejected;
        }
        return $totalRejected;
    }

    public function getTotalAccepted(): int
    {
        $totalAccepted = 0;
        foreach ($this->getDetails() as $detail) {
            $totalAccepted += $detail->quantityAccepted;
        }
        return $totalAccepted;
    }

    public function getTotalReceived(): int
    {
        return $this->getTotalAccepted() + $this->getTotalRejected();
    }

    public function isAllReceived(): bool
    {
        return array_all($this->getDetails(), fn($detail) => !($detail->getReceived() < $detail->quantity));
    }

    public function getTotalQuantity(): int
    {
        $totalQuantity = 0;
        foreach ($this->getDetails() as $detail) {
            $totalQuantity += $detail->quantity;
        }
        return $totalQuantity;
    }

    #[Override]
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            DB::transaction(function(): void {
                $transferRecord = TransferRecord::find($this->getCanonicalId()) ?? new TransferRecord();
                $originalTransferStatus = $transferRecord->transferStatus;

                $transferRecord->id = $this->id;
                $transferRecord->originLocationId = $this->originLocationId;
                $transferRecord->destinationLocationId = $this->destinationLocationId;
                $transferRecord->transferStatus = $this->getTransferStatus()->value;
                $transferRecord->save();

                if ($this->isTransferPending() && $originalTransferStatus === TransferStatusType::DRAFT->value) {
                    $this->moveDetailsToIncoming();
                }

                $this->saveDetails();

                $this->updateTransferStatus();
                $transferRecord->transferStatus = $this->getTransferStatus()->value;
                $transferRecord->save();
            });
        }

        parent::afterSave($isNew);
    }

    /**
     * Moves each detail's quantity out of the origin location's on-hand stock and into the destination
     * location's incoming stock.
     */
    private function moveDetailsToIncoming(): void
    {
        $inventoryUpdateCollection = new UpdateInventoryLevelCollection();

        foreach ($this->getDetails() as $detail) {
            $inventoryUpdateCollection->push(new UpdateInventoryLevelInTransfer([
                'type' => InventoryTransactionType::INCOMING->value,
                'updateAction' => InventoryUpdateQuantityType::ADJUST,
                'inventoryItemId' => $detail->inventoryItemId,
                'transferId' => $this->id,
                'inventoryLocationId' => $this->destinationLocationId,
                'quantity' => $detail->quantity,
                'note' => t('Incoming transfer from Transfer ID: {id}', ['id' => $this->id], category: 'commerce'),
            ]));

            $inventoryUpdateCollection->push(new UpdateInventoryLevelInTransfer([
                'type' => 'onHand',
                'updateAction' => InventoryUpdateQuantityType::ADJUST,
                'inventoryItemId' => $detail->inventoryItemId,
                'transferId' => $this->id,
                'inventoryLocationId' => $this->originLocationId,
                'quantity' => $detail->quantity * -1,
                'note' => t('Outgoing transfer from Transfer ID: {id}', ['id' => $this->id], category: 'commerce'),
            ]));
        }

        app(Inventory::class)->executeUpdateInventoryLevels($inventoryUpdateCollection);
    }

    private function saveDetails(): void
    {
        $existingDetailIds = TransferDetailRecord::where('transferId', $this->id)->pluck('id')->all();
        $currentDetailIds = [];

        foreach ($this->getDetails() as $detail) {
            $detailRecord = ($detail->id ? TransferDetailRecord::find($detail->id) : null) ?? new TransferDetailRecord();

            if ($detail->uid) {
                $detailRecord->uid = $detail->uid;
            }

            $detailRecord->transferId = $this->id;
            $detailRecord->inventoryItemId = $detail->inventoryItemId;
            $detailRecord->inventoryItemDescription = $detail->getInventoryItem()?->getSku() ?? '';
            $detailRecord->quantity = $detail->quantity;
            $detailRecord->quantityAccepted = $detail->quantityAccepted;
            $detailRecord->quantityRejected = $detail->quantityRejected;
            $detailRecord->save();

            $detail->id = $detailRecord->id;
            $detail->uid = $detailRecord->uid;
            $currentDetailIds[] = $detailRecord->id;
        }

        $deletedDetailIds = array_diff($existingDetailIds, $currentDetailIds);

        if (!empty($deletedDetailIds)) {
            TransferDetailRecord::whereIn('id', $deletedDetailIds)->delete();
        }
    }
}
