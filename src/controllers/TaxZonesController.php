<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\TaxZone;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Zone Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class TaxZonesController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $taxZones = Plugin::getInstance()->getTaxZones()->getAllTaxZones();
        return $this->renderTemplate('commerce/settings/taxzones/index', compact('taxZones'));
    }

    /**
     * @param int|null     $id
     * @param TaxZone|null $taxZone
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, TaxZone $taxZone = null): Response
    {
        $variables = [
            'id' => $id,
            'taxZone' => $taxZone
        ];

        if (!$variables['taxZone']) {
            if ($variables['id']) {
                $variables['taxZone'] = Plugin::getInstance()->getTaxZones()->getTaxZoneById($variables['id']);

                if (!$variables['taxZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxZone'] = new TaxZone();
            }
        }

        if ($variables['taxZone']->id) {
            $variables['title'] = $variables['taxZone']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a tax zone');
        }

        $countries = Plugin::getInstance()->getCountries()->getAllCountries();
        $states = Plugin::getInstance()->getStates()->getAllStates();

        $variables['countries'] = ArrayHelper::map($countries, 'id', 'name');
        $variables['states'] = ArrayHelper::map($states, 'id', 'name');

        return $this->renderTemplate('commerce/settings/taxzones/_edit', $variables);
    }

    /**
     * @return null|Response
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $taxZone = new TaxZone();

        // Shared attributes
        $taxZone->id = Craft::$app->getRequest()->getParam('taxZoneId');
        $taxZone->name = Craft::$app->getRequest()->getParam('name');
        $taxZone->description = Craft::$app->getRequest()->getParam('description');
        $taxZone->countryBased = Craft::$app->getRequest()->getParam('countryBased');
        $taxZone->default = Craft::$app->getRequest()->getParam('default');
        $countryIds = Craft::$app->getRequest()->getParam('countries') ?: [];
        $stateIds = Craft::$app->getRequest()->getParam('states') ?: [];

        $countries = [];
        foreach ($countryIds as $id) {
            $country = $id ? Plugin::getInstance()->getCountries()->getCountryById($id) : null;
            if ($country) {
                $countries[] = $country;
            }
        }
        $taxZone->setCountries($countries);

        $states = [];
        foreach ($stateIds as $id) {
            $state = $id ? Plugin::getInstance()->getStates()->getStateById($id) : null;
            if ($state) {
                $states[] = $state;
            }
        }
        $taxZone->setStates($states);

        // TODO: refactor to remove ids params which are not needed.
        if (Plugin::getInstance()->getTaxZones()->saveTaxZone($taxZone, $taxZone->getCountryIds(), $taxZone->getStateIds())) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'id' => $taxZone->id,
                    'name' => $taxZone->name,
                ]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Tax zone saved.'));
            $this->redirectToPostedUrl($taxZone);
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'errors' => $taxZone->getErrors()
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save tax zone.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['taxZone' => $taxZone]);

        return null;
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getTaxZones()->deleteTaxZoneById($id);
        return $this->asJson(['success' => true]);
    }
}
