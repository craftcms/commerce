<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\LineItemStatus;
use craft\commerce\Plugin;
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
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $lineItemStatuses = Plugin::getInstance()->getLineItemStatuses()->getAllLineItemStatuses();

        return $this->renderTemplate('commerce/settings/lineitemstatuses/index', compact('lineItemStatuses'));
    }

    /**
     * @param int|null $id
     * @param LineItemStatus|null $lineItemStatus
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, LineItemStatus $lineItemStatus = null): Response
    {
        $variables = compact('id', 'lineItemStatus');

        if (!$variables['lineItemStatus']) {
            if ($variables['id']) {
                $variables['lineItemStatus'] = Plugin::getInstance()->getLineItemStatuses()->getLineItemStatusById($variables['id']);

                if (!$variables['lineItemStatus']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['lineItemStatus'] = new LineItemStatus();
            }
        }

        if ($variables['lineItemStatus']->id) {
            $variables['title'] = $variables['lineItemStatus']->name;
        } else {
            $variables['title'] = Plugin::t('Create a new line item status');
        }

        return $this->renderTemplate('commerce/settings/lineitemstatuses/_edit', $variables);
    }

    /**
     * @throws BadRequestHttpException
     * @throws ErrorException
     * @throws Exception
     * @throws MissingComponentException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $id = Craft::$app->getRequest()->getBodyParam('id');
        $lineItemStatus = Plugin::getInstance()->getLineItemStatuses()->getLineItemStatusById($id);

        if (!$lineItemStatus) {
            $lineItemStatus = new LineItemStatus();
        }

        $lineItemStatus->name = Craft::$app->getRequest()->getBodyParam('name');
        $lineItemStatus->handle = Craft::$app->getRequest()->getBodyParam('handle');
        $lineItemStatus->color = Craft::$app->getRequest()->getBodyParam('color');
        $lineItemStatus->default = (bool)Craft::$app->getRequest()->getBodyParam('default');

        // Save it
        if (Plugin::getInstance()->getLineItemStatuses()->saveLineItemStatus($lineItemStatus)) {
            Craft::$app->getSession()->setNotice(Plugin::t('Order status saved.'));
            $this->redirectToPostedUrl($lineItemStatus);
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save line item status.'));
        }

        Craft::$app->getUrlManager()->setRouteParams(compact('lineItemStatus'));
    }

    /**
     * @return Response
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

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        if ($success = Plugin::getInstance()->getLineItemStatuses()->reorderLineItemStatuses($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Plugin::t('Couldn’t reorder  Line Item Statuses.')]);
    }

    /**
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws Throwable
     */
    public function actionArchive()
    {
        $this->requireAcceptsJson();

        $lineItemStatusId = Craft::$app->getRequest()->getRequiredParam('id');

        if (Plugin::getInstance()->getLineItemStatuses()->archiveLineItemStatusById((int)$lineItemStatusId)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['error' => Plugin::t('Couldn’t archive Line Item Status.')]);
    }
}
