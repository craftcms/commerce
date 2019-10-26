<?php
namespace craft\commerce\queue\jobs;

use craft\commerce\Plugin;
use craft\commerce\elements\Order;
use craft\commerce\models\Email;
use craft\commerce\models\OrderHistory;

use Craft;
use craft\queue\BaseJob;

use yii\base\Event;

class SendEmail extends BaseJob
{
    // Properties
    // =========================================================================

    public $email;
    public $order;
    public $orderHistory;


    // Public Methods
    // =========================================================================

    public function execute($queue)
    {
        $this->setProgress($queue, 1);

        // Fetch the current Order and Order History so we can get a correct object
        // but make sure to apply any serialised data saved at the time of this queue job generation.
        // Reason being is that data may have changed between job lodging and job execution
        // but we also need to send out service (and resulting emails) a proper object.
        // Don't forget that we're passing the data as an array, not an object.
        $order = Order::find()->id($this->order['id'])->one();

        // Remove line items and adjusters - maybe just for now. I feel like these won't change too much?
        unset($this->order['lineItems'], $this->order['orderAdjustments']);

        // Populate (overwrite) with the potentially newer info
        $order->setAttributes($this->order);

        // Just create new models
        $orderHistory = new OrderHistory($this->orderHistory);
        $email = new Email($this->email);

        Plugin::getInstance()->getEmails()->sendEmail($email, $order, $orderHistory);

        return false;
    }


    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): string
    {
        return 'Sending email for order #' . $this->order['id'];
    }
}
