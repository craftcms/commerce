<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Shipping Methods Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingMethodsController extends BaseShippingSettingsController
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response
     */
    public function actionIndex(): Response
    {
        $shippingMethods = Plugin::getInstance()->getShippingMethods()->getAllShippingMethods();
        return $this->renderTemplate('commerce/settings/shippingmethods/index', compact('shippingMethods'));
    }

    /**
     * @param int|null $id
     * @param ShippingMethod|null $shippingMethod
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, ShippingMethod $shippingMethod = null): Response
    {
        $variables = [
            'id' => $id,
            'shippingMethod' => $shippingMethod
        ];

        $variables['newMethod'] = false;

        if (!$variables['shippingMethod']) {
            if ($variables['id']) {
                $variables['shippingMethod'] = Plugin::getInstance()->getShippingMethods()->getShippingMethodById($variables['id']);

                if (!$variables['shippingMethod']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingMethod'] = new ShippingMethod();
            }
        }

        if ($variables['shippingMethod']->id) {
            $variables['title'] = $variables['shippingMethod']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new shipping method');
        }

        $shippingRules = Plugin::getInstance()->getShippingRules()->getAllShippingRulesByShippingMethodId($variables['shippingMethod']->id);

        $variables['shippingRules'] = $shippingRules;

        return $this->renderTemplate('commerce/settings/shippingmethods/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();
        $shippingMethod = new ShippingMethod();

        // Shared attributes
        $shippingMethod->id = Craft::$app->getRequest()->getBodyParam('shippingMethodId');
        $shippingMethod->name = Craft::$app->getRequest()->getBodyParam('name');
        $shippingMethod->handle = Craft::$app->getRequest()->getBodyParam('handle');
        $shippingMethod->enabled = (bool)Craft::$app->getRequest()->getBodyParam('enabled');

        // Save it
        if (Plugin::getInstance()->getShippingMethods()->saveShippingMethod($shippingMethod)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Shipping method saved.'));
            $this->redirectToPostedUrl($shippingMethod);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save shipping method.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['shippingMethod' => $shippingMethod]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::getInstance()->getShippingMethods()->deleteShippingMethodById($id)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asErrorJson(Craft::t('commerce', 'Could delete shipping method and it’s rules.'));
    }
}
