<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\Plugin;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Shipping Zones Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingZonesController extends BaseShippingSettingsController
{
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $shippingZones = Plugin::getInstance()->getShippingZones()->getAllShippingZones();
        return $this->renderTemplate('commerce/shipping/shippingzones/index', [
            'shippingZones' => $shippingZones,
        ]);
    }

    /**
     * @param int|null $id
     * @param ShippingAddressZone|null $shippingZone
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, ShippingAddressZone $shippingZone = null): Response
    {
        $variables = compact('id', 'shippingZone');

        if (!$variables['shippingZone']) {
            if ($variables['id']) {
                $variables['shippingZone'] = Plugin::getInstance()->getShippingZones()->getShippingZoneById($variables['id']);

                if (!$variables['shippingZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingZone'] = new ShippingAddressZone();
            }
        }

        if ($variables['shippingZone']->id) {
            $variables['title'] = $variables['shippingZone']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a shipping zone');
        }

        $variables['countries'] = Plugin::getInstance()->getCountries()->getAllEnabledCountriesAsList();
        $variables['states'] = Plugin::getInstance()->getStates()->getAllEnabledStatesAsList();

        return $this->renderTemplate('commerce/shipping/shippingzones/_edit', $variables);
    }

    /**
     * @return Response|null
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $shippingZone = new ShippingAddressZone();

        // Shared attributes
        $shippingZone->id = Craft::$app->getRequest()->getBodyParam('shippingZoneId');
        $shippingZone->name = Craft::$app->getRequest()->getBodyParam('name');
        $shippingZone->description = Craft::$app->getRequest()->getBodyParam('description');
        $shippingZone->zipCodeConditionFormula = Craft::$app->getRequest()->getBodyParam('zipCodeConditionFormula');
        $shippingZone->isCountryBased = Craft::$app->getRequest()->getBodyParam('isCountryBased');
        $countryIds = Craft::$app->getRequest()->getBodyParam('countries') ?: [];
        $stateIds = Craft::$app->getRequest()->getBodyParam('states') ?: [];

        $countries = [];
        foreach ($countryIds as $id) {
            $country = $id ? Plugin::getInstance()->getCountries()->getCountryById($id) : null;
            if ($country) {
                $countries[] = $country;
            }
        }
        $shippingZone->setCountries($countries);

        $states = [];
        foreach ($stateIds as $id) {
            $state = $id ? Plugin::getInstance()->getStates()->getAdministrativeAreaById($id) : null;
            if ($state) {
                $states[] = $state;
            }
        }
        $shippingZone->setStates($states);

        // Save it
        if (!$shippingZone->validate() || !Plugin::getInstance()->getShippingZones()->saveShippingZone($shippingZone)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'errors' => $shippingZone->getErrors(),
                ]);
            }

            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save shipping zone.'));
            Craft::$app->getUrlManager()->setRouteParams(['shippingZone' => $shippingZone]);

            return null;
        }

        // Success
        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'id' => $shippingZone->id,
                'name' => $shippingZone->name,
            ]);
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Shipping zone saved.'));
        $this->redirectToPostedUrl($shippingZone);

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['shippingZone' => $shippingZone]);

        return null;
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::getInstance()->getShippingZones()->deleteShippingZoneById($id)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Craft::t('commerce', 'Could not delete shipping zone'));
    }

    /**
     * @return Response
     * @throws BadRequestHttpException
     * @throws LoaderError
     * @throws SyntaxError
     * @since 2.2
     */
    public function actionTestZip(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $zipCodeFormula = (string)Craft::$app->getRequest()->getRequiredBodyParam('zipCodeConditionFormula');
        $testZipCode = (string)Craft::$app->getRequest()->getRequiredBodyParam('testZipCode');

        $params = ['zipCode' => $testZipCode];
        if (Plugin::getInstance()->getFormulas()->evaluateCondition($zipCodeFormula, $params)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson('failed');
    }
}
