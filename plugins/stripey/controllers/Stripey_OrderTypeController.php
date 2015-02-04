<?php
namespace Craft;

class Stripey_OrderTypeController extends Stripey_BaseController
{
	protected $allowAnonymous = false;

	public function actionIndex()
	{
		$orderTypes = craft()->stripey_orderType->getAll();
		$this->renderTemplate('stripey/settings/ordertypes/index', compact('orderTypes'));

	}

	public function actionEditOrderType(array $variables = array())
	{
		$variables['brandNewOrderType'] = false;

		if (empty($variables['orderType'])) {
			if (!empty($variables['orderTypeId'])) {
				$orderTypeId            = $variables['orderTypeId'];
				$variables['orderType'] = craft()->stripey_orderType->getById($orderTypeId);

				if (!$variables['orderType']) {
					throw new HttpException(404);
				}
			} else {
				$variables['orderType']         = new Stripey_OrderTypeModel();
				$variables['brandNewOrderType'] = true;
			};
		}

		if (!empty($variables['orderTypeId'])) {
			$variables['title'] = $variables['orderType']->name;
		} else {
			$variables['title'] = Craft::t('Create a Order Type');
		}

		$this->renderTemplate('stripey/settings/ordertypes/_edit', $variables);
	}

	public function actionSaveOrderType()
	{
		$this->requirePostRequest();

		$orderType = new Stripey_OrderTypeModel();

		// Shared attributes
		$orderType->id     = craft()->request->getPost('orderTypeId');
		$orderType->name   = craft()->request->getPost('name');
		$orderType->handle = craft()->request->getPost('handle');

		// Set the field layout
		$fieldLayout       = craft()->fields->assembleLayoutFromPost();
		$fieldLayout->type = 'Stripey_Order';
		$orderType->setFieldLayout($fieldLayout);

		// Save it
		if (craft()->stripey_orderType->save($orderType)) {
			craft()->userSession->setNotice(Craft::t('Order type saved.'));
			$this->redirectToPostedUrl($orderType);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save order type.'));
		}

		// Send the calendar back to the template
		craft()->urlManager->setRouteVariables(array(
			'orderType' => $orderType
		));
	}


	public function actionDeleteOrderType()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$orderTypeId = craft()->request->getRequiredPost('id');

		craft()->stripey_orderType->deleteById($orderTypeId);
		$this->returnJson(array('success' => true));
	}

} 