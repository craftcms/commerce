<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\State;
use craft\commerce\Plugin;
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
    // Public Methods
    // =========================================================================

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
        $variables = [
            'id' => $id,
            'state' => $state
        ];
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
            $variables['title'] = Craft::t('commerce', 'Create a new state');
        }

        $countriesModels = Plugin::getInstance()->getCountries()->getAllCountries();
        $countries = [];
        foreach ($countriesModels as $model) {
            $countries[$model->id] = $model->name;
        }
        $variables['countries'] = $countries;

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

        // Save it
        if (Plugin::getInstance()->getStates()->saveState($state)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'State saved.'));
            $this->redirectToPostedUrl($state);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save state.'));
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
}
