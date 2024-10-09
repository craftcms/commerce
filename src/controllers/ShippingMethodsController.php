<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
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
     * @throws InvalidConfigException
     */
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $shippingMethods = Plugin::getInstance()->getShippingMethods()->getAllShippingMethods($store->id);
        return $this->renderTemplate('commerce/store-management/shipping/shippingmethods/index', compact('shippingMethods', 'store'));
    }

    /**
     * @param int|null $id
     * @param ShippingMethod|null $shippingMethod
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, ShippingMethod $shippingMethod = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $variables = compact('id', 'shippingMethod');

        $variables['newMethod'] = false;

        if (!$variables['shippingMethod']) {
            if ($variables['id']) {
                $variables['shippingMethod'] = Plugin::getInstance()->getShippingMethods()->getShippingMethodById($variables['id'], $store->id);

                if (!$variables['shippingMethod']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingMethod'] = Craft::createObject([
                    'class' => ShippingMethod::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        if ($variables['shippingMethod']->id) {
            $variables['title'] = $variables['shippingMethod']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping method');
        }

        $variables['storeHandle'] = $store->handle;

        DebugPanel::prependOrAppendModelTab(model: $variables['shippingMethod'], prepend: true);

        $variables['shippingRules'] = $variables['shippingMethod']->id !== null
            ? Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($variables['shippingMethod']->id)
            : [];

        return $this->renderTemplate('commerce/store-management/shipping/shippingmethods/_edit', $variables);
    }

    /**
     * @throws BadRequestHttpException
     * @throws \yii\base\Exception
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $shippingMethod = new ShippingMethod();

        // Shared attributes
        $shippingMethod->id = $this->request->getBodyParam('shippingMethodId');
        $shippingMethod->name = $this->request->getBodyParam('name');
        $shippingMethod->handle = $this->request->getBodyParam('handle');
        $shippingMethod->storeId = $this->request->getBodyParam('storeId');
        $shippingMethod->setOrderCondition($this->request->getBodyParam('orderCondition'));
        $shippingMethod->enabled = (bool)$this->request->getBodyParam('enabled');

        // Save it
        if (!Plugin::getInstance()->getShippingMethods()->saveShippingMethod($shippingMethod)) {
            return $this->asModelFailure($shippingMethod, Craft::t('commerce', 'Couldn’t save shipping method.'), 'shippingMethod');
        }

        return $this->asModelSuccess($shippingMethod, Craft::t('commerce', 'Shipping method saved.'), 'shippingMethod');
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();

        $id = $this->request->getBodyParam('id');
        $ids = $this->request->getBodyParam('ids');

        if ((!$id && empty($ids)) || ($id && !empty($ids))) {
            throw new BadRequestHttpException('id or ids must be specified.');
        }

        if ($id) {
            // If it is just the one id we know it has come from an ajax request on the table
            $this->requireAcceptsJson();
            $ids = [$id];
        }

        $failedIds = [];
        foreach ($ids as $id) {
            if (!Plugin::getInstance()->getShippingMethods()->deleteShippingMethodById($id)) {
                $failedIds[] = $id;
            }
        }

        if (!empty($failedIds)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete {count, number} shipping {count, plural, one{method} other{methods}} and rules.', [
                'count' => count($failedIds),
            ]));
        }

        return $this->asSuccess(Craft::t('commerce', 'Shipping methods and rules deleted.'));
    }

    /**
     * @throws BadRequestHttpException
     * @throws Exception
     * @since 3.2.9
     */
    public function actionUpdateStatus(): void
    {
        $this->requirePostRequest();
        $ids = $this->request->getRequiredBodyParam('ids');
        $status = $this->request->getRequiredBodyParam('status');

        if (empty($ids)) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t update status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $shippingMethods = ShippingMethodRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var ShippingMethodRecord $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethod->enabled = ($status == 'enabled');
            $shippingMethod->save();
        }
        $transaction->commit();

        $this->setSuccessFlash(Craft::t('commerce', 'Shipping methods updated.'));
    }
}
