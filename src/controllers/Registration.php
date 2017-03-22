<?php
namespace craft\commerce\controllers;

use Craft;

/**
 * Class Registration Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Registration extends BaseAdmin
{
    public function actionEdit()
    {
        $licenseKey = Craft::$app->getPlugins()->getPluginLicenseKey('Commerce');

        $this->renderTemplate('commerce/settings/registration', [
            'hasLicenseKey' => ($licenseKey !== null)
        ]);
    }

    public function actionGetLicenseInfo()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        Craft::$app->getEt()->ping();

        $this->_sendSuccessResponse();
    }

    /**
     * Returns a successful license update response.
     */
    private function _sendSuccessResponse()
    {
        $this->asJson([
            'success' => true,
            'licenseKey' => Craft::$app->getPlugins()->getPluginLicenseKey('Commerce'),
            'licenseKeyStatus' => Craft::$app->getPlugins()->getPluginLicenseKeyStatus('Commerce'),
        ]);
    }

    public function actionUnregister()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $etResponse = Craft::$app->getEt()->unregisterPlugin('Commerce');
        $this->_handleEtResponse($etResponse);
    }

    /**
     * Returns a response based on the EtService response.
     *
     * @param mixed
     *
     * @return bool|string The response from EtService
     */
    private function _handleEtResponse($etResponse)
    {
        if (!empty($etResponse->data['success'])) {
            $this->_sendSuccessResponse();
        } else {
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

            $this->asErrorJson($error);
        }
    }

    public function actionUpdateLicenseKey()
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
            $this->_handleEtResponse($etResponse);
        } else {
            // Just clear our record of the license key
            Craft::$app->getPlugins()->setPluginLicenseKey('Commerce', null);
            Craft::$app->getPlugins()->setPluginLicenseKeyStatus('Commerce', LicenseKeyStatus::Unknown);
            $this->_sendSuccessResponse();
        }
    }

    public function actionTransfer()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $etResponse = Craft::$app->getEt()->transferPlugin('Commerce');
        $this->_handleEtResponse($etResponse);
    }
}
