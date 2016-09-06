<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Order status service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_OrderStatusesService extends BaseApplicationComponent
{

	/**
	 * @param string $handle
	 *
	 * @return Commerce_OrderStatusModel|null
	 */
	public function getOrderStatusByHandle($handle)
	{
		$result = Commerce_OrderStatusRecord::model()->findByAttributes(['handle' => $handle]);

		if ($result)
		{
			return Commerce_OrderStatusModel::populateModel($result);
		}

		return null;
	}

	/**
	 * @param $id
	 *
	 * @return Commerce_EmailModel[]
	 */
	public function getAllEmailsByOrderStatusId($id)
	{
		$orderStatus = Commerce_OrderStatusRecord::model()->with('emails')->findById($id);

		if ($orderStatus)
		{
			return Commerce_EmailModel::populateModels($orderStatus->emails);
		}

		return [];
	}

	/**
	 * Get default order status from the DB
	 *
	 * @return Commerce_OrderStatusModel|null
	 */
	public function getDefaultOrderStatus()
	{
		$result = Commerce_OrderStatusRecord::model()->findByAttributes(['default' => true]);

		if ($result)
		{
			return Commerce_OrderStatusModel::populateModel($result);
		}

		return null;
	}

    /**
     * Get default order status ID from the DB
     *
     * @return int|null
     */
    public function getDefaultOrderStatusId()
    {
        $defaultStatus = $this->getDefaultOrderStatus();

        if ($defaultStatus && $defaultStatus->id)
        {
            return $defaultStatus->id;
        }

        return null;
    }

	/**
	 * @param Commerce_OrderStatusModel $model
	 * @param array                     $emailIds
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function saveOrderStatus(Commerce_OrderStatusModel $model, array $emailIds)
	{
		if ($model->id)
		{
			$record = Commerce_OrderStatusRecord::model()->findById($model->id);
			if (!$record->id)
			{
				throw new Exception(Craft::t('No order status exists with the ID “{id}”',
					['id' => $model->id]));
			}
		}
		else
		{
			$record = new Commerce_OrderStatusRecord();
		}

		$record->name = $model->name;
		$record->handle = $model->handle;
		$record->color = $model->color;
		$record->sortOrder = $model->sortOrder ? $model->sortOrder : 999;
		$record->default = $model->default;

		$record->validate();
		$model->addErrors($record->getErrors());

		//validating emails ids
		$criteria = new \CDbCriteria();
		$criteria->addInCondition('id', $emailIds);
		$exist = Commerce_EmailRecord::model()->exists($criteria);
		$hasEmails = (boolean)count($emailIds);

		if (!$exist && $hasEmails)
		{
			$model->addError('emails',
				'One or more emails do not exist in the system.');
		}

		//saving
		if (!$model->hasErrors())
		{
			CommerceDbHelper::beginStackedTransaction();
			try
			{
				//only one default status can be among statuses of one order type
				if ($record->default)
				{
					Commerce_OrderStatusRecord::model()->updateAll(['default' => 0]);
				}

				// Save it!
				$record->save(false);

				//Delete old links
				if ($model->id)
				{
					Commerce_OrderStatusEmailRecord::model()->deleteAllByAttributes(['orderStatusId' => $model->id]);
				}

				//Save new links
				$rows = array_map(function ($id) use ($record)
				{
					return [$id, $record->id];
				}, $emailIds);
				$cols = ['emailId', 'orderStatusId'];
				$table = Commerce_OrderStatusEmailRecord::model()->getTableName();
				craft()->db->createCommand()->insertAll($table, $cols, $rows);

				// Now that we have a calendar ID, save it on the model
				$model->id = $record->id;

				CommerceDbHelper::commitStackedTransaction();
			}
			catch (\Exception $e)
			{
				CommerceDbHelper::rollbackStackedTransaction();
				throw $e;
			}

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param int
	 *
	 * @return bool
	 */
	public function deleteOrderStatusById($id)
	{
		$statuses = $this->getAllOrderStatuses();

		$criteria = craft()->elements->getCriteria('Commerce_Order');
		$criteria->orderStatusId = $id;
		$order = $criteria->first();

		if ($order)
		{
			return false;
		}

		if (count($statuses) >= 2)
		{
			Commerce_OrderStatusRecord::model()->deleteByPk($id);

			return true;
		}

		return false;
	}

	/**
	 * @param array|\CDbCriteria $criteria
	 *
	 * @return Commerce_OrderStatusModel[]
	 */
	public function getAllOrderStatuses($criteria = [])
	{
		$criteria['order'] = 'sortOrder ASC';
		$orderStatusRecords = Commerce_OrderStatusRecord::model()->findAll($criteria);

		return Commerce_OrderStatusModel::populateModels($orderStatusRecords);
	}

	/**
	 * Handler for order status change event
	 *
	 * @param Commerce_OrderModel $order
	 * @param Commerce_OrderHistoryModel $orderHistory
	 *
	 * @throws Exception
	 */
	public function statusChangeHandler($order, $orderHistory)
	{
		if ($order->orderStatusId)
		{
			$status = craft()->commerce_orderStatuses->getOrderStatusById($order->orderStatusId);
			if ($status && count($status->emails))
			{
				foreach ($status->emails as $email)
				{
					if ($email->enabled)
					{
						$this->_sendStatusChangeEmail($email, $order, $orderHistory);
					}
				}
			}
		}
	}


	/**
	 * @param int $id
	 *
	 * @return Commerce_OrderStatusModel|null
	 */
	public function getOrderStatusById($id)
	{
		$result = Commerce_OrderStatusRecord::model()->findById($id);

		if ($result)
		{
			return Commerce_OrderStatusModel::populateModel($result);
		}

		return null;
	}

	/**
	 * @param $ids
	 *
	 * @return bool
	 */
	public function reorderOrderStatuses($ids)
	{
		foreach ($ids as $sortOrder => $id)
		{
			craft()->db->createCommand()->update('commerce_orderstatuses',
				['sortOrder' => $sortOrder + 1], ['id' => $id]);
		}

		return true;
	}

	/**
	 * Sends an email
	 *
	 * @param Commerce_EmailModel        $email
	 * @param Commerce_OrderModel        $order
	 * @param Commerce_OrderHistoryModel $update
	 */
	private function _sendStatusChangeEmail($email, $order, $update)
	{

		// Set Craft to the site template mode
		$templatesService = craft()->templates;
		$oldTemplateMode = $templatesService->getTemplateMode();
		$templatesService->setTemplateMode(TemplateMode::Site);

		//sending emails
		$renderVariables = [
			'order'  => $order,
			'update' => $update
		];

		$newEmail = new EmailModel();

		if (craft()->commerce_settings->getSettings()->emailSenderAddress)
		{
			$newEmail->fromEmail = craft()->commerce_settings->getSettings()->emailSenderAddress;
		}

		if (craft()->commerce_settings->getSettings()->emailSenderName)
		{
			$newEmail->fromName = craft()->commerce_settings->getSettings()->emailSenderName;
		}

		if ($email->recipientType == Commerce_EmailRecord::TYPE_CUSTOMER)
		{
			if ($order->getCustomer())
			{
				$newEmail->toEmail = $order->getCustomer()->email;
			}
			else
			{
				$newEmail->toEmail = $order->email;
			}

			// If we can't get an email, we can't send an email to the customer.
			if (empty($newEmail->toEmail))
			{
				return;
			}
		}

		if ($email->recipientType == Commerce_EmailRecord::TYPE_CUSTOM)
		{
			// To:
			try
			{
				$newEmail->toEmail = $templatesService->renderString($email->to, $renderVariables);
			}
			catch (\Exception $e)
			{
				$error = Craft::t('Email template parse error for custom email “{email}” in “To:”. Order: “{order}”. Template error: “{message}”',
					['email' => $email->name, 'order' => $order->getShortNumber(), 'message' => $e->getMessage()]);
				CommercePlugin::log($error, LogLevel::Error, true);

				$templatesService->setTemplateMode($oldTemplateMode);

				return;
			}
		}


		// BCC:
		try
		{
			$bcc = $templatesService->renderString($email->bcc, $renderVariables);
			$bcc = str_replace(';', ',', $bcc);
			$bcc = explode(',', $bcc);
			$bccEmails = [];
			foreach ($bcc as $bccEmail)
			{
				$bccEmails[] = ['email' => $bccEmail];
			}
			$newEmail->bcc = $bccEmails;
		}
		catch (\Exception $e)
		{
			$error = Craft::t('Email template parse error for email “{email}” in “BCC:”. Order: “{order}”. Template error: “{message}”',
				['email' => $email->name, 'order' => $order->getShortNumber(), 'message' => $e->getMessage()]);
			CommercePlugin::log($error, LogLevel::Error, true);

			$templatesService->setTemplateMode($oldTemplateMode);

			return;
		}

		// Subject:
		try
		{
			$newEmail->subject = $templatesService->renderString($email->subject, $renderVariables);
		}
		catch (\Exception $e)
		{
			$error = Craft::t('Email template parse error for email “{email}” in “Subject:”. Order: “{order}”. Template error: “{message}”',
				['email' => $email->name, 'order' => $order->getShortNumber(), 'message' => $e->getMessage()]);
			CommercePlugin::log($error, LogLevel::Error, true);

			$templatesService->setTemplateMode($oldTemplateMode);

			return;
		}

		// Email Body
		if (!$templatesService->doesTemplateExist($email->templatePath))
		{
			$error = Craft::t('Email template does not exist at “{templatePath}” for email “{email}”. Order: “{order}”.',
				['templatePath' => $email->templatePath, 'email' => $email->name, 'order' => $order->getShortNumber()]);
			CommercePlugin::log($error, LogLevel::Error, true);

			$templatesService->setTemplateMode($oldTemplateMode);

			return;
		}
		else
		{
			try
			{
				$newEmail->body = $newEmail->htmlBody = $templatesService->render($email->templatePath,
					$renderVariables);
			}
			catch (\Exception $e)
			{
				$error = Craft::t('Email template parse error for email “{email}”. Order: “{order}”. Template error: “{message}”',
					['email' => $email->name, 'order' => $order->getShortNumber(), 'message' => $e->getMessage()]);
				CommercePlugin::log($error, LogLevel::Error, true);

				$templatesService->setTemplateMode($oldTemplateMode);

				return;
			}
		}

		craft()->plugins->callFirst('commerce_modifyEmail', [&$newEmail, $order]);

		try
		{
			if (!craft()->email->sendEmail($newEmail))
			{
				$error = Craft::t('Email “{email}” could not be sent for order “{order}”. Errors: {errors}',
					['errors' => implode(", ", $email->getAllErrors()), 'email' => $email->name, 'order' => $order->getShortNumber()]);

				CommercePlugin::log($error, LogLevel::Error, true);
			}
		}
		catch (\Exception $e)
		{
			$error = Craft::t('Email “{email}” could not be sent for order “{order}”. Error: {error}',
				['error' => $e->getMessage(), 'email' => $email->name, 'order' => $order->getShortNumber()]);

			CommercePlugin::log($error, LogLevel::Error, true);
		}


		// Restore the original template mode
		$templatesService->setTemplateMode($oldTemplateMode);
	}

}
