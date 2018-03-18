<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\enums\LicenseKeyStatus;
use GuzzleHttp\Exception\RequestException;
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

        try {
            Craft::$app->getApi()->getLicenseInfo();
        } catch (RequestException $e) {
            // if there was an issue with the Commerce license,
            // we'll be able to get it from getPluginLicenseKeyStatus()
        }

        return $this->_sendSuccessResponse();
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

            try {
                Craft::$app->getApi()->getLicenseInfo();
            } catch (RequestException $e) {
                // if there was an issue with the Commerce license,
                // we'll be able to get it from getPluginLicenseKeyStatus()
            }

            return $this->_sendSuccessResponse();
        }

        // Just clear our record of the license key
        Craft::$app->getPlugins()->setPluginLicenseKey('Commerce');
        Craft::$app->getPlugins()->setPluginLicenseKeyStatus('Commerce', LicenseKeyStatus::Unknown);

        return $this->_sendSuccessResponse();
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns a successful license update response.
     *
     * @return Response
     */
    private function _sendSuccessResponse(): Response
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
