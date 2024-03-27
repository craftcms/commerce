<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\Plugin;
use craft\helpers\Cp;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Shipping Zone Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZonesController extends BaseShippingSettingsController
{
    public function actionIndex(?string $storeHandle = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $shippingZones = Plugin::getInstance()->getShippingZones()->getAllShippingZones($store->id);
        return $this->renderTemplate('commerce/store-management/shipping/shippingzones/index', compact('shippingZones', 'store'));
    }

    /**
     * @param int|null $id
     * @param ShippingAddressZone|null $shippingZone
     * @throws HttpException
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, ShippingAddressZone $shippingZone = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $variables = compact('id', 'shippingZone');

        if (!$variables['shippingZone']) {
            if ($variables['id']) {
                $variables['shippingZone'] = Plugin::getInstance()->getShippingZones()->getShippingZoneById($variables['id'], $store->id);

                if (!$variables['shippingZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingZone'] = Craft::createObject([
                    'class' => ShippingAddressZone::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        if ($variables['shippingZone']->id) {
            $variables['title'] = $variables['shippingZone']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a shipping zone');
        }

        $variables['storeHandle'] = $store->handle;

        $condition = $variables['shippingZone']->getCondition();
        $condition->mainTag = 'div';
        $condition->name = 'condition';
        $condition->id = 'condition';
        $variables['conditionField'] = Cp::fieldHtml($condition->getBuilderHtml(), [
            'label' => Craft::t('app', 'Address Condition'),
        ]);

        DebugPanel::prependOrAppendModelTab(model: $variables['shippingZone'], prepend: true);

        return $this->renderTemplate('commerce/store-management/shipping/shippingzones/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $shippingZone = new ShippingAddressZone();

        // Shared attributes
        $shippingZone->id = $this->request->getBodyParam('shippingZoneId');
        $shippingZone->storeId = $this->request->getBodyParam('storeId');
        $shippingZone->name = $this->request->getBodyParam('name');
        $shippingZone->description = $this->request->getBodyParam('description');
        $shippingZone->setCondition($this->request->getBodyParam('condition'));

        if ($shippingZone->validate() && Plugin::getInstance()->getShippingZones()->saveShippingZone($shippingZone)) {
            return $this->asModelSuccess(
                $shippingZone,
                Craft::t('commerce', 'Shipping zone saved.'),
                'shippingZone',
                data: [
                    'id' => $shippingZone->id,
                    'name' => $shippingZone->name,
                ]
            );
        }

        return $this->asModelFailure(
            $shippingZone,
            Craft::t('commerce', 'Couldn’t save shipping zone.'),
            'shippingZone'
        );
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        if (!Plugin::getInstance()->getShippingZones()->deleteShippingZoneById($id)) {
            return $this->asFailure(Craft::t('commerce', 'Could not delete shipping zone'));
        }

        return $this->asSuccess();
    }

    /**
     * @throws BadRequestHttpException
     * @throws LoaderError
     * @throws SyntaxError
     * @since 2.2
     */
    public function actionTestZip(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $zipCodeFormula = (string)$this->request->getRequiredBodyParam('zipCodeConditionFormula');
        $testZipCode = (string)$this->request->getRequiredBodyParam('testZipCode');

        $params = ['zipCode' => $testZipCode];

        if (!Plugin::getInstance()->getFormulas()->evaluateCondition($zipCodeFormula, $params)) {
            return $this->asFailure('failed');
        }

        return $this->asSuccess();
    }
}
