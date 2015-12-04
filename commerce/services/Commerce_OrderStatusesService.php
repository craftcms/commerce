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

        if ($result) {
            return Commerce_OrderStatusModel::populateModel($result);
        }

        return null;
    }

    /**
     * Get default order status from the DB
     *
     * @return Commerce_OrderStatusModel|null
     */
    public function getDefaultOrderStatus()
    {
        $result = Commerce_OrderStatusRecord::model()->findByAttributes(['default' => true]);

        if ($result) {
            return Commerce_OrderStatusModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param Commerce_OrderStatusModel $model
     * @param array $emailIds
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveOrderStatus(Commerce_OrderStatusModel $model, array $emailIds)
    {
        if ($model->id) {
            $record = Commerce_OrderStatusRecord::model()->findById($model->id);
            if (!$record->id) {
                throw new Exception(Craft::t('No order status exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
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
        $criteria->addInCondition('id', $emailIds);
        $exist = Commerce_EmailRecord::model()->exists($criteria);
        $hasEmails = (boolean)count($emailIds);

        if (!$exist && $hasEmails) {
            $model->addError('emails',
                'One or more emails do not exist in the system.');
        }

        //saving
        if (!$model->hasErrors()) {
            CommerceDbHelper::beginStackedTransaction();
            try {
                //only one default status can be among statuses of one order type
                if ($record->default) {
                    Commerce_OrderStatusRecord::model()->updateAll(['default' => 0]);
                }

                // Save it!
                $record->save(false);

                //Delete old links
                if ($model->id) {
                    Commerce_OrderStatusEmailRecord::model()->deleteAllByAttributes(['orderStatusId' => $model->id]);
                }

                //Save new links
                $rows = array_map(function ($id) use ($record) {
                    return [$id, $record->id];
                }, $emailIds);
                $cols = ['emailId', 'orderStatusId'];
                $table = Commerce_OrderStatusEmailRecord::model()->getTableName();
                craft()->db->createCommand()->insertAll($table, $cols, $rows);

                // Now that we have a calendar ID, save it on the model
                $model->id = $record->id;

                CommerceDbHelper::commitStackedTransaction();
            } catch (\Exception $e) {
                CommerceDbHelper::rollbackStackedTransaction();
                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int
     * @return bool
     */
    public function deleteOrderStatusById($id)
    {
        $statuses = $this->getAllOrderStatuses();

        $criteria = craft()->elements->getCriteria('Commerce_Order');
        $criteria->orderStatusId = $id;
        $order = $criteria->first();

        if($order){
            return false;
        }

        if (count($statuses) >= 2) {
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
    public function statusChangeHandler(Event $event)
    {
        /** @var Commerce_OrderModel $order */
        $order = $event->params['order'];

        if (!$order->orderStatusId) {
            return;
        }

        $status = craft()->commerce_orderStatuses->getOrderStatusById($order->orderStatusId);
        if (!$status || !$status->emails) {
            CommercePlugin::log("Can't send email if no status or emails exist.",
                LogLevel::Info, true);

            return;
        }

        //sending emails
        $renderVariables = [
            'order' => $order,
            'update' => $event->params['orderHistory'],
        ];

        //substitute templates path
        $oldPath = craft()->path->getTemplatesPath();
        $newPath = craft()->path->getSiteTemplatesPath();
        craft()->path->setTemplatesPath($newPath);

        foreach ($status->emails as $email) {
            $craftEmail = new EmailModel();

            if (craft()->commerce_settings->getSettings()->emailSenderAddress) {
                $craftEmail->fromEmail = craft()->commerce_settings->getSettings()->emailSenderAddress;
            }

            if (craft()->commerce_settings->getSettings()->emailSenderName) {
                $craftEmail->fromName = craft()->commerce_settings->getSettings()->emailSenderName;
            }

            $craftEmail->toEmail = $to = craft()->templates->renderString($email->to,
                $renderVariables);
            $craftEmail->bcc = [['email' => craft()->templates->renderString($email->bcc, $renderVariables)]];
            $craftEmail->subject = craft()->templates->renderString($email->subject,
                $renderVariables);

            if (!craft()->templates->doesTemplateExist($email->templatePath)) {
                $error = Craft::t('Email template does not exist at “{templatePath}” for email “email”. Attempting to send blank email. Order “{order}”.',
                    ['templatePath' => $email->templatePath, 'email' => $email->name, 'order' => $order->getShortNumber()]);
                CommercePlugin::log($error, LogLevel::Error, true);
                $craftEmail->body = $craftEmail->htmlBody = "";
            }else{
                $craftEmail->body = $craftEmail->htmlBody = craft()->templates->render($email->templatePath,
                    $renderVariables);
            }

            craft()->plugins->callFirst('commerce_modifyEmail', [&$craftEmail, $order]);

            if (!craft()->email->sendEmail($craftEmail)) {
                $error = Craft::t('Email “email” could not be sent for “{order}”. Errors: {errors}',
                    ['errors' => implode(" ",$email->getAllErrors()), 'email' => $email->name, 'order' => $order->getShortNumber()]);

                CommercePlugin::log($error, LogLevel::Error, true);
            }else{
                $log = sprintf('Order #%d got new status "%s". Email "%s" %d was sent to %s',
                    $order->id, $order->orderStatus, $email->name, $email->id, $to);
                CommercePlugin::log($log, LogLevel::Info, true);
            }
        }

        //put old template path back
        craft()->path->setTemplatesPath($oldPath);
    }

    /**
     * @param int $id
     *
     * @return Commerce_OrderStatusModel|null
     */
    public function getOrderStatusById($id)
    {
        $result = Commerce_OrderStatusRecord::model()->findById($id);

        if ($result) {
            return Commerce_OrderStatusModel::populateModel($result);
        }

        return null;
    }

}
