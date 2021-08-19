<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\errors\MissingComponentException;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Shipping Methods Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingMethodsController extends BaseShippingSettingsController
{
    /**
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionIndex(): Response
    {
        $shippingMethods = Plugin::getInstance()->getShippingMethods()->getAllShippingMethods();
        return $this->renderTemplate('commerce/shipping/shippingmethods/index', compact('shippingMethods'));
    }

    /**
     * @param int|null $id
     * @param ShippingMethod|null $shippingMethod
     * @return Response
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(int $id = null, ShippingMethod $shippingMethod = null): Response
    {
        $variables = compact('id', 'shippingMethod');

        $variables['newMethod'] = false;

        if (!$variables['shippingMethod']) {
            if ($variables['id']) {
                $variables['shippingMethod'] = Plugin::getInstance()->getShippingMethods()->getShippingMethodById($variables['id']);

                if (!$variables['shippingMethod']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingMethod'] = new ShippingMethod();
            }
        }

        if ($variables['shippingMethod']->id) {
            $variables['title'] = $variables['shippingMethod']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping method');
        }

        $variables['shippingRules'] = $variables['shippingMethod']->id !== null
            ? Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($variables['shippingMethod']->id)
            : [];

        return $this->renderTemplate('commerce/shipping/shippingmethods/_edit', $variables);
    }

    /**
     * @throws BadRequestHttpException
     * @throws \yii\base\Exception
     */
    public function actionSave(): void
    {
        $this->requirePostRequest();
        $shippingMethod = new ShippingMethod();

        // Shared attributes
        $shippingMethod->id = Craft::$app->getRequest()->getBodyParam('shippingMethodId');
        $shippingMethod->name = Craft::$app->getRequest()->getBodyParam('name');
        $shippingMethod->handle = Craft::$app->getRequest()->getBodyParam('handle');
        $shippingMethod->enabled = (bool)Craft::$app->getRequest()->getBodyParam('enabled');

        // Save it
        if (Plugin::getInstance()->getShippingMethods()->saveShippingMethod($shippingMethod)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Shipping method saved.'));
            $this->redirectToPostedUrl($shippingMethod);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save shipping method.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['shippingMethod' => $shippingMethod]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::getInstance()->getShippingMethods()->deleteShippingMethodById($id)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Craft::t('commerce', 'Could delete shipping method and it’s rules.'));
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @since 3.2.9
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');
        $status = Craft::$app->getRequest()->getRequiredBodyParam('status');

        if (empty($ids)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t update status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $shippingMethods = ShippingMethodRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var ShippingMethodRecord $discount */
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethod->enabled = ($status == 'enabled');
            $shippingMethod->save();
        }
        $transaction->commit();

        $this->setSuccessFlash(Craft::t('commerce', 'Shipping methods updated.'));
    }

}
