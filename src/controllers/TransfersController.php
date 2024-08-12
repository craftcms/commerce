<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Transfer;
use craft\commerce\enums\TransferStatusType;
use craft\commerce\fieldlayoutelements\TransferManagementField;
use craft\commerce\models\TransferDetail;
use craft\commerce\Plugin;
use craft\commerce\services\Transfers;
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
        return $this->asSuccess(Craft::t('commerce', 'TODO'));
    }

    public function actionItemsTable(): Response
    {

    }

    /**
     * @return Response
     */
    public function actionReceiveTransferModal(): Response
    {
        $params = [
        ];

        return $this->asCpModal()
            ->action('commerce/transfers/receive-transfer')
            ->contentTemplate('commerce/transfers/_receiveTransferModal', $params);
    }

    public function actionRenderManagement(): string
    {
        $transferId = $this->request->getRequiredParam('transferId');
        $transfer = Transfer::find()->id($transferId)->drafts()->one();

        $details = $this->request->getRequiredParam('details');

        $transfer->setDetails($details);

        if($addRow = $this->request->getRequiredParam('addRow'))
        {
            $detail = new TransferDetail();
            $transfer->addDetails($detail);
        }

        return TransferManagementField::renderFieldHtml($transfer);
    }
}
