<?php
namespace Craft;

/**
 * Class Commerce_OrderStatusesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_OrderStatusesController extends Commerce_BaseAdminController
{
    /**
     * @param array $variables
     * @throws HttpException
     */
    public function actionIndex(array $variables = [])
    {
        $variables['orderStatuses'] = craft()->commerce_orderStatuses->getAllOrderStatuses();

        $this->renderTemplate('commerce/settings/orderstatuses/index', $variables);
    }


    /**
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['orderStatus'])) {
            if (!empty($variables['id'])) {
                $variables['orderStatus'] = craft()->commerce_orderStatuses->getOrderStatusById($variables['id']);
                $variables['orderStatusId'] = $variables['orderStatus'];
                if (!$variables['orderStatus']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['orderStatus'] = new Commerce_OrderStatusModel();
            }
        }

        if (!empty($variables['orderStatusId'])) {
            $variables['title'] = $variables['orderStatus']->name;
        } else {
            $variables['title'] = Craft::t('Create a new order status');
        }

        $emails = craft()->commerce_emails->getAllEmails(['order' => 'name']);
        $variables['emails'] = \CHtml::listData($emails, 'id', 'name');

        $this->renderTemplate('commerce/settings/orderstatuses/_edit',
            $variables);
    }

    /**
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $orderStatus = new Commerce_OrderStatusModel();

        // Shared attributes
        $orderStatus->id = craft()->request->getPost('orderStatusId');
        $orderStatus->name = craft()->request->getPost('name');
        $orderStatus->handle = craft()->request->getPost('handle');
        $orderStatus->color = craft()->request->getPost('color');
        $orderStatus->default = craft()->request->getPost('default');
        $emailIds = craft()->request->getPost('emails', []);

        // Save it
        if (craft()->commerce_orderStatuses->saveOrderStatus($orderStatus, $emailIds)) {
            craft()->userSession->setNotice(Craft::t('Order status saved.'));
            $this->redirectToPostedUrl($orderStatus);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save order status.'));
        }

        craft()->urlManager->setRouteVariables(compact('orderStatus', 'emailIds'));
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requireAjaxRequest();

        $orderStatusId = craft()->request->getRequiredPost('id');

        if (craft()->commerce_orderStatuses->deleteOrderStatusById($orderStatusId)) {
            $this->returnJson(['success' => true]);
        };
    }

}
