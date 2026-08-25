<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Elements;

use craft\commerce\web\assets\transfers\TransfersAsset;
use CraftCms\Cms\Cp\Html\StatusHtml;
use CraftCms\Cms\Element\Conditions\Contracts\ElementConditionInterface;
use CraftCms\Cms\Element\Element;
use CraftCms\Cms\FieldLayout\FieldLayout;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Inventory\Models\InventoryLocation;
use CraftCms\Commerce\Inventory\Models\UpdateInventoryLevelInTransfer;
use CraftCms\Commerce\Transfer\Conditions\TransferCondition;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;
use CraftCms\Commerce\Transfer\Models\TransferDetail;
use CraftCms\Commerce\Transfer\Queries\TransferQuery;
use CraftCms\Commerce\Transfer\Records\Transfer as TransferRecord;
use CraftCms\Commerce\Transfer\Records\TransferDetail as TransferDetailRecord;
use CraftCms\Commerce\Transfer\Transfers;
use CraftCms\Commerce\Transfer\Validation\TransferRules;
use CraftCms\RulesetValidation\Attributes\Ruleset;
use Illuminate\Validation\Validator;
use Override;
use Symfony\Component\HttpFoundation\Response;

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
        if ($this->getOriginLocation() === null && $this->getDestinationLocation() === null) {
            return t('Transfer', category: 'commerce');
        }

        return t('{from} to {to}', [
            'from' => $this->getOriginLocation()->getUiLabel(),
            'to' => $this->getDestinationLocation()->getUiLabel(),
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
                'label' => t($status->label(), category: 'commerce'),
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
    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        return $user->can('commerce-manageInventoryTransfers');
    }

    #[Override]
    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }

        return $user->can('commerce-manageInventoryTransfers');
    }

    #[Override]
    public function canDuplicate(User $user): bool
    {
        return false;
    }

    #[Override]
    public function canDelete(User $user): bool
    {
        $canDelete = false;

        if (parent::canSave($user)) {
            $canDelete = true;
        }

        if ($this->getTransferStatus() === TransferStatusType::DRAFT) {
            $canDelete = true;
        }

        return $canDelete && $user->can('commerce-manageInventoryTransfers');
    }

    #[Override]
    public function canCreateDrafts(User $user): bool
    {
        return false;
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
    public function getMetadata(): array
    {
        $metadata = parent::getMetadata();

        $statusHtml = app(StatusHtml::class)->statusIndicatorHtml($this->getTransferStatus()->label(), [
            'color' => $this->getTransferStatus()->color(),
        ]) . ' ' . Html::tag('span', $this->getTransferStatus()->label());

        return array_merge([t('Transfer Status', category: 'commerce') => $statusHtml], $metadata);
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

    #[Override]
    public function prepareEditScreen(Response|CpScreenResponse $response, string $containerId): void
    {
        // TODO: this still registers the legacy `craft\commerce\web\assets\transfers\TransfersAsset`
        // yii2 AssetBundle via the yii2-adapter bridge, since Commerce's own webpack-built CP assets
        // haven't been ported to a native `HtmlStack`-based registration mechanism yet.
        \Craft::$app->getView()->registerAssetBundle(TransfersAsset::class);

        HtmlStack::jsWithVars(fn($containerId, $settingsJs) => <<<JS
new Craft.Commerce.TransferEdit($('#' + $containerId), $settingsJs);
JS, [
            $containerId,
            [],
        ]);

        $receiveInventoryButtonId = sprintf('receive-transfer-%s', mt_rand());

        HtmlStack::jsWithVars(fn($id, $settings) => <<<JS
$('#' + $id).on('click', (e) => {
	e.preventDefault();
	const modal = new Craft.Commerce.ReceiveTransferScreen($settings);
	modal.on('close', (e) => {
	  console.log('closed');
	});
});
JS, [
            $receiveInventoryButtonId,
            ['params' => ['transferId' => $this->id]],
        ]);

        if (!$this->isTransferDraft()) {
            $response->additionalButtonsHtml(Html::a(
                t('Receive Inventory', category: 'commerce'),
                '#',
                [
                    'id' => $receiveInventoryButtonId,
                    'class' => 'btn',
                ]
            ));
        }

        $response->crumbs([
            [
                'label' => t('Commerce', category: 'commerce'),
                'url' => Url::cpUrl('commerce'),
            ],
            [
                'label' => self::pluralDisplayName(),
                'url' => Url::cpUrl('commerce/inventory/transfers'),
            ],
        ]);

        $response->selectedSubnavItem('inventory-transfers');
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
            $transferId = $this->getCanonicalId();
            $transferRecord = TransferRecord::find($transferId);

            if (!$transferRecord) {
                $transferRecord = new TransferRecord();
            }

            $originalTransferStatus = $transferRecord->transferStatus;

            $transferRecord->id = $this->id;
            $transferRecord->originLocationId = $this->originLocationId;
            $transferRecord->destinationLocationId = $this->destinationLocationId;
            $transferRecord->transferStatus = $this->getTransferStatus()->value;

            $transferRecord->save();

            if ($this->getTransferStatus() === TransferStatusType::PENDING && $originalTransferStatus === TransferStatusType::DRAFT->value) {
                $inventoryUpdateCollection = new UpdateInventoryLevelCollection();
                foreach ($this->getDetails() as $detail) {
                    $inventoryUpdate1 = new UpdateInventoryLevelInTransfer();
                    $inventoryUpdate1->type = InventoryTransactionType::INCOMING->value;
                    $inventoryUpdate1->updateAction = InventoryUpdateQuantityType::ADJUST;
                    $inventoryUpdate1->inventoryItemId = $detail->inventoryItemId;
                    $inventoryUpdate1->transferId = $this->id;
                    $inventoryUpdate1->inventoryLocationId = $this->destinationLocationId;
                    $inventoryUpdate1->quantity = $detail->quantity;
                    $inventoryUpdate1->note = t('Incoming transfer from Transfer ID: ', category: 'commerce') . $this->id;

                    $inventoryUpdateCollection->push($inventoryUpdate1);

                    $inventoryUpdate2 = new UpdateInventoryLevelInTransfer();
                    $inventoryUpdate2->type = 'onHand';
                    $inventoryUpdate2->updateAction = InventoryUpdateQuantityType::ADJUST;
                    $inventoryUpdate2->inventoryItemId = $detail->inventoryItemId;
                    $inventoryUpdate2->transferId = $this->id;
                    $inventoryUpdate2->inventoryLocationId = $this->originLocationId;
                    $inventoryUpdate2->quantity = $detail->quantity * -1;
                    $inventoryUpdate2->note = t('Outgoing transfer from Transfer ID: ', category: 'commerce') . $this->id;

                    $inventoryUpdateCollection->push($inventoryUpdate2);
                }

                app(Inventory::class)->executeUpdateInventoryLevels($inventoryUpdateCollection);
            }

            $existingDetailIds = TransferDetailRecord::where('transferId', $this->id)->pluck('id')->all();

            $currentDetailIds = [];

            foreach ($this->getDetails() as $detail) {
                if ($detail->id) {
                    $detailRecord = TransferDetailRecord::find($detail->id);
                } else {
                    $detailRecord = new TransferDetailRecord();
                }
                $detailRecord->transferId = $this->id;
                $detailRecord->inventoryItemId = $detail->inventoryItemId;
                $inventoryItem = $detail->inventoryItemId ? app(Inventory::class)->getInventoryItemById($detail->inventoryItemId) : null;
                $detailRecord->inventoryItemDescription = $inventoryItem?->getSku() ?? '';
                $detailRecord->quantity = $detail->quantity;
                $detailRecord->quantityAccepted = $detail->quantityAccepted;
                $detailRecord->quantityRejected = $detail->quantityRejected;

                $detailRecord->save();
                $detail->id = $detailRecord->id;

                $currentDetailIds[] = $detailRecord->id;
            }

            $deletedDetailIds = array_diff($existingDetailIds, $currentDetailIds);
            if (!empty($deletedDetailIds)) {
                TransferDetailRecord::whereIn('id', $deletedDetailIds)->delete();
            }

            $this->updateTransferStatus();
            $transferRecord->transferStatus = $this->getTransferStatus()->value;

            $transferRecord->save();
        }

        parent::afterSave($isNew);
    }
}
