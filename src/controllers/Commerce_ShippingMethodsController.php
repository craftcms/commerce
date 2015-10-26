<?php
namespace Craft;

/**
 * Class Commerce_ShippingMethodsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ShippingMethodsController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $shippingMethods = craft()->commerce_shippingMethods->getAll();
        $this->renderTemplate('commerce/settings/shippingmethods/index', compact('shippingMethods'));
    }

    /**
     * Create/Edit Shipping Method
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        $variables['newMethod'] = false;

        if (empty($variables['shippingMethod'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['shippingMethod'] = craft()->commerce_shippingMethods->getById($id);

                if (!$variables['shippingMethod']->id) {
                    throw new HttpException(404);
                }
            } else {
                $variables['shippingMethod'] = new Commerce_ShippingMethodModel();
                $variables['newMethod'] = true;
            }
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['shippingMethod']->name;
        } else {
            $variables['title'] = Craft::t('Create a new shipping method');
            $variables['newMethod'] = true;
        }

        $shippingRules = craft()->commerce_shippingRules->getAllByMethodId($variables['shippingMethod']->id);

        $variables['shippingRules'] = $shippingRules;

        $this->renderTemplate('commerce/settings/shippingmethods/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();
        $shippingMethod = new Commerce_ShippingMethodModel();

        // Shared attributes
        $shippingMethod->id = craft()->request->getPost('shippingMethodId');
        $shippingMethod->name = craft()->request->getPost('name');
        $shippingMethod->handle = craft()->request->getPost('handle');
        $shippingMethod->enabled = craft()->request->getPost('enabled');
        // Save it
        if (craft()->commerce_shippingMethods->save($shippingMethod)) {
            craft()->userSession->setNotice(Craft::t('Shipping method saved.'));
            $this->redirectToPostedUrl($shippingMethod);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save shipping method.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['shippingMethod' => $shippingMethod]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        $method = craft()->commerce_shippingMethods->getById($id);

        if (craft()->commerce_shippingMethods->delete($method)) {
            $this->returnJson(['success' => true]);
        }
    }

}
