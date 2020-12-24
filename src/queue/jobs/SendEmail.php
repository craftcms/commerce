<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\errors\EmailException;
use craft\commerce\helpers\Locale;
use craft\commerce\Plugin;
use craft\queue\BaseJob;

class SendEmail extends BaseJob
{
    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var array Order Data at time of order status change
     */
    public $orderData;

    /**
     * @var int The commerce email ID
     */
    public $commerceEmailId;

    /**
     * @var int the order history ID
     */
    public $orderHistoryId;


    public function execute($queue)
    {
        $this->setProgress($queue, 0.2);

        $order = Order::find()->id($this->orderId)->one();
        $email = Plugin::getInstance()->getEmails()->getEmailById($this->commerceEmailId);
        $orderHistory = Plugin::getInstance()->getOrderHistories()->getOrderHistoryById($this->orderHistoryId);

        $language = $email->getRenderLanguage($order);
        Locale::switchAppLanguage($language);

        $this->setProgress($queue, 0.5);

        $error = '';
        if (!Plugin::getInstance()->getEmails()->sendEmail($email, $order, $orderHistory, $this->orderData, $error)) {
            throw new EmailException($error);
        }

        $this->setProgress($queue, 1);
    }


    protected function defaultDescription(): string
    {
        return 'Sending email for order #' . $this->orderId;
    }
}
