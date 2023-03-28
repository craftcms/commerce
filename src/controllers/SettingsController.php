<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Settings;
use craft\commerce\Plugin;
use craft\commerce\services\Subscriptions;
use craft\helpers\App;
use craft\helpers\StringHelper;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SettingsController extends BaseAdminController
{
    /**
     * Commerce Settings Form
     */
    public function actionEdit(): Response
    {
        return $this->renderTemplate('commerce/settings/general', ['settings' => Plugin::getInstance()->getSettings()]);
    }

    /**
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        $params = $this->request->getBodyParams();
        $data = $params['settings'];

        $settings = Plugin::getInstance()->getSettings();
        $settings->weightUnits = $data['weightUnits'] ?? key($settings->getWeightUnitsOptions());
        $settings->dimensionUnits = $data['dimensionUnits'] ?? key($settings->getDimensionUnits());
        $settings->updateBillingDetailsUrl = $data['updateBillingDetailsUrl'] ?? $settings->updateBillingDetailsUrl;
        $settings->defaultView = $data['defaultView'] ?? $settings->defaultView;

        if (!$settings->validate()) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings/general/index', compact('settings'));
        }

        $pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings->toArray());

        if (!$pluginSettingsSaved) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings/general/index', compact('settings'));
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Saves the field layout.
     *
     * @return Response|null
     */
    public function actionSaveSubscriptionFieldLayout(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $configData = [StringHelper::UUID() => $fieldLayout->getConfig()];

        Craft::$app->getProjectConfig()->set(Subscriptions::CONFIG_FIELDLAYOUT_KEY, $configData);

        $this->setSuccessFlash(Craft::t('commerce', 'Subscription fields saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionSites(): Response
    {
        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('commerce/settings/sites/_edit', [
            'sites' => $sites,
            'primaryStoreId' => Plugin::getInstance()->getStores()->getPrimaryStore()->id,
            'stores' => Plugin::getInstance()->getStores()->getAllStores(),
            'storesList' => Plugin::getInstance()->getStores()->getAllStores()->map(function($store) {
                return [
                    'label' => $store->name . ($store->primary ? ' (' . Craft::t('commerce', 'Primary') . ')' : ''),
                    'value' => $store->id,
                ];
            }),
        ]);
    }
}
