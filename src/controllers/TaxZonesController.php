<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\Plugin;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Tax Zone Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxZonesController extends BaseTaxSettingsController
{
    public function actionIndex(): Response
    {
        $taxZones = Plugin::getInstance()->getTaxZones()->getAllTaxZones();
        return $this->renderTemplate('commerce/tax/taxzones/index', compact('taxZones'));
    }

    /**
     * @param int|null $id
     * @param TaxAddressZone|null $taxZone
     * @throws HttpException
     */
    public function actionEdit(int $id = null, TaxAddressZone $taxZone = null): Response
    {
        $variables = compact('id', 'taxZone');

        if (!$variables['taxZone']) {
            if ($variables['id']) {
                $variables['taxZone'] = Plugin::getInstance()->getTaxZones()->getTaxZoneById($variables['id']);

                if (!$variables['taxZone']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['taxZone'] = new TaxAddressZone();
            }
        }

        if ($variables['taxZone']->id) {
            $variables['title'] = $variables['taxZone']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a tax zone');
        }

        $variables['countries'] = Plugin::getInstance()->getCountries()->getAllEnabledCountriesAsList();
        $variables['states'] = Plugin::getInstance()->getStates()->getAllEnabledStatesAsList();

        return $this->renderTemplate('commerce/tax/taxzones/_edit', $variables);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $taxZone = new TaxAddressZone();

        // Shared attributes
        $taxZone->id = Craft::$app->getRequest()->getBodyParam('taxZoneId');
        $taxZone->name = Craft::$app->getRequest()->getBodyParam('name');
        $taxZone->description = Craft::$app->getRequest()->getBodyParam('description');
        $taxZone->isCountryBased = Craft::$app->getRequest()->getBodyParam('isCountryBased');
        $taxZone->zipCodeConditionFormula = Craft::$app->getRequest()->getBodyParam('zipCodeConditionFormula');
        $taxZone->default = (bool)Craft::$app->getRequest()->getBodyParam('default');
        $countryIds = Craft::$app->getRequest()->getBodyParam('countries') ?: [];
        $stateIds = Craft::$app->getRequest()->getBodyParam('states') ?: [];

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

        if ($taxZone->validate() && Plugin::getInstance()->getTaxZones()->saveTaxZone($taxZone)) {
            return $this->asModelSuccess(
                $taxZone,
                Craft::t('commerce', 'Tax zone saved.'),
                'taxZone',
                data: [
                    'id' => $taxZone->id,
                    'name' => $taxZone->name,
                ]
            );
        }

        return $this->asModelFailure(
            $taxZone,
            Craft::t('commerce', 'Couldn’t save tax zone.'),
            'taxZone'
        );
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getTaxZones()->deleteTaxZoneById($id);
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

        $zipCodeFormula = (string)Craft::$app->getRequest()->getRequiredBodyParam('zipCodeConditionFormula');
        $testZipCode = (string)Craft::$app->getRequest()->getRequiredBodyParam('testZipCode');

        $params = ['zipCode' => $testZipCode];
        if (Plugin::getInstance()->getFormulas()->evaluateCondition($zipCodeFormula, $params)) {
            return $this->asSuccess();
        }

        return $this->asFailure('failed');
    }
}
