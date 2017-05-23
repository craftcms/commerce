<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\PaymentMethod;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use yii\web\HttpException;

/**
 * Class Payment Method Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class PaymentMethodsController extends BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $paymentMethods = Plugin::getInstance()->getPaymentMethods()->getAllPaymentMethods();
        $this->renderTemplate('commerce/settings/paymentmethods/index', compact('paymentMethods'));
    }

    /**
     * Create/Edit PaymentMethod
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['paymentMethod'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['paymentMethod'] = Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById($id);

                if (!$variables['paymentMethod']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['paymentMethod'] = new PaymentMethod();
            }
        }

        $variables['gateways'] = Plugin::getInstance()->getGateways()->getAllGateways();
        $list = [];
        foreach ($variables['gateways'] as $gw) {
            $list[$gw->handle()] = $gw->displayName();
        }
        asort($list);

        $variables['paymentMethod']->getGateway(); //init gateway settings
        $variables['gatewaysList'] = $list;
        if ($variables['paymentMethod']->id) {
            $variables['title'] = $variables['paymentMethod']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new payment method');
        }
        $this->renderTemplate('commerce/settings/paymentmethods/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $paymentMethod = new PaymentMethod();

        // Shared attributes
        $paymentMethod->id = Craft::$app->getRequest()->getRequiredParam('id');
        $paymentMethod->name = Craft::$app->getRequest()->getRequiredParam('name');
        $paymentMethod->paymentType = Craft::$app->getRequest()->getRequiredParam('paymentType');
        $paymentMethod->class = Craft::$app->getRequest()->getRequiredParam('class');
        $paymentMethod->frontendEnabled = Craft::$app->getRequest()->getParam('frontendEnabled');


        // Check if settings have been overridden in config.
        $configSettings = Plugin::getInstance()->getSettings()->getSettings()->paymentMethodSettings;
        if (!isset($configSettings[$paymentMethod->id])) {
            $paymentMethod->settings = Craft::$app->getRequest()->getParam('settings', []);
        } else {
            if ($paymentMethod->id) {
                // We need to get this directly from the database since the model populateModel fills from config file.
                $method = (new Query())
                    ->select(['*'])
                    ->from(['{{%commerce_paymentmethods}}'])
                    ->where(Db::parseParam('id', $paymentMethod->id))
                    ->one();
                if ($method) {
                    $paymentMethod->settings = $method['settings'];
                }
            }
        }

        // Save it
        if (Plugin::getInstance()->getPaymentMethods()->savePaymentMethod($paymentMethod)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Payment method saved.'));
            $this->redirectToPostedUrl($paymentMethod);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save payment method.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'paymentMethod' => $paymentMethod
        ]);
    }

    /**
     * @throws HttpException
     */
    public function actionArchive()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        if (Plugin::getInstance()->getPaymentMethods()->archivePaymentMethod($id)) {
            $this->asJson(['success' => true]);
        };

        $this->asErrorJson(Craft::t('commerce', 'Could not archive payment method'));
    }

    /**
     * @throws HttpException
     */
    public function actionReorder()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = Json::decode(Craft::$app->getRequest()->getRequiredParam('ids'));
        if ($success = Plugin::getInstance()->getPaymentMethods()->reorderPaymentMethods($ids)) {
            return $this->asJson(['success' => $success]);
        };

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder Payment Methods.')]);
    }

}
