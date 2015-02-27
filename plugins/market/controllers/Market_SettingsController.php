<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_SettingsController extends Market_BaseController
{

	/**
	 * Market Settings Form
	 */
	public function actionEdit()
	{
		$settings = craft()->market_settings->getSettings();
        $this->renderTemplate('market/settings', ['settings' => $settings]);
    }

    /**
     * @throws HttpException
     */
    public function actionSaveSettings()
	{
		$this->requirePostRequest();
		$postData = craft()->request->getPost('settings');
		$settings = Market_SettingsModel::populateModel($postData);

		if (!craft()->market_settings->save($settings)) {
			craft()->userSession->setError(Craft::t('Error, Market settings not saved.'));
            $this->renderTemplate('market/settings', ['settings' => $settings]);
        } else {
			craft()->userSession->setNotice(Craft::t('Success, Market settings saved.'));
			$this->redirectToPostedUrl();
		}
	}
} 