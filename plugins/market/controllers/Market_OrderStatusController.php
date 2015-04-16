<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_OrderStatusController extends Market_BaseController
{
    /**
     * @param array $variables
     * @throws HttpException
     */
	public function actionEdit(array $variables = [])
	{
        $variables['orderType'] = craft()->market_orderType->getById($variables['orderTypeId']);

		if (empty($variables['orderStatus'])) {
			if (!empty($variables['id'])) {
                $variables['orderStatus'] = craft()->market_orderStatus->getById($variables['id']);

				if (!$variables['orderStatus']->id) {
					throw new HttpException(404);
				}
			} else {
				$variables['orderStatus'] = new Market_OrderStatusModel();
			}
		}

		if (!empty($variables['orderStatusId'])) {
			$variables['title'] = $variables['orderStatus']->name;
		} else {
			$variables['title'] = Craft::t('Create an Order Status');
		}

        $emails              = craft()->market_email->getAll(['order' => 'name']);
        $variables['emails'] = \CHtml::listData($emails, 'id', 'name');

		$this->renderTemplate('market/settings/orderstatuses/_edit', $variables);
	}

    /**
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
	public function actionSave()
	{
		$this->requirePostRequest();

		$orderStatus = new Market_OrderStatusModel();

		// Shared attributes
		$orderStatus->id               = craft()->request->getPost('orderStatusId');
		$orderStatus->name             = craft()->request->getPost('name');
		$orderStatus->handle           = craft()->request->getPost('handle');
		$orderStatus->color            = craft()->request->getPost('color');
		$orderStatus->orderTypeId      = craft()->request->getPost('orderTypeId');
		$orderStatus->default          = craft()->request->getPost('default');
        $emailsIds = craft()->request->getPost('emails', []);

		// Save it
		if (craft()->market_orderStatus->save($orderStatus, $emailsIds)) {
			craft()->userSession->setNotice(Craft::t('Order status saved.'));
			$this->redirectToPostedUrl($orderStatus);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save order status.'));
		}

		// Send the calendar back to the template
		craft()->urlManager->setRouteVariables(compact('orderStatus', 'emailsIds'));
	}

    /**
     * @throws HttpException
     */
	public function actionDelete()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$orderStatusId = craft()->request->getRequiredPost('id');

		craft()->market_orderStatus->deleteById($orderStatusId);
		$this->returnJson(['success' => true]);
	}

} 