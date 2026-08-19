<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers;

use craft\commerce\elements\Transfer;
use craft\commerce\fieldlayoutelements\TransferManagementField;
use craft\commerce\services\Transfers;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Http\RespondsWithFlash;
use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Facades\Drafts;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Inventory\Collections\InventoryMovementCollection;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Enums\InventoryTransactionType;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Inventory\Models\InventoryTransferMovement;
use CraftCms\Commerce\Inventory\Models\UpdateInventoryLevel;
use CraftCms\Commerce\Transfer\Enums\TransferStatusType;
use CraftCms\Commerce\Transfer\Models\TransferDetail;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use function CraftCms\Cms\currentUserElement;
use function CraftCms\Cms\pageTemplate;
use function CraftCms\Cms\t;

readonly class TransfersController
{
    use RespondsWithFlash;

    public function create(): Response
    {
        $user = currentUserElement();
        abort_unless($user !== null, 401);

        $transfer = \Craft::createObject(Transfer::class);

        abort_unless($transfer->canSave($user), 403, 'User not authorized to save this transfer.');

        $transfer->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);
        $success = Drafts::saveElementAsDraft($transfer, $user->id, null, null, false);

        if (!$success) {
            return $this->asModelFailure($transfer, t('Couldn\'t create {type}.', [
                'type' => Transfer::lowerDisplayName(),
            ]), 'transfer');
        }

        $editUrl = $transfer->getCpEditUrl();

        $response = $this->asModelSuccess($transfer, t('{type} created.', [
            'type' => Transfer::displayName(),
        ]), 'transfer', array_filter([
            'cpEditUrl' => request()->isCpRequest() ? $editUrl : null,
        ]));

        if (!request()->expectsJson()) {
            return redirect(\CraftCms\Cms\Support\Url::urlWithParams($editUrl, [
                'fresh' => 1,
            ]));
        }

        return $response;
    }

    public function index(): string
    {
        return pageTemplate('commerce/inventory/transfers/_index', [], TemplateMode::Cp);
    }

    public function markAsPending(Request $request): Response
    {
        $transferId = $request->input('transferId');
        abort_if(!$transferId, 400, 'Missing transferId');

        $transfer = Transfer::findOne($transferId);
        $transfer->transferStatus = TransferStatusType::PENDING;

        if (!Elements::saveElement($transfer)) {
            return $this->asFailure(t('Couldn\'t mark transfer as pending.'));
        }

        return $this->asSuccess(t('Transfer marked as pending.'));
    }

    public function saveSettings(): Response
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
            $uid = (string)\CraftCms\Cms\Support\Str::uuid();
        }

        $configData = [$uid => $fieldLayout->getConfig()];
        $result = ProjectConfig::set(Transfers::CONFIG_FIELDLAYOUT_KEY, $configData, force: true);

        if (!$result) {
            return $this->asFailure(t('Couldn\'t save transfer fields.'));
        }

        return $this->asSuccess(t('Transfer fields saved.', category: 'commerce'));
    }

    public function receiveTransfer(Request $request): Response
    {
        $details = $request->input('details', []);
        $transferId = $request->input('transferId');
        abort_if(!$transferId, 400, 'Missing transferId');

        /** @var Transfer $transfer */
        $transfer = Transfer::find()->id($transferId)->one();

        $inventoryMovementCollection = new InventoryMovementCollection();
        $inventoryUpdateCollection = new UpdateInventoryLevelCollection();

        $transferDetails = $transfer->getDetails();

        foreach ($transferDetails as $detail) {
            if ($acceptedAmount = $details[$detail->uid]['accept'] ?? null) {
                // Update the total accepted
                $detail->quantityAccepted += $acceptedAmount;

                $inventoryAcceptedMovement = new InventoryTransferMovement();
                $inventoryAcceptedMovement->quantity = (int)$acceptedAmount;
                $inventoryAcceptedMovement->transferId = $transfer->id;
                $inventoryAcceptedMovement->setInventoryItem($detail->getInventoryItem());
                $inventoryAcceptedMovement->toInventoryLocation = $transfer->getDestinationLocation();
                $inventoryAcceptedMovement->fromInventoryLocation = $transfer->getDestinationLocation(); // we are moving from incoming to available
                $inventoryAcceptedMovement->toInventoryTransactionType = InventoryTransactionType::AVAILABLE;
                $inventoryAcceptedMovement->fromInventoryTransactionType = InventoryTransactionType::INCOMING;

                $inventoryMovementCollection->push($inventoryAcceptedMovement);
            }

            if ($rejectedAmount = $details[$detail->uid]['reject'] ?? null) {
                // Update the total rejected
                $detail->quantityRejected += $rejectedAmount;

                $inventoryRejectedMovement = new UpdateInventoryLevel();
                $inventoryRejectedMovement->quantity = $rejectedAmount * -1;
                $inventoryRejectedMovement->updateAction = InventoryUpdateQuantityType::ADJUST;
                $inventoryRejectedMovement->inventoryItemId = $detail->inventoryItemId;
                $inventoryRejectedMovement->transferId = $transfer->id;
                $inventoryRejectedMovement->setInventoryLocation($transfer->getDestinationLocation());
                $inventoryRejectedMovement->type = InventoryTransactionType::INCOMING->value;

                $inventoryUpdateCollection->push($inventoryRejectedMovement);
            }
        }

        $transfer->setDetails($transferDetails);

        try {
            // Accepted movement
            app(Inventory::class)->executeInventoryMovements($inventoryMovementCollection);
            // Rejected updates
            app(Inventory::class)->executeUpdateInventoryLevels($inventoryUpdateCollection);
            Elements::saveElement($transfer, false);
        } catch (\Throwable $e) {
            Log::error('Failed to save transfer details: ' . $e->getMessage(), ['exception' => $e]);
            return $this->asFailure(t('Failed to receive transfer: {error}', ['error' => $e->getMessage()], category: 'commerce'));
        }

        return $this->asSuccess(t('Updated', category: 'commerce'));
    }

    public function receiveTransferScreen(Request $request): CpScreenResponse
    {
        $transferId = $request->input('transferId');
        abort_if(!$transferId, 400, 'Missing transferId');

        /** @var ?Transfer $transfer */
        $transfer = Transfer::find()->id($transferId)->one();

        if (!$transfer) {
            return new CpScreenResponse()
                ->contentHtml('Cant find transfer');
        }

        $html = Html::beginTag('div', [
            'hx' => [
                'action' => 'commerce/transfers/receive-transfer-modal-content',
            ],
        ]);

        $html .= Html::tag('h2', t('Receive Transfer', category: 'commerce'));

        $html .= Html::hiddenInput('transferId', $transferId);

        // @TODO Add shortcut links to accept-all and reject-all unreceived items in the receive-transfer modal
        // $html .= Html::a(t('Accept All Unreceived', category: 'commerce'), '#');
        // $html .= Html::a(t('Reject All Unreceived', category: 'commerce'), '#');

        $tableRows = '';
        foreach ($transfer->getDetails() as $detail) {
            $deleted = $detail->inventoryItemId == null;
            $key = $detail->uid;
            $purchasable = $detail->getInventoryItem()?->getPurchasable(\craft\helpers\Cp::requestedSite()->id);
            $label = $purchasable ? \craft\helpers\Cp::elementChipHtml($purchasable) : $detail->inventoryItemDescription;
            $tableRows .= Html::beginTag('tr');
            $tableRows .= Html::tag('td', $label);
            $tableRows .= Html::tag('td', (string)$detail->quantityAccepted, ['class' => 'rightalign']);
            $tableRows .= Html::tag('td',
                Html::input('number', 'details[' . $key . '][accept]', '', [
                    'class' => 'text fullwidth',
                    'disabled' => $deleted,
                    'placeholder' => $deleted ? t('"{name}" deleted.', ['name' => $detail->inventoryItemDescription]) : '',
                ])
            );
            $tableRows .= Html::tag('td', (string)$detail->quantityRejected, ['class' => 'rightalign']);
            $tableRows .= Html::tag('td',
                Html::input('number', 'details[' . $key . '][reject]', '', [
                    'class' => 'text fullwidth',
                    'disabled' => $deleted,
                    'placeholder' => $deleted ? t('"{name}" deleted.', ['name' => $detail->inventoryItemDescription]) : '',
                ])
            );
        }

        $html .= Html::tag('table',
            Html::tag('thead',
                Html::tag('tr',
                    Html::tag('th', t('Item', category: 'commerce')) .
                    Html::tag('th', t('Accepted', category: 'commerce'), ['class' => 'rightalign']) .
                    Html::tag('th', t('Accept', category: 'commerce')) .
                    Html::tag('th', t('Rejected', category: 'commerce'), ['class' => 'rightalign']) .
                    Html::tag('th', t('Reject', category: 'commerce'))
                )
            ) .
            $tableRows,
            ['class' => 'data fullwidth']);

        $html .= Html::endTag('div');

        return new CpScreenResponse()
            ->action('commerce/transfers/receive-transfer')
            ->submitButtonLabel(t('Receive', category: 'commerce'))
            ->contentHtml($html);
    }

    public function renderManagement(Request $request): string
    {
        $transferId = $request->input('transferId');
        abort_if(!$transferId, 400, 'Missing transferId');

        /** @var ?Transfer $transfer */
        $transfer = Transfer::find()->id($transferId)->drafts(null)->one();

        // We will only change the transfer if it is a draft.
        if ($transfer && $transfer->isTransferDraft()) {
            $allLocations = app(InventoryLocations::class)->getAllInventoryLocations();
            $defaultFirstLocationId = $allLocations->first()->id;
            $defaultSecondLocationId = $allLocations->skip(1)->first()->id;

            $originLocationId = (int)$request->input('originLocationId', $defaultFirstLocationId);
            $destinationLocationId = (int)$request->input('destinationLocationId', $defaultSecondLocationId);

            $transfer->originLocationId = $originLocationId;
            $transfer->destinationLocationId = $destinationLocationId;

            $details = $request->input('details', []);
            $transfer->setDetails($details);

            $details = $request->input('details', []);

            if ($request->input('removeInventoryItemUid')) {
                $details = array_filter($details, fn($detail) => $detail['uid'] !== $request->input('removeInventoryItemUid'));
            }
            $transfer->setDetails($details);

            $addItem = $request->input('addItem', false);
            $addInventoryItemId = $request->input('newInventoryItemId', null);
            if ($addItem && $addInventoryItemId) {
                $transfer->addDetail(new TransferDetail([
                    'uid' => (string)\CraftCms\Cms\Support\Str::uuid(),
                    'inventoryItemId' => $addInventoryItemId,
                    'quantity' => 1,
                ]));
            }
        }

        return TransferManagementField::renderFieldHtml($transfer);
    }
}
