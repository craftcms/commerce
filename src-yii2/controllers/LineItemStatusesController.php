<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\LineItemStatus;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\errors\MissingComponentException;
use craft\helpers\Json;
use Throwable;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class  Line Item Status Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItemStatusesController extends BaseAdminController
{
    public function actionIndex(): Response
    {
        $lineItemStatuses = [];
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        $stores->each(function(Store $store) use (&$lineItemStatuses) {
            $lineItemStatuses[$store->handle] = Plugin::getInstance()->getLineItemStatuses()->getAllLineItemStatuses($store->id);
        });
        $stores = $stores->all();

        return $this->renderTemplate('commerce/settings/lineitemstatuses/index', [
            'lineItemStatuses' => $lineItemStatuses,
            'stores' => $stores,
            'readOnly' => $this->isReadOnlyScreen(),
        ]);
    }

    /**
     * @param int|null $id
     * @param LineItemStatus|null $lineItemStatus
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, LineItemStatus $lineItemStatus = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        if (!$lineItemStatus) {
            if ($id) {
                $lineItemStatus = Plugin::getInstance()->getLineItemStatuses()->getLineItemStatusById($id, $store->id);

                if (!$lineItemStatus) {
                    throw new HttpException(404);
                }
            } else {
                $lineItemStatus = Craft::createObject([
                    'class' => LineItemStatus::class,
                    'storeId' => $store->id,
                ]);
            }
        }

        $statusColors = ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'];
        $nextAvailableColor = null;

        DebugPanel::prependOrAppendModelTab(model: $lineItemStatus, prepend: true);

        if ($lineItemStatus->id) {
            $title = $lineItemStatus->name;
        } else {
            $title = Craft::t('commerce', 'Create a new line item status');

            $availableColors = $statusColors;
            Plugin::getInstance()->getLineItemStatuses()->getAllLineItemStatuses($store->id)->each(function(LineItemStatus $status) use (&$availableColors) {
                $key = array_search($status->color, $availableColors, true);
                if ($key !== false) {
                    unset($availableColors[$key]);
                }
            });

            $nextAvailableColor = !empty($availableColors) ? array_shift($availableColors) : 'green';
        }

        return $this->asCpScreen()
            ->title($title)
            ->crumbs([
                ['label' => Craft::t('commerce', 'Commerce'), 'url' => 'commerce'],
                ['label' => Craft::t('app', 'Settings'), 'url' => 'commerce/settings', 'ariaLabel' => Craft::t('commerce', 'Commerce Settings')],
                ['label' => Craft::t('commerce', 'Line Item Statuses'), 'url' => 'commerce/settings/lineitemstatuses'],
            ])
            ->selectedSubnavItem('settings')
            ->action('commerce/line-item-statuses/save')
            ->redirectUrl('commerce/settings/lineitemstatuses')
            ->contentTemplate('commerce/settings/lineitemstatuses/_edit', [
                'lineItemStatus' => $lineItemStatus,
                'statusColors' => $statusColors,
                'nextAvailableColor' => $nextAvailableColor,
                'readOnly' => $this->isReadOnlyScreen(),
            ]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws ErrorException
     * @throws Exception
     * @throws MissingComponentException
     */
    public function actionSave(): void
    {
        $this->requirePostRequest();

        $id = $this->request->getBodyParam('id');
        $lineItemStatus = $id ? Plugin::getInstance()->getLineItemStatuses()->getLineItemStatusById($id, $this->request->getBodyParam('storeId')) : false;

        if (!$lineItemStatus) {
            $lineItemStatus = new LineItemStatus();
        }

        $lineItemStatus->storeId = $this->request->getBodyParam('storeId');
        $lineItemStatus->name = $this->request->getBodyParam('name');
        $lineItemStatus->handle = $this->request->getBodyParam('handle');
        $lineItemStatus->color = $this->request->getBodyParam('color');
        $lineItemStatus->default = (bool)$this->request->getBodyParam('default');

        // Save it
        if (Plugin::getInstance()->getLineItemStatuses()->saveLineItemStatus($lineItemStatus)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Order status saved.'));
            $this->redirectToPostedUrl($lineItemStatus);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save line item status.'));
        }

        Craft::$app->getUrlManager()->setRouteParams(compact('lineItemStatus'));
    }

    /**
     * @throws BadRequestHttpException
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode($this->request->getRequiredBodyParam('ids'));
        if (!Plugin::getInstance()->getLineItemStatuses()->reorderLineItemStatuses($ids)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t reorder  Line Item Statuses.'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws BadRequestHttpException
     * @throws Throwable
     */
    public function actionArchive(): ?Response
    {
        $this->requireAcceptsJson();

        $lineItemStatusId = $this->request->getRequiredParam('id');

        $storeId = (new Query())->from(Table::LINEITEMSTATUSES)->select(['storeId'])->where(['id' => $lineItemStatusId])->scalar();

        if (!$storeId || !Plugin::getInstance()->getLineItemStatuses()->archiveLineItemStatusById((int)$lineItemStatusId, $storeId)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t archive Line Item Status.'));
        }

        return $this->asSuccess();
    }
}
