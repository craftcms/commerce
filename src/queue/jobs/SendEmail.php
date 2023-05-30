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
use yii\queue\RetryableJobInterface;

class SendEmail extends BaseJob implements RetryableJobInterface
{
    /**
     * @var int Order ID
     */
    public int $orderId;

    /**
     * @var array Order Data at time of order status change
     */
    public array $orderData;

    /**
     * @var int The commerce email ID
     */
    public int $commerceEmailId;

    /**
     * @var int the order history ID
     */
    public int $orderHistoryId;

    /**
     * @inheritDoc
     */
    public function execute($queue): void
    {
        $this->setProgress($queue, 0.2);

        $order = Order::find()->id($this->orderId)->one();
        $email = Plugin::getInstance()->getEmails()->getEmailById($this->commerceEmailId);
        $orderHistory = Plugin::getInstance()->getOrderHistories()->getOrderHistoryById($this->orderHistoryId);

        $originalLanguage = Craft::$app->language;
        $originalFormattingLocale = Craft::$app->formattingLocale;
        $language = $email->getRenderLanguage($order);
        Locale::switchAppLanguage($language);

        $this->setProgress($queue, 0.5);

        $error = '';
        if (!Plugin::getInstance()->getEmails()->sendEmail($email, $order, $orderHistory, $this->orderData, $error)) {
            throw new EmailException($error);
        }

        // Set previous language back
        Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale);

        $this->setProgress($queue, 1);
    }

    /**
     * @inheritDoc
     */
    public function getTtr(): int
    {
        return 60;
    }

    /**
     * @inheritDoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < 5;
    }

    /**
     * @inheritDoc
     */
    protected function defaultDescription(): ?string
    {
        return 'Sending email for order #' . $this->orderId;
    }
}
