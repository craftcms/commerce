<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\Country;
use craft\commerce\Plugin;
use craft\commerce\records\Country as CountryRecord;
use craft\db\Query;
use craft\errors\MissingComponentException;
use craft\helpers\Json;
use Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Countries Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CountriesController extends BaseStoreSettingsController
{
    /**
     * @throws HttpException
     */
    public function actionIndex(): Response
    {
        $countries = Plugin::getInstance()->getCountries()->getAllCountries();
        return $this->renderTemplate('commerce/store-settings/countries/index',
            compact('countries'));
    }

    /**
     * @param int|null $id
     * @param Country|null $country
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Country $country = null): Response
    {
        $variables = compact('id', 'country');

        if (!$variables['country']) {
            if ($variables['id']) {
                $id = $variables['id'];
                $variables['country'] = Plugin::getInstance()->getCountries()->getCountryById($id);

                if (!$variables['country']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['country'] = new Country();
            }
        }

        if ($variables['country']->id) {
            $variables['title'] = $variables['country']->name;
        } else {
            $variables['title'] = Plugin::t('Create a new country');
        }

        // Check to see if we should show the disable warning
        $variables['showDisableWarning'] = false;

        if ($variables['id'] && $variables['country']->id == $variables['id'] && $variables['country']->enabled) {
            $relatedAddressCount = (new Query())
                ->select(['addresses.id',])
                ->from([Table::ADDRESSES . ' addresses'])
                ->where(['countryId' => $variables['id']])
                ->count();

            $variables['showDisableWarning'] = $relatedAddressCount ? true : $variables['showDisableWarning'];

            if (!$variables['showDisableWarning']) {
                $relatedShippingZoneCount = (new Query())
                    ->select(['zone_countries.id',])
                    ->from([Table::SHIPPINGZONE_COUNTRIES . ' zone_countries'])
                    ->where(['countryId' => $variables['id']])
                    ->count();

                $variables['showDisableWarning'] = $relatedShippingZoneCount ? true : $variables['showDisableWarning'];
            }

            if (!$variables['showDisableWarning']) {
                $relatedTaxZoneCount = (new Query())
                    ->select(['zone_countries.id',])
                    ->from([Table::TAXZONE_COUNTRIES . ' zone_countries'])
                    ->where(['countryId' => $variables['id']])
                    ->count();

                $variables['showDisableWarning'] = $relatedTaxZoneCount ? true : $variables['showDisableWarning'];
            }

        }

        $variables['states'] = Plugin::getInstance()->getStates()->getAllStates();

        return $this->renderTemplate('commerce/store-settings/countries/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $country = new Country();

        // Shared attributes
        $country->id = Craft::$app->getRequest()->getBodyParam('countryId');
        $country->name = Craft::$app->getRequest()->getBodyParam('name');
        $country->iso = Craft::$app->getRequest()->getBodyParam('iso');
        $country->isStateRequired = (bool)Craft::$app->getRequest()->getBodyParam('isStateRequired');
        $country->enabled = (bool)Craft::$app->getRequest()->getBodyParam('enabled');

        // Save it
        if (Plugin::getInstance()->getCountries()->saveCountry($country)) {
            Craft::$app->getSession()->setNotice(Plugin::t('Country saved.'));
            $this->redirectToPostedUrl($country);
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save country.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['country' => $country]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        try {
            Plugin::getInstance()->getCountries()->deleteCountryById($id);
            return $this->asJson(['success' => true]);
        } catch (Exception $e) {
            return $this->asErrorJson($e->getMessage());
        }
    }

    /**
     * @return Response
     * @throws \yii\db\Exception
     * @throws BadRequestHttpException
     * @since 2.2
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));

        if ($success = Plugin::getInstance()->getCountries()->reorderCountries($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Plugin::t('Couldn’t reorder countries.')]);
    }

    /**
     * @throws BadRequestHttpException
     * @throws MissingComponentException
     * @throws \yii\db\Exception
     * @since 3.0
     */
    public function actionUpdateStatus()
    {
        $this->requirePostRequest();
        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');
        $status = Craft::$app->getRequest()->getRequiredBodyParam('status');

        if (empty($ids)) {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t update countries status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $countries = CountryRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var CountryRecord $country */
        foreach ($countries as $country) {
            $country->enabled = ($status == 'enabled');
            $country->save();
        }
        $transaction->commit();

        Craft::$app->getSession()->setNotice(Plugin::t('Countries updated.'));
    }
}
