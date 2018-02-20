<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Subscription;
use craft\commerce\models\Address;
use craft\commerce\models\Settings as SettingsModel;
use craft\commerce\Plugin;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SettingsController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * Commerce Settings Form
     */
    public function actionEdit(): Response
    {
        $settings = Plugin::getInstance()->getSettings();

        $craftSettings = Craft::$app->getSystemSettings()->getEmailSettings();
        $settings->emailSenderAddressPlaceholder = $craftSettings['fromEmail'] ?? '';
        $settings->emailSenderNamePlaceholder = $craftSettings['fromName'] ?? '';

        $variables = [
            'settings' => $settings
        ];

        return $this->renderTemplate('commerce/settings/general', $variables);
    }

    /**
     * @return Response
     */
    public function actionEditLocation(): Response
    {
        $storeLocation = Plugin::getInstance()->getAddresses()->getStoreLocationAddress();

        if (!$storeLocation) {
            $storeLocation = new Address();
        }

        $variables = [
            'storeLocation' => $storeLocation
        ];

        return $this->renderTemplate('commerce/settings/storelocation/index', $variables);
    }

    /**
     * @return Response|null
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();
        $postData = Craft::$app->getRequest()->getParam('settings');
        $settings = new SettingsModel($postData);

        if (!Plugin::getInstance()->getSettings()->saveSettings($settings)) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings', ['settings' => $settings]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Settings saved.'));

        $this->redirectToPostedUrl();

        return null;
    }

    /**
     * @return Response
     */
    public function actionSavelocation(): Repsonse
    {
        $this->requirePostRequest();

        $address = Plugin::getInstance()->getAddresses()->getStoreLocationAddress();

        if (!$address) {
            $address = new Address();
        }

        $attributes = [
            'attention',
            'title',
            'firstName',
            'lastName',
            'address1',
            'address2',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'businessName',
            'businessTaxId',
            'businessId',
            'countryId',
            'stateValue'
        ];
        foreach ($attributes as $attr) {
            $address->$attr = Craft::$app->getRequest()->getParam($attr);
        }

        $address->storeLocation = true;

        // Save it
        if (Plugin::getInstance()->getAddresses()->saveAddress($address)) {

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => true, 'address' => $address]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Address saved.'));
            return $this->redirectToPostedUrl();
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'error' => Craft::t('commerce', 'Couldn’t save address.'),
                'errors' => $address->errors
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save address.'));


        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['storeLocation' => $address]);

        return $this->redirectToPostedUrl();
    }

    /**
     * Saves the field layout.
     *
     * @return Response|null
     */
    public function actionSaveSubscriptionFieldLayout()
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        // Set the field layout
        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Subscription::class;

        if (!Craft::$app->getFields()->saveLayout($fieldLayout)) {
            Craft::$app->getSession()->setError(Craft::t('app', 'Couldn’t save subscription fields.'));

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Subscription fields saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     *
     */
    public function actionSaveStoreLocation()
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getParam('id');

        $address = Plugin::getInstance()->getAddresses()->getAddressById($id);

        if (!$address) {
            $address = new Address();
        }

        // Shared attributes
        $attributes = [
            'attention',
            'title',
            'firstName',
            'lastName',
            'address1',
            'address2',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'businessName',
            'businessTaxId',
            'businessId',
            'countryId',
            'stateValue'
        ];
        foreach ($attributes as $attr) {
            $address->$attr = Craft::$app->getRequest()->getParam($attr);
        }

        $address->storeLocation = true;

        // Save it
        if (Plugin::getInstance()->getAddresses()->saveAddress($address)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson(['success' => true, 'address' => $address]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Address saved.'));
            return $this->redirectToPostedUrl();
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asJson([
                    'error' => Craft::t('commerce', 'Couldn’t save address.'),
                    'errors' => $address->errors
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save address.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['address' => $address]);
    }
}
