<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\TaxZone;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;

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
class TaxZones extends BaseAdmin
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $taxZones = Plugin::getInstance()->getTaxZones()->getAllTaxZones();
        $this->renderTemplate('commerce/settings/taxzones/index',
            compact('taxZones'));
    }

    /**
     * Create/Edit TaxZone
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['taxZone'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['taxZone'] = Plugin::getInstance()->getTaxZones()->getTaxZoneById($id);

                if (!$variables['taxZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxZone'] = new TaxZone();
            };
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['taxZone']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a tax zone');
        }

        $countries = Plugin::getInstance()->getCountries()->getAllCountries();
        $states = Plugin::getInstance()->getStates()->getAllStates();

        $variables['countries'] = ArrayHelper::map($countries, 'id', 'name');
        $variables['states'] = ArrayHelper::map($states, 'id', 'name');

        $this->renderTemplate('commerce/settings/taxzones/_edit', $variables);
    }

    /**
     * @throws HttpException
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
        $countryIds = Craft::$app->getRequest()->getParam('countries') ? Craft::$app->getRequest()->getParam('countries') : [];
        $stateIds = Craft::$app->getRequest()->getParam('states') ? Craft::$app->getRequest()->getParam('states') : [];

        $countries = [];
        foreach ($countryIds as $id) {
            if ($country = Plugin::getInstance()->getCountries()->getCountryById($id)) {
                $countries[] = $country;
            }
        }
        $taxZone->setCountries($countries);

        $states = [];
        foreach ($stateIds as $id) {
            if ($state = Plugin::getInstance()->getStates()->getStateById($id)) {
                $states[] = $state;
            }
        }
        $taxZone->setStates($states);

        // TODO: refactor to remove ids params which are not needed.
        if (Plugin::getInstance()->getTaxZones()->saveTaxZone($taxZone, $taxZone->getCountryIds(), $taxZone->getStateIds())) {
            if (Craft::$app->getRequest()->isAjax()) {
                $this->asJson([
                    'success' => true,
                    'id' => $taxZone->id,
                    'name' => $taxZone->name,
                ]);
            } else {
                Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Tax zone saved.'));
                $this->redirectToPostedUrl($taxZone);
            }
        } else {
            if (Craft::$app->getRequest()->isAjax()) {
                $this->asJson([
                    'errors' => $taxZone->getErrors()
                ]);
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save tax zone.'));
            }
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['taxZone' => $taxZone]);
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
        $this->asJson(['success' => true]);
    }

}
