<?php

namespace craft\commerce\controllers;

use craft\commerce\models\ShippingZone;
use craft\helpers\ArrayHelper;
use Craft;

/**
 * Class Shipping Zones Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class ShippingZonesController extends BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $shippingZones = Plugin::getInstance()->getShippingZones()->getAllShippingZones();
        $this->renderTemplate('commerce/settings/shippingzones/index',
            compact('shippingZones'));
    }

    /**
     * Create/Edit ShippingZone
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['shippingZone'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['shippingZone'] = Plugin::getInstance()->getShippingZones()->getShippingZoneById($id);

                if (!$variables['shippingZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingZone'] = new ShippingZone();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['shippingZone']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a shipping zone');
        }

        $countries = Plugin::getInstance()->getCountries()->getAllCountries();
        $states = Plugin::getInstance()->getStates()->getAllStates();

        $variables['countries'] = ArrayHelper::map($countries, 'id', 'name');
        $variables['states'] = ArrayHelper::map($states, 'id', 'name');

        $this->renderTemplate('commerce/settings/shippingzones/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $shippingZone = new ShippingZone();

        // Shared attributes
        $shippingZone->id = Craft::$app->getRequest()->getParam('shippingZoneId');
        $shippingZone->name = Craft::$app->getRequest()->getParam('name');
        $shippingZone->description = Craft::$app->getRequest()->getParam('description');
        $shippingZone->countryBased = Craft::$app->getRequest()->getParam('countryBased');
        $shippingZone->countryBased = Craft::$app->getRequest()->getParam('countryBased');
        $countryIds = Craft::$app->getRequest()->getParam('countries') ?: [];
        $stateIds = Craft::$app->getRequest()->getParam('states') ?: [];

        $countries = [];
        foreach ($countryIds as $id) {
            if ($country = Plugin::getInstance()->getCountries()->getCountryById($id)) {
                $countries[] = $country;
            }
        }
        $shippingZone->setCountries($countries);

        $states = [];
        foreach ($stateIds as $id) {
            if ($state = Plugin::getInstance()->getStates()->getStateById($id)) {
                $states[] = $state;
            }
        }
        $shippingZone->setStates($states);

        // Save it
        if (Plugin::getInstance()->getShippingZones()->saveShippingZone($shippingZone, $shippingZone->getCountryIds(), $shippingZone->getStateIds())) {
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
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getShippingZones()->deleteShippingZoneById($id);
        $this->asJson(['success' => true]);
    }

}
