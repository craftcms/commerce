<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\enums\LicenseKeyStatus;
use yii\web\Response;

/**
 * Class Registration Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class RegistrationController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionEdit(): Response
    {
        $this->getView()->registerAssetBundle(CommerceCpAsset::class);

        $licenseKey = Craft::$app->getPlugins()->getPluginLicenseKey('Commerce');

        return $this->renderTemplate('commerce/settings/registration', [
            'hasLicenseKey' => $licenseKey !== null
        ]);
    }

    /**
     * @return Response
     */
    public function actionGetLicenseInfo(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        Craft::$app->getEt()->ping();

        return $this->_sendSuccessResponse();
    }

    /**
     * @return bool|string|Response
     */
    public function actionUnregister(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $etResponse = Craft::$app->getEt()->unregisterPlugin('Commerce');
        return $this->_handleEtResponse($etResponse);
    }

    /**
     * @return bool|string|Response
     */
    public function actionUpdateLicenseKey(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $licenseKey = Craft::$app->getRequest()->getRequiredParam('licenseKey');

        // Are we registering a new license key?
        if ($licenseKey) {
            // Record the license key locally
            try {
                Craft::$app->getPlugins()->setPluginLicenseKey('Commerce', $licenseKey);
            } catch (InvalidLicenseKeyException $e) {
                $this->asErrorJson(Craft::t('commerce', 'That license key is invalid.'));
            }

            // Register it with Elliott
            $etResponse = Craft::$app->getEt()->registerPlugin('Commerce');
            return $this->_handleEtResponse($etResponse);
        }

        // Just clear our record of the license key
        Craft::$app->getPlugins()->setPluginLicenseKey('Commerce');
        Craft::$app->getPlugins()->setPluginLicenseKeyStatus('Commerce', LicenseKeyStatus::Unknown);

        return $this->_sendSuccessResponse();
    }

    /**
     * @return bool|string|Response
     */
    public function actionTransfer(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $etResponse = Craft::$app->getEt()->transferPlugin('Commerce');
        return $this->_handleEtResponse($etResponse);
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a successful license update response.
     */
    private function _sendSuccessResponse()
    {
        return $this->asJson([
            'success' => true,
            'licenseKey' => Craft::$app->getPlugins()->getPluginLicenseKey('Commerce'),
            'licenseKeyStatus' => Craft::$app->getPlugins()->getPluginLicenseKeyStatus('Commerce'),
        ]);
    }

    /**
     * Returns a response based on the EtService response.
     *
     * @param mixed
     * @return bool|string The response from EtService
     */
    private function _handleEtResponse($etResponse)
    {
        if (!empty($etResponse->data['success'])) {
            return $this->_sendSuccessResponse();
        }

        if (!empty($etResponse->errors)) {
            switch ($etResponse->errors[0]) {
                case 'nonexistent_plugin_license':
                    $error = Craft::t('commerce', 'That license key isn’t valid');
                    break;
                case 'plugin_license_in_use':
                    $error = Craft::t('commerce', 'That license key is already being used on another Craft site');
                    break;
                default:
                    $error = $etResponse->errors[0];
            }
        } else {
            $error = Craft::t('commerce', 'An unknown error occurred.');
        }

        return $this->asErrorJson($error);
    }
}
