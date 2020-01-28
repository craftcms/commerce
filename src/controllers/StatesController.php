<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\records\State as StateRecord;
use craft\db\Query;
use craft\errors\MissingComponentException;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class State Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class StatesController extends BaseStoreSettingsController
{
    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $states = Plugin::getInstance()->getStates()->getAllStates();
        return $this->renderTemplate('commerce/store-settings/states/index', compact('states'));
    }

    /**
     * @param int|null $id
     * @param State|null $state
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, State $state = null): Response
    {
        $variables = compact('id', 'state');
        if (!$variables['state']) {
            if ($variables['id']) {
                $variables['state'] = Plugin::getInstance()->getStates()->getStateById($variables['id']);

                if (!$variables['state']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['state'] = new State();
            }
        }

        if ($variables['state']->id) {
            $variables['title'] = $variables['state']->name;
        } else {
            $variables['title'] = Plugin::t('Create a new state');
        }

        $variables['countries'] = Plugin::getInstance()->getCountries()->getAllEnabledCountriesAsList();


        // Check to see if we should show the disable warning
        $variables['showDisableWarning'] = false;

        if ($variables['id'] && $variables['state']->id == $variables['id'] && $variables['state']->enabled) {
            $relatedAddressCount = (new Query())
                ->select(['addresses.id',])
                ->from([Table::ADDRESSES . ' addresses'])
                ->where(['stateId' => $variables['id']])
                ->count();

            $variables['showDisableWarning'] = $relatedAddressCount ? true : $variables['showDisableWarning'];

            if (!$variables['showDisableWarning']) {
                $relatedShippingZoneCount = (new Query())
                    ->select(['zone_states.id',])
                    ->from([Table::SHIPPINGZONE_STATES . ' zone_states'])
                    ->where(['stateId' => $variables['id']])
                    ->count();

                $variables['showDisableWarning'] = $relatedShippingZoneCount ? true : $variables['showDisableWarning'];
            }

            if (!$variables['showDisableWarning']) {
                $relatedTaxZoneCount = (new Query())
                    ->select(['zone_states.id',])
                    ->from([Table::TAXZONE_STATES . ' zone_states'])
                    ->where(['stateId' => $variables['id']])
                    ->count();

                $variables['showDisableWarning'] = $relatedTaxZoneCount ? true : $variables['showDisableWarning'];
            }
        }

        return $this->renderTemplate('commerce/store-settings/states/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $state = new State();

        // Shared attributes
        $state->id = Craft::$app->getRequest()->getBodyParam('stateId');
        $state->name = Craft::$app->getRequest()->getBodyParam('name');
        $state->abbreviation = Craft::$app->getRequest()->getBodyParam('abbreviation');
        $state->countryId = Craft::$app->getRequest()->getBodyParam('countryId');
        $state->enabled = (bool)Craft::$app->getRequest()->getBodyParam('enabled');

        // Save it
        if (Plugin::getInstance()->getStates()->saveState($state)) {
            Craft::$app->getSession()->setNotice(Plugin::t('State saved.'));
            $this->redirectToPostedUrl($state);
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save state.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'state' => $state
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getStates()->deleteStateById($id);
        return $this->asJson(['success' => true]);
    }

    /**
     * @throws MissingComponentException
     * @throws Exception
     * @throws BadRequestHttpException
     * @since 3.0
     */
    public function actionUpdateStatus()
    {
        $this->requirePostRequest();
        $ids = Craft::$app->getRequest()->getRequiredBodyParam('ids');
        $status = Craft::$app->getRequest()->getRequiredBodyParam('status');

        if (empty($ids)) {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t update states status.'));
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        $states = StateRecord::find()
            ->where(['id' => $ids])
            ->all();

        /** @var StateRecord $state */
        foreach ($states as $state) {
            $state->enabled = ($status == 'enabled');
            $state->save();
        }
        $transaction->commit();

        Craft::$app->getSession()->setNotice(Plugin::t('States updated.'));
    }
}
