<?php
namespace Craft;

/**
 * Email service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_EmailsService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Commerce_EmailModel|null
     */
    public function getEmailById($id)
    {
        $result = Commerce_EmailRecord::model()->findById($id);

        if ($result)
        {
            return Commerce_EmailModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param array $attr
     *
     * @return Commerce_EmailModel|null
     */
    public function getEmailByAttributes(array $attr)
    {
        $result = Commerce_EmailRecord::model()->findByAttributes($attr);

        if ($result)
        {
            return Commerce_EmailModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return Commerce_EmailModel[]
     */
    public function getAllEmails($criteria = [])
    {
        $records = Commerce_EmailRecord::model()->findAll($criteria);

        return Commerce_EmailModel::populateModels($records);
    }

    /**
     * @param Commerce_EmailModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveEmail(Commerce_EmailModel $model)
    {
        if ($model->id)
        {
            $record = Commerce_EmailRecord::model()->findById($model->id);

            if (!$record)
            {
                throw new Exception(Craft::t('No email exists with the ID “{id}”', ['id' => $model->id]));
            }
        }
        else
        {
            $record = new Commerce_EmailRecord();
        }

        $record->name = $model->name;
        $record->subject = $model->subject;
        $record->recipientType = $model->recipientType;
        $record->to = $model->to;
        $record->bcc = $model->bcc;
        $record->enabled = $model->enabled;
        $record->templatePath = $model->templatePath;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors())
        {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * @param int $id
     *
     * @throws \CDbException
     */
    public function deleteEmailById($id)
    {
        Commerce_EmailRecord::model()->deleteByPk($id);
    }

    /**
     * Sends a commerce email
     *
     * @param Commerce_EmailModel        $email
     * @param Commerce_OrderModel        $order
     * @param Commerce_OrderHistoryModel $orderHistory
     */
    public function sendEmail($email, $order, $orderHistory)
    {

        if (!$email->enabled)
        {
            return;
        }

        // Set Craft to the site template mode
        $templatesService = craft()->templates;
        $oldTemplateMode = $templatesService->getTemplateMode();
        $templatesService->setTemplateMode(TemplateMode::Site);

        //sending emails
        $renderVariables = ['order'        => $order,
                            'update'       => $orderHistory, // TODO: Remove and deprecate 'update' variable in 2.0
                            'orderHistory' => $orderHistory];

        $newEmail = new EmailModel();

        $originalLanguage = craft()->language;

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
            // use the order's language for template rendering the email fields and body.
            $orderLanguage = $order->orderLocale ? $order->orderLocale : $originalLanguage;
            craft()->setLanguage($orderLanguage);

            if ($order->getCustomer())
            {
                $newEmail->toEmail = $order->getCustomer()->email;
            }
            else
            {
                $newEmail->toEmail = $order->email;
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
                $error = Craft::t('Email template parse error for custom email “{email}” in “To:”. Order: “{order}”. Template error: “{message}”', ['email'   => $email->name,
                                                                                                                                                    'order'   => $order->getShortNumber(),
                                                                                                                                                    'message' => $e->getMessage()]);
                CommercePlugin::log($error, LogLevel::Error, true);

                craft()->setLanguage($originalLanguage);
                $templatesService->setTemplateMode($oldTemplateMode);

                return;
            }
        }

        if (empty($newEmail->toEmail))
        {
            $error = Craft::t('Email error. No email address found for order. Order: “{order}”', ['order' => $order->getShortNumber()]);
            CommercePlugin::log($error, LogLevel::Error, true);

            craft()->setLanguage($originalLanguage);
            $templatesService->setTemplateMode($oldTemplateMode);

            return;
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
            $error = Craft::t('Email template parse error for email “{email}” in “BCC:”. Order: “{order}”. Template error: “{message}”', ['email'   => $email->name,
                                                                                                                                          'order'   => $order->getShortNumber(),
                                                                                                                                          'message' => $e->getMessage()]);
            CommercePlugin::log($error, LogLevel::Error, true);

            craft()->setLanguage($originalLanguage);
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
            $error = Craft::t('Email template parse error for email “{email}” in “Subject:”. Order: “{order}”. Template error: “{message}”', ['email'   => $email->name,
                                                                                                                                              'order'   => $order->getShortNumber(),
                                                                                                                                              'message' => $e->getMessage()]);
            CommercePlugin::log($error, LogLevel::Error, true);

            craft()->setLanguage($originalLanguage);
            $templatesService->setTemplateMode($oldTemplateMode);

            return;
        }

        // Template Path
        try
        {
            $templatePath = $templatesService->renderString($email->templatePath, $renderVariables);
        }
        catch (\Exception $e)
        {
            $error = Craft::t('Email template path parse error for email “{email}” in “Template Path”. Order: “{order}”. Template error: “{message}”', ['email'   => $email->name,
                                                                                                                                                        'order'   => $order->getShortNumber(),
                                                                                                                                                        'message' => $e->getMessage()]);
            CommercePlugin::log($error, LogLevel::Error, true);

            craft()->setLanguage($originalLanguage);
            $templatesService->setTemplateMode($oldTemplateMode);

            return;
        }

        // Email Body
        if (!$templatesService->doesTemplateExist($templatePath))
        {
            $error = Craft::t('Email template does not exist at “{templatePath}” which resulted in “{templateParsedPath}” for email “{email}”. Order: “{order}”.', ['templatePath'        => $email->templatePath,
                                                                                                                                                                 'templateParsedPath'  => $templatePath,
                                                                                                                                                                 'email'               => $email->name,
                                                                                                                                                                 'order'               => $order->getShortNumber()]);
            CommercePlugin::log($error, LogLevel::Error, true);

            craft()->setLanguage($originalLanguage);
            $templatesService->setTemplateMode($oldTemplateMode);

            return;
        }
        else
        {
            try
            {
                $newEmail->body = $newEmail->htmlBody = $templatesService->render($templatePath, $renderVariables);
            }
            catch (\Exception $e)
            {
                $error = Craft::t('Email template parse error for email “{email}”. Order: “{order}”. Template error: “{message}”', ['email'   => $email->name,
                                                                                                                                    'order'   => $order->getShortNumber(),
                                                                                                                                    'message' => $e->getMessage()]);
                CommercePlugin::log($error, LogLevel::Error, true);

                craft()->setLanguage($originalLanguage);
                $templatesService->setTemplateMode($oldTemplateMode);

                return;
            }
        }

        craft()->plugins->callFirst('commerce_modifyEmail', [&$newEmail, $order]);

        try
        {
            //raising event
            $event = new Event($this, ['craftEmail'    => $newEmail,
                                       'commerceEmail' => $email,
                                       'order'         => $order,
                                       'orderHistory'  => $orderHistory]);
            $this->onBeforeSendEmail($event);

            if ($event->performAction == false)
            {
                $error = Craft::t('Email “{email}”, for order "{order}" was cancelled by plugin.', ['email' => $email->name,
                                                                                                    'order' => $order->getShortNumber()]);

                CommercePlugin::log($error, LogLevel::Info, true);

                return;
            }

            if (!craft()->email->sendEmail($newEmail))
            {
                $error = Craft::t('Email “{email}” could not be sent for order “{order}”. Errors: {errors}', ['errors' => implode(", ", $email->getAllErrors()),
                                                                                                              'email'  => $email->name,
                                                                                                              'order'  => $order->getShortNumber()]);

                CommercePlugin::log($error, LogLevel::Error, true);
            }
            else
            {
                //raising event
                $event = new Event($this, ['craftEmail'    => $newEmail,
                                           'commerceEmail' => $email,
                                           'order'         => $order,
                                           'orderHistory'  => $orderHistory]);
                $this->onSendEmail($event);
            }
        }
        catch (\Exception $e)
        {
            $error = Craft::t('Email “{email}” could not be sent for order “{order}”. Error: {error}', ['error' => $e->getMessage(),
                                                                                                        'email' => $email->name,
                                                                                                        'order' => $order->getShortNumber()]);

            CommercePlugin::log($error, LogLevel::Error, true);
        }


        // Restore original values
        craft()->setLanguage($originalLanguage);
        $templatesService->setTemplateMode($oldTemplateMode);
    }

    /**
     * Event: before sending email
     * Event params:    craftEmail(EmailModel)
     *                  commerceEmail(Commerce_EmailModel)
     *                  order(Commerce_OrderModel)
     *                  orderHistory(Commerce_OrderHistoryModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeSendEmail(\CEvent $event)
    {
        $params = $event->params;

        if (empty($params['craftEmail']) || !($params['craftEmail'] instanceof EmailModel))
        {
            throw new Exception('onBeforeSendEmail event requires "craftEmail" param with EmailModel instance');
        }

        if (empty($params['commerceEmail']) || !($params['commerceEmail'] instanceof Commerce_EmailModel))
        {
            throw new Exception('onBeforeSendEmail event requires "commerceEmail" param with Commerce_EmailModel instance');
        }

        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
        {
            throw new Exception('onBeforeSendEmail event requires "order" param with Commerce_OrderModel instance');
        }

        if (empty($params['orderHistory']) || !($params['orderHistory'] instanceof Commerce_OrderHistoryModel))
        {
            throw new Exception('onBeforeSendEmail event requires "orderHistory" param with Commerce_OrderHistoryModel instance');
        }

        $this->raiseEvent('onBeforeSendEmail', $event);
    }

    /**
     * Event: before sending email
     * Event params:    craftEmail(EmailModel)
     *                  commerceEmail(Commerce_EmailModel)
     *                  order(Commerce_OrderModel)
     *                  orderHistory(Commerce_OrderHistoryModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onSendEmail(\CEvent $event)
    {
        $params = $event->params;

        if (empty($params['craftEmail']) || !($params['craftEmail'] instanceof EmailModel))
        {
            throw new Exception('onSendEmail event requires "craftEmail" param with EmailModel instance');
        }

        if (empty($params['commerceEmail']) || !($params['commerceEmail'] instanceof Commerce_EmailModel))
        {
            throw new Exception('onSendEmail event requires "commerceEmail" param with Commerce_EmailModel instance');
        }

        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
        {
            throw new Exception('onSendEmail event requires "order" param with Commerce_OrderModel instance');
        }

        if (empty($params['orderHistory']) || !($params['orderHistory'] instanceof Commerce_OrderHistoryModel))
        {
            throw new Exception('onSendEmail event requires "orderHistory" param with Commerce_OrderHistoryModel instance');
        }

        $this->raiseEvent('onSendEmail', $event);
    }
}
