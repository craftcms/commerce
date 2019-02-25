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
use craft\helpers\ArrayHelper;
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
    // Public Methods
    // =========================================================================

    /**
     * @throws HttpException
     */
    public function actionIndex(): Response
    {
        $shippingZones = Plugin::getInstance()->getShippingZones()->getAllShippingZones();
        return $this->renderTemplate('commerce/shipping/shippingzones/index', [
            'shippingZones' => $shippingZones
        ]);
    }

    /**
     * @param int|null $id
     * @param ShippingAddressZone $shippingZone
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, ShippingAddressZone $shippingZone = null): Response
    {
        $variables = [
            'id' => $id,
            'shippingZone' => $shippingZone
        ];

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

        $countries = Plugin::getInstance()->getCountries()->getAllCountries();
        $states = Plugin::getInstance()->getStates()->getAllStates();

        $variables['countries'] = ArrayHelper::map($countries, 'id', 'name');
        $variables['states'] = ArrayHelper::map($states, 'id', 'name');

        return $this->renderTemplate('commerce/shipping/shippingzones/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $shippingZone = new ShippingAddressZone();

        // Shared attributes
        $shippingZone->id = Craft::$app->getRequest()->getBodyParam('shippingZoneId');
        $shippingZone->name = Craft::$app->getRequest()->getBodyParam('name');
        $shippingZone->description = Craft::$app->getRequest()->getBodyParam('description');
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
            $state = $id ? Plugin::getInstance()->getStates()->getStateById($id) : null;
            if ($state) {
                $states[] = $state;
            }
        }
        $shippingZone->setStates($states);

        // Save it
        if ($shippingZone->validate() && Plugin::getInstance()->getShippingZones()->saveShippingZone($shippingZone)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson([
                    'success' => true,
                    'id' => $shippingZone->id,
                    'name' => $shippingZone->name,
                ]);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Shipping zone saved.'));
                $this->redirectToPostedUrl($shippingZone);
            }
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson([
                    'errors' => $shippingZone->getErrors()
                ]);
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save shipping zone.'));
            }
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['shippingZone' => $shippingZone]);
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
}
