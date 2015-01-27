<?php
namespace Craft;

class Cellar_SettingsController extends BaseController
{
    protected $allowAnonymous = true;

    public function actionGeneral()
    {
    	$variables = array();

    	$settings = craft()->cellar_settings->getSettings();

    	$variables['settings'] = $settings;

    	$this->renderTemplate('cellar/settings/general', $variables);
    }

    public function actionGeneralSave()
    {
    	$settings = craft()->request->getPost('settings');


    	craft()->cellar_settings->saveSettings($settings);

    	$this->redirectToPostedUrl();
    }

}