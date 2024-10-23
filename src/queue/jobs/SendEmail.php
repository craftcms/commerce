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
use yii\base\InvalidConfigException;
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
     * @var Order|null
     */
    private ?Order $_order = null;


    /**
     * @inheritDoc
     */
    public function execute($queue): void
    {
        $this->setProgress($queue, 0.2);

        if (!$this->_getOrder()) {
            throw new InvalidConfigException('Invalid order ID: ' . $this->orderId);
        }

        $email = Plugin::getInstance()->getEmails()->getEmailById($this->commerceEmailId, $this->_getOrder()->getStore()->id);
        if (!$email) {
            throw new InvalidConfigException('Invalid email ID: ' . $this->commerceEmailId);
        }

        $originalLanguage = Craft::$app->language;
        $originalFormattingLocale = Craft::$app->formattingLocale;

        $orderHistory = Plugin::getInstance()->getOrderHistories()->getOrderHistoryById($this->orderHistoryId);

        $language = $email->getRenderLanguage($this->_getOrder());
        Locale::switchAppLanguage($language);

        $this->setProgress($queue, 0.5);

        $error = '';
        if (!Plugin::getInstance()->getEmails()->sendEmail($email, $this->_getOrder(), $orderHistory, $this->orderData, $error)) {
            throw new EmailException($error);
        }

        // Set previous language back
        Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale->id);

        $this->setProgress($queue, 1);
    }

    /**
     * @return Order|null
     */
    private function _getOrder(): ?Order
    {
        if ($this->_order === null) {
            $this->_order = Order::find()->id($this->orderId)->one();
        }

        return $this->_order;
    }

    /**
     * @return int
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
     *
     */
    protected function defaultDescription(): ?string
    {
        return 'Sending email for order ' . $this->_getOrder()?->reference;
    }
}
