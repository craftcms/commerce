<?php
namespace Craft;

use Commerce\Helpers\CommerceDbHelper;

/**
 * Order status service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_OrderStatusService extends BaseApplicationComponent
{
	/**
	 * @param string $handle
	 *
	 * @return Commerce_OrderStatusModel
	 */
	public function getByHandle ($handle)
	{
		$orderStatusRecord = Commerce_OrderStatusRecord::model()->findByAttributes(['handle' => $handle]);

		return Commerce_OrderStatusModel::populateModel($orderStatusRecord);
	}

	/**
	 * Get default order status from the DB
	 *
	 * @return Commerce_OrderStatusModel
	 */
	public function getDefault ()
	{
		$orderStatus = Commerce_OrderStatusRecord::model()->findByAttributes(['default' => true]);

		return Commerce_OrderStatusModel::populateModel($orderStatus);
	}

	/**
	 * @param Commerce_OrderStatusModel $model
	 * @param array                   $emailsIds
	 *
	 * @return bool
	 * @throws Exception
	 * @throws \CDbException
	 * @throws \Exception
	 */
	public function save (Commerce_OrderStatusModel $model, array $emailsIds)
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
		$record->default = $model->default;

		$record->validate();
		$model->addErrors($record->getErrors());

		//validating emails ids
		$criteria = new \CDbCriteria();
		$criteria->addInCondition('id', $emailsIds);
		$exist = Commerce_EmailRecord::model()->exists($criteria);
		$hasEmails = (boolean)count($emailsIds);

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
				}, $emailsIds);
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
	 * @param int $id
	 */
	public function deleteById ($id)
	{
		$statuses = $this->getAll();

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
	public function getAll ($criteria = [])
	{
		$orderStatusRecords = Commerce_OrderStatusRecord::model()->findAll($criteria);

		return Commerce_OrderStatusModel::populateModels($orderStatusRecords);
	}

	/**
	 * Handler for order status change event
	 *
	 * @param Event $event
	 *
	 * @throws Exception
	 */
	public function statusChangeHandler (Event $event)
	{
		/** @var Commerce_OrderModel $order */
		$order = $event->params['order'];

		if (!$order->orderStatusId)
		{
			return;
		}

		$status = craft()->commerce_orderStatus->getById($order->orderStatusId);
		if (!$status || !$status->emails)
		{
			CommercePlugin::log("Can't send email if no status or emails exist.",
				LogLevel::Info, true);

			return;
		}

		//sending emails
		$renderVariables = [
			'order'  => $order,
			'update' => $event->params['orderHistory'],
		];

		//substitute templates path
		$oldPath = craft()->path->getTemplatesPath();
		$newPath = craft()->path->getSiteTemplatesPath();
		craft()->path->setTemplatesPath($newPath);

		foreach ($status->emails as $email)
		{
			$craftEmail = new EmailModel();

			if (craft()->commerce_settings->getSettings()->emailSenderAddress)
			{
				$craftEmail->fromEmail = craft()->commerce_settings->getSettings()->emailSenderAddress;
			}

			if (craft()->commerce_settings->getSettings()->emailSenderName)
			{
				$craftEmail->fromName = craft()->commerce_settings->getSettings()->emailSenderName;
			}

			$craftEmail->toEmail = $to = craft()->templates->renderString($email->to,
				$renderVariables);
			$craftEmail->bcc = craft()->templates->renderString($email->bcc,
				$renderVariables);
			$craftEmail->subject = craft()->templates->renderString($email->subject,
				$renderVariables);

			$craftEmail->$body = craft()->templates->render($email->templatePath,
				$renderVariables);

			if (!craft()->email->sendEmail($craftEmail))
			{
				throw new Exception('Email sending error: '.implode(', ',
						$email->getAllErrors()));
			}

			//logging
			$log = sprintf('Order #%d got new status "%s". Email "%s" %d was sent to %s',
				$order->id, $order->orderStatus, $email->name, $email->id, $to);
			CommercePlugin::log($log, LogLevel::Info, true);
		}

		//put old template path back
		craft()->path->setTemplatesPath($oldPath);
	}

	/**
	 * @param int $id
	 *
	 * @return Commerce_OrderStatusModel
	 */
	public function getById ($id)
	{
		$orderStatusRecord = Commerce_OrderStatusRecord::model()->findById($id);

		return Commerce_OrderStatusModel::populateModel($orderStatusRecord);
	}

}