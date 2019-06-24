<?php

namespace craft\commerce\queue\jobs;

use Craft;
use craft\commerce\Plugin;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;
use Throwable;
use yii\queue\Queue;

class SendEmailJob extends BaseJob
{

    /**
     * @param Queue|QueueInterface $queue The queue the job belongs to
     * @throws Throwable
     */
    public function execute($queue)
    {
        // Remove all incomplete carts older than a certain date in config.
        Plugin::getInstance()->getCarts()->purgeIncompleteCarts();
    }

    protected function defaultDescription()
    {
        return Craft::t('commerce', 'Purging carts');
    }
}