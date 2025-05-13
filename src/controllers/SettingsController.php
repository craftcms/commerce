<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Subscription;
use craft\commerce\elements\Transfer;
use craft\commerce\models\Settings;
use craft\commerce\Plugin;
use craft\commerce\services\Subscriptions;
use craft\commerce\services\Transfers;
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
        $readOnly = $this->isReadOnlyScreen();
        return $this->renderTemplate('commerce/settings/general', [
            'settings' => Plugin::getInstance()->getSettings(),
            'readOnly' => $this->isReadOnlyScreen(),
        ]);
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

    /**
     * @return Response
     */
    public function actionSaveTransferSettings(): Response
    {
        $this->requirePostRequest();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();

        $fieldLayout->reservedFieldHandles = [
            'originLocationId',
            'originLocation',
            'destinationLocationId',
            'destinationLocation',
        ];

        $fieldLayout->type = Transfer::class;

        if (!$fieldLayout->validate()) {
            Craft::info('Field layout not saved due to validation error.', __METHOD__);

            Craft::$app->getUrlManager()->setRouteParams([
                'variables' => [
                    'fieldLayout' => $fieldLayout,
                ],
            ]);

            return $this->asFailure(Craft::t('commerce', 'Couldn’t save transfer fields.'));
        }

        if ($currentTransfersFieldLayout = Craft::$app->getProjectConfig()->get(Transfers::CONFIG_FIELDLAYOUT_KEY)) {
            $uid = array_key_first($currentTransfersFieldLayout);
        } else {
            $uid = StringHelper::UUID();
        }

        $configData = [$uid => $fieldLayout->getConfig()];
        $result = Craft::$app->getProjectConfig()->set(Transfers::CONFIG_FIELDLAYOUT_KEY, $configData, force: true);

        if (!$result) {
            return $this->asFailure(Craft::t('app', 'Couldn’t save transfer fields.'));
        }

        return $this->asSuccess(Craft::t('commerce', 'Transfer fields saved.'));
    }

    /**
     * @return Response
     */
    public function actionSaveSubscriptionSettings(): Response
    {
        $this->requirePostRequest();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();

        $fieldLayout->reservedFieldHandles = [
        ];

        $fieldLayout->type = Subscription::class;

        if (!$fieldLayout->validate()) {
            Craft::info('Field layout not saved due to validation error.', __METHOD__);

            Craft::$app->getUrlManager()->setRouteParams([
                'variables' => [
                    'fieldLayout' => $fieldLayout,
                ],
            ]);

            return $this->asFailure(Craft::t('commerce', 'Couldn’t save subscription fields.'));
        }

        if ($currentSubscriptionsFieldLayout = Craft::$app->getProjectConfig()->get(Subscriptions::CONFIG_FIELDLAYOUT_KEY)) {
            $uid = array_key_first($currentSubscriptionsFieldLayout);
        } else {
            $uid = StringHelper::UUID();
        }

        $configData = [$uid => $fieldLayout->getConfig()];
        $result = Craft::$app->getProjectConfig()->set(Subscriptions::CONFIG_FIELDLAYOUT_KEY, $configData, force: true);

        if (!$result) {
            return $this->asFailure(Craft::t('app', 'Couldn’t save subscription fields.'));
        }

        return $this->asSuccess(Craft::t('commerce', 'Subscription fields saved.'));
    }


    /**
     * @param array $variables
     * @return Response
     */
    public function actionEditTransferSettings(array $variables = []): Response
    {
        $fieldLayout = Plugin::getInstance()->getTransfers()->getFieldLayout();

        $variables['fieldLayout'] = $fieldLayout;
        $variables['title'] = Craft::t('commerce', 'Transfer Settings');
        $variables['readOnly'] = $this->isReadOnlyScreen();

        return $this->renderTemplate('commerce/settings/transfers/_edit', $variables);
    }

    /**
     * @param array $variables
     * @return Response
     */
    public function actionEditSubscriptionSettings(array $variables = []): Response
    {
        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Subscription::class);

        $variables['fieldLayout'] = $fieldLayout;
        $variables['title'] = Craft::t('commerce', 'Subscription Settings');
        $variables['readOnly'] = $this->isReadOnlyScreen();

        return $this->renderTemplate('commerce/settings/subscriptions/_edit', $variables);
    }
}
