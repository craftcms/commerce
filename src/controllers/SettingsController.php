<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

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
     * @return Response|null
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();
        $postData = Craft::$app->getRequest()->getBodyParam('settings');
        $settings = new SettingsModel($postData);


        if (!$settings->validate() || !Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings/general/index', ['settings' => $settings]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Settings saved.'));

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
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save subscription fields.'));

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Subscription fields saved.'));

        return $this->redirectToPostedUrl();
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

        return $this->renderTemplate('commerce/settings/location/index', $variables);
    }


    /**
     * Saves the store location setting
     */
    public function actionSaveStoreLocation()
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getBodyParam('id');

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

        $address->isStoreLocation = true;

        if ($address->validate() && Plugin::getInstance()->getAddresses()->saveAddress($address)) {

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Store Location saved.'));

            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save Store Location.'));

        $variables = [
            'storeLocation' => $address
        ];

        // Send the model back to the template
        return $this->renderTemplate('commerce/settings/location/index', $variables);
    }
}
