<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\commerce\collections\InventoryMovementCollection;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\elements\Transfer;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\fieldlayoutelements\TransferManagementField;
use craft\commerce\models\inventory\InventoryTransferMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\TransferDetail;
use craft\commerce\Plugin;
use craft\commerce\services\Transfers;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class Transfers Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class TransfersController extends BaseStoreManagementController
{

    /**
     * @return void
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function init(): void
    {
        parent::init();

        $this->requirePermission('commerce-manageInventoryTransfers');
    }

    /**
     * @return Response
     */
    public function actionCreate(): Response
    {
        $user = static::currentUser();
        $transfer = Craft::createObject(Transfer::class);

        if (!Craft::$app->getElements()->canSave($transfer, $user)) {
            throw new ForbiddenHttpException('User not authorized to save this transfer.');
        }

        $transfer->setScenario(Element::SCENARIO_ESSENTIALS);
        $success = Craft::$app->getDrafts()->saveElementAsDraft($transfer, Craft::$app->getUser()->getId(), null, null, false);

        if (!$success) {
            return $this->asModelFailure($transfer, Craft::t('app', 'Couldn’t create {type}.', [
                'type' => Transfer::lowerDisplayName(),
            ]), 'transfer');
        }

        $editUrl = $transfer->getCpEditUrl();

        $response = $this->asModelSuccess($transfer, Craft::t('app', '{type} created.', [
            'type' => Transfer::displayName(),
        ]), 'transfer', array_filter([
            'cpEditUrl' => $this->request->isCpRequest ? $editUrl : null,
        ]));

        if (!$this->request->getAcceptsJson()) {
            $response->redirect(UrlHelper::urlWithParams($editUrl, [
                'fresh' => 1,
            ]));
        }

        return $response;
    }

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('commerce/inventory/transfers/_index');
    }

    /**
     * @return Response
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\MethodNotAllowedHttpException
     */
    public function actionMarkAsPending(): Response
    {
        $this->requirePostRequest();

        $transferId = $this->request->getRequiredBodyParam('transferId');
        $transfer = Transfer::findOne($transferId);
        $transfer->transferStatus = TransferStatusType::PENDING;

        if (!Craft::$app->getElements()->saveElement($transfer)) {
            return $this->asFailure(Craft::t('app', 'Couldn’t mark transfer as pending.'));
        }

        return $this->asSuccess(Craft::t('app', 'Transfer marked as pending.'));
    }

    /**
     * @param array $variables
     * @return Response
     */
    public function actionEditSettings(array $variables = []): Response
    {
        $fieldLayout = Plugin::getInstance()->getTransfers()->getFieldLayout();

        $variables['fieldLayout'] = $fieldLayout;
        $variables['title'] = Craft::t('commerce', 'Transfer Settings');

        return $this->renderTemplate('commerce/settings/transfers/settings', $variables);
    }

    /**
     * @return Response
     */
    public function actionSaveSettings(): Response
    {
        $this->requirePostRequest();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();

        $fieldLayout->reservedFieldHandles = [
            'originLocationId',
            'originLocation',
            'destinationLocationId',
            'destinationLocation',
        ];

        $fieldLayout->type = Transfer::class;

        if (!$fieldLayout->validate()) {
            Craft::info('Field layout not saved due to validation error.', __METHOD__);

            Craft::$app->getUrlManager()->setRouteParams([
                'variables' => [
                    'fieldLayout' => $fieldLayout,
                ],
            ]);

            return $this->asFailure(Craft::t('commerce', 'Couldn’t save transfer fields.'));
        }

        if ($currentTransfersFieldLayout = Craft::$app->getProjectConfig()->get(Transfers::CONFIG_FIELDLAYOUT_KEY)) {
            $uid = array_key_first($currentTransfersFieldLayout);
        } else {
            $uid = StringHelper::UUID();
        }

        $configData = [$uid => $fieldLayout->getConfig()];
        $result = Craft::$app->getProjectConfig()->set(Transfers::CONFIG_FIELDLAYOUT_KEY, $configData, force: true);

        if (!$result) {
            return $this->asFailure(Craft::t('app', 'Couldn’t save transfer fields.'));
        }

        return $this->asSuccess(Craft::t('commerce', 'Transfer fields saved.'));
    }

    /**
     * @return Response
     */
    public function actionReceiveTransfer(): Response
    {
        $details = $this->request->getParam('details', []);
        $transferId = $this->request->getRequiredParam('transferId');
        /** @var Transfer $transfer */
        $transfer = Transfer::find()->id($transferId)->one();

        $inventoryMovementCollection = new InventoryMovementCollection();
        $inventoryUpdateCollection = new UpdateInventoryLevelCollection();

        $transferDetails = $transfer->getDetails();

        foreach ($transferDetails as $detail) {
            if ($acceptedAmount = $details[$detail->uid]['accept']) {
                // Update the total accepted
                $detail->quantityAccepted += $acceptedAmount;

                $inventoryAcceptedMovement = new InventoryTransferMovement();
                $inventoryAcceptedMovement->quantity = $acceptedAmount;
                $inventoryAcceptedMovement->transferId = $transfer->id;
                $inventoryAcceptedMovement->inventoryItem = $detail->getInventoryItem();
                $inventoryAcceptedMovement->toInventoryLocation = $transfer->getDestinationLocation();
                $inventoryAcceptedMovement->fromInventoryLocation = $transfer->getDestinationLocation(); // we are moving from incoming to available
                $inventoryAcceptedMovement->toInventoryTransactionType = InventoryTransactionType::AVAILABLE;
                $inventoryAcceptedMovement->fromInventoryTransactionType = InventoryTransactionType::INCOMING;

                $inventoryMovementCollection->push($inventoryAcceptedMovement);
            }

            if ($rejectedAmount = $details[$detail->uid]['reject']) {
                // Update the total rejected
                $detail->quantityRejected += $rejectedAmount;

                $inventoryRejectedMovement = new UpdateInventoryLevel();
                $inventoryRejectedMovement->quantity = $rejectedAmount;
                $inventoryRejectedMovement->updateAction = InventoryUpdateQuantityType::ADJUST;
                $inventoryRejectedMovement->inventoryItem = $detail->getInventoryItem();
                $inventoryRejectedMovement->transferId = $transfer->id;
                $inventoryRejectedMovement->inventoryLocation = $transfer->getDestinationLocation();
                $inventoryRejectedMovement->type = InventoryTransactionType::INCOMING->value;

                $inventoryUpdateCollection->push($inventoryRejectedMovement);
            }
        }

        $transfer->setDetails($transferDetails);

        try {
            Plugin::getInstance()->getInventory()->executeInventoryMovements($inventoryMovementCollection);
            Plugin::getInstance()->getInventory()->executeUpdateInventoryLevels($inventoryUpdateCollection);
            Craft::$app->getElements()->saveElement($transfer, false);
        } catch (\Throwable $e) {
            Craft::error('Failed to save transfer details: ' . $e->getMessage(), __METHOD__);
            return $this->asFailure(Craft::t('commerce', 'Failed to receive transfer: {error}', ['error' => $e->getMessage()]));
        }

        return $this->asSuccess(Craft::t('commerce', 'Updated'));
    }

    /**
     * @return Response
     */
    public function actionReceiveTransferModal(): Response
    {
        $transferId = $this->request->getRequiredParam('transferId');
        /** @var ?Transfer $transfer */
        $transfer = Transfer::find()->id($transferId)->one();

        if (!$transfer) {
            return $this->asCpModal()
                ->contentHtml('Cant find transfer');
        }

        $html = Html::beginTag('div', [
            'hx' => [
                'action' => 'commerce/transfers/receive-transfer-modal-content',
            ],
        ]);

        $html .= Html::tag('h2', Craft::t('commerce', 'Receive Transfer'));

        $html .= Html::hiddenInput('transferId', $transferId);

        // TODO: Allow shortcut to receive and reject all
        // $html .= Html::a(Craft::t('commerce', 'Accept All Unreceived'), '#');
        // $html .= Html::a(Craft::t('commerce', 'Reject All Unreceived'), '#');

        $tableRows = '';
        foreach ($transfer->getDetails() as $detail) {
            $key = $detail->uid;
            $purchasable = $detail->getInventoryItem()?->getPurchasable();
            $label = $purchasable ? \craft\helpers\Cp::elementChipHtml($purchasable) : $detail->inventoryItemDescription;
            $tableRows .= Html::beginTag('tr');
            $tableRows .= Html::tag('td', $label);
            $tableRows .= Html::tag('td', (string)$detail->quantityAccepted);
            $tableRows .= Html::tag('td',
                Html::input('number', 'details[' . $key . '][accept]', '', ['class' => 'text fullwidth'])
            );
            $tableRows .= Html::tag('td', (string)$detail->quantityRejected);
            $tableRows .= Html::tag('td',
                Html::input('number', 'details[' . $key . '][reject]', '', ['class' => 'text fullwidth'])
            );
        }

        $html .= Html::tag('table',
            Html::tag('thead',
                Html::tag('tr',
                    Html::tag('th', Craft::t('commerce', 'Item')) .
                    Html::tag('th', Craft::t('commerce', 'Accepted')) .
                    Html::tag('th', Craft::t('commerce', 'Accept')) .
                    Html::tag('th', Craft::t('commerce', 'Rejected')) .
                    Html::tag('th', Craft::t('commerce', 'Reject'))
                )
            ) .
            $tableRows,
            ['class' => 'data fullwidth']);

        $html .= Html::endTag('div');

        return $this->asCpModal()
            ->action('commerce/transfers/receive-transfer')
            ->submitButtonLabel(Craft::t('commerce', 'Update'))
//            ->additionalButtonsHtml($acceptAllUnreceivedButton)
            ->contentHtml($html);
    }

    /**
     * @return string
     */
    public function actionReceiveTransferModalContent(): string
    {
        return Html::tag('div', 'fresh content');
    }

    public function actionRenderManagement(): string
    {
        $transferId = $this->request->getRequiredParam('transferId');

        /** @var ?Transfer $transfer */
        $transfer = Transfer::find()->id($transferId)->drafts(null)->one();

        // We will only change the transfer if it is a draft.
        if ($transfer && $transfer->isTransferDraft()) {
            $allLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();
            $defaultFirstLocationId = $allLocations->first()->id;
            $defaultSecondLocationId = $allLocations->skip(1)->first()->id;

            $originLocationId = (int)$this->request->getParam('originLocationId', $defaultFirstLocationId);
            $destinationLocationId = (int)$this->request->getParam('destinationLocationId', $defaultSecondLocationId);

            $transfer->originLocationId = $originLocationId;
            $transfer->destinationLocationId = $destinationLocationId;

            $details = $this->request->getParam('details', []);
            $transfer->setDetails($details);

            $details = $this->request->getParam('details', []);

            if ($this->request->getParam('removeInventoryItemUid')) {
                $details = array_filter($details, function($detail) {
                    return $detail['uid'] !== $this->request->getParam('removeInventoryItemUid');
                });
            }
            $transfer->setDetails($details);

            $addItem = $this->request->getParam('addItem', false);
            $addInventoryItemId = $this->request->getParam('newInventoryItemId', null);
            if ($addItem && $addInventoryItemId) {
                $transfer->addDetail(new TransferDetail([
                    'uid' => StringHelper::UUID(),
                    'inventoryItemId' => $addInventoryItemId,
                    'quantity' => 1,
                ]));
            }
        }

        return TransferManagementField::renderFieldHtml($transfer);
    }
}
