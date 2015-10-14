<?php
namespace Craft;

use Market\Helpers\MarketDbHelper;

/**
 * Class Market_OrderStatusService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_OrderStatusService extends BaseApplicationComponent
{
    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return Market_OrderStatusModel[]
     */
    public function getAll($criteria = [])
    {
        $orderStatusRecords = Market_OrderStatusRecord::model()->findAll($criteria);

        return Market_OrderStatusModel::populateModels($orderStatusRecords);
    }

    /**
     * @param string $handle
     *
     * @return Market_OrderStatusModel
     */
    public function getByHandle($handle)
    {
        $orderStatusRecord = Market_OrderStatusRecord::model()->findByAttributes(['handle' => $handle]);

        return Market_OrderStatusModel::populateModel($orderStatusRecord);
    }

    /**
     * Get default order status from the DB
     *
     * @return Market_OrderStatusModel
     */
    public function getDefault()
    {
        $orderStatus = Market_OrderStatusRecord::model()->findByAttributes(['default'=>true]);

        return Market_OrderStatusModel::populateModel($orderStatus);
    }

    /**
     * @param Market_OrderStatusModel $model
     * @param array $emailsIds
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function save(Market_OrderStatusModel $model, array $emailsIds)
    {
        if ($model->id) {
            $record = Market_OrderStatusRecord::model()->findById($model->id);
            if (!$record->id) {
                throw new Exception(Craft::t('No order status exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Market_OrderStatusRecord();
        }

        $record->name        = $model->name;
        $record->handle      = $model->handle;
        $record->color       = $model->color;
        $record->default     = $model->default;

        $record->validate();
        $model->addErrors($record->getErrors());

        //validating emails ids
        $criteria = new \CDbCriteria();
        $criteria->addInCondition('id', $emailsIds);
        $exist     = Market_EmailRecord::model()->exists($criteria);
        $hasEmails = (boolean)count($emailsIds);

        if (!$exist && $hasEmails) {
            $model->addError('emails',
                'One or more emails do not exist in the system.');
        }

        //saving
        if (!$model->hasErrors()) {
            MarketDbHelper::beginStackedTransaction();
            try {
                //only one default status can be among statuses of one order type
                if ($record->default) {
                    Market_OrderStatusRecord::model()->updateAll(['default' => 0]);
                }

                // Save it!
                $record->save(false);

                //Delete old links
                if ($model->id) {
                    Market_OrderStatusEmailRecord::model()->deleteAllByAttributes(['orderStatusId' => $model->id]);
                }

                //Save new links
                $rows  = array_map(function ($id) use ($record) {
                    return [$id, $record->id];
                }, $emailsIds);
                $cols  = ['emailId', 'orderStatusId'];
                $table = Market_OrderStatusEmailRecord::model()->getTableName();
                craft()->db->createCommand()->insertAll($table, $cols, $rows);

                // Now that we have a calendar ID, save it on the model
                $model->id = $record->id;

                MarketDbHelper::commitStackedTransaction();
            } catch (\Exception $e) {
                MarketDbHelper::rollbackStackedTransaction();
                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     */
    public function deleteById($id)
    {
        Market_OrderStatusRecord::model()->deleteByPk($id);
    }

    /**
     * Handler for order status change event
     *
     * @param Event $event
     *
     * @throws Exception
     */
    public function statusChangeHandler(Event $event)
    {
        /** @var Market_OrderModel $order */
        $order = $event->params['order'];

        if (!$order->orderStatusId) {
            return;
        }

        $status = craft()->market_orderStatus->getById($order->orderStatusId);
        if (!$status || !$status->emails) {
            MarketPlugin::log("Can't send email if no status or emails exist.",
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

        foreach ($status->emails as $email) {
            if($email->enabled == true){
                $craftEmail = new EmailModel();

                if(craft()->market_settings->getSettings()->emailSenderAddress){
                    $craftEmail->fromEmail = craft()->market_settings->getSettings()->emailSenderAddress;
                }

                if(craft()->market_settings->getSettings()->emailSenderName){
                    $craftEmail->fromName = craft()->market_settings->getSettings()->emailSenderName;
                }

                $craftEmail->toEmail = $to = craft()->templates->renderString($email->to, $renderVariables);
                $craftEmail->bcc     = [['email'=>craft()->templates->renderString($email->bcc, $renderVariables)]];
                $craftEmail->subject = craft()->templates->renderString($email->subject, $renderVariables);

                $body              = $email->type == Market_EmailRecord::TYPE_HTML ? 'htmlBody' : 'body';
                $craftEmail->$body = craft()->templates->render($email->templatePath,
                    $renderVariables);

                if (!craft()->email->sendEmail($craftEmail)) {
                    throw new Exception('Email sending error: ' . implode(', ',
                            $email->getAllErrors()));
                }

                //logging
                $log = sprintf('Order #%d got new status "%s". Email "%s" %d was sent to %s',
                    $order->id, $order->orderStatus, $email->name, $email->id, $to);
                MarketPlugin::log($log, LogLevel::Info, true);
            }
        }

        //put old template path back
        craft()->path->setTemplatesPath($oldPath);
    }

    /**
     * @param int $id
     *
     * @return Market_OrderStatusModel
     */
    public function getById($id)
    {
        $orderStatusRecord = Market_OrderStatusRecord::model()->findById($id);

        return Market_OrderStatusModel::populateModel($orderStatusRecord);
    }

}