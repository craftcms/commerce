<?php
namespace craft\commerce\controllers;

use craft\commerce\models\State;

/**
 * Class State Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class StatesController extends BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $states = Plugin::getInstance()->getStates()->getAllStates();
        $this->renderTemplate('commerce/settings/states/index',
            compact('states'));
    }

    /**
     * Create/Edit State
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['state'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['state'] = Plugin::getInstance()->getStates()->getStateById($id);

                if (!$variables['state']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['state'] = new State();
            };
        }

        if (!empty($variables['id'])) {
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

        $this->renderTemplate('commerce/settings/states/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $state = new State();

        // Shared attributes
        $state->id = Craft::$app->getRequest()->getParam('stateId');
        $state->name = Craft::$app->getRequest()->getParam('name');
        $state->abbreviation = Craft::$app->getRequest()->getParam('abbreviation');
        $state->countryId = Craft::$app->getRequest()->getParam('countryId');

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
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getStates()->deleteStateById($id);
        $this->asJson(['success' => true]);
    }

}
