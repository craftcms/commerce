<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Plan;
use craft\commerce\models\Settings as SettingsModel;
use craft\commerce\Plugin;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class SettingsController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * Commerce Settings Index
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->redirect('commerce/settings/general');
    }

    /**
     * Commerce Settings Form
     *
     * @return Response
     */
    public function actionEdit(): Response
    {
        $settings = Plugin::getInstance()->getSettings();

        $craftSettings = Craft::$app->getSystemSettings()->getEmailSettings();
        $settings->emailSenderAddressPlaceholder = $craftSettings['fromEmail'] ?? '';
        $settings->emailSenderNamePlaceholder = $craftSettings['fromName'] ?? '';

        return $this->renderTemplate('commerce/settings/general', ['settings' => $settings]);
    }

    /**
     * Save settings
     *
     * @return Response
     */
    public function actionSaveSettings(): Response
    {
        $this->requirePostRequest();
        $postData = Craft::$app->getRequest()->getParam('settings');
        $settings = new SettingsModel($postData);

        if (!Plugin::getInstance()->getSettings()->saveSettings($settings)) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings', ['settings' => $settings]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Settings saved.'));
        return $this->redirectToPostedUrl();
    }

    /**
     * Save stock location
     *
     * @return Response|null
     */
    public function actionSaveStockLocation()
    {
        $this->requirePostRequest();

        $address = Plugin::getInstance()->getAddresses()->getStockLocation();

        // Shared attributes
        $attributes = [
            'firstName',
            'lastName',
            'address1',
            'address2',
            'city',
            'zipCode',
            'businessName',
            'countryId',
        ];

        foreach ($attributes as $attr) {
            $address->$attr = Craft::$app->getRequest()->getParam($attr);
        }

        $address->stateId = Craft::$app->getRequest()->getParam('stateId');
        $address->stockLocation = true;

        // Save it
        if (Plugin::getInstance()->getAddresses()->saveAddress($address)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Address saved.'));
            return $this->redirectToPostedUrl();
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save address.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['address' => $address]);
    }

    /**
     * Saves the field layout.
     *
     * @return Response|null
     */
    public function actionSavePlanFieldLayout()
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        // Set the field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Plan::class;

        if (!Craft::$app->getFields()->saveLayout($fieldLayout)) {
            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save subscription plan fields.'));

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Subscription plan fields saved.'));

        return $this->redirectToPostedUrl();
    }

}
