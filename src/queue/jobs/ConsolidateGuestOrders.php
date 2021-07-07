<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\queue\BaseJob;

/**
 * ConsolidateGuestOrders job
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class ConsolidateGuestOrders extends BaseJob
{
    /**
     * @var array
     */
    public $emails;

    /**
     * @var
     */
    private $_queue;

    /**
     * @inheritDoc
     */
    public function execute($queue)
    {
        $this->_queue = $queue;

        $total = count($this->emails);

        $step = 1;

        foreach ($this->emails as $email) {
            $this->setProgress($this->_queue, $step / $total, Craft::t('commerce', 'Email {step} of {total}', compact('step', 'total')));
            try {
                Plugin::getInstance()->getCustomers()->consolidateGuestOrdersByEmail($email);
            } catch (\Throwable $e) {
                Craft::warning('Could not consolidate orders for guest email' . $email, 'commerce');
            }

            $step++;
        }

        $this->setProgress($queue, $step / $total, Craft::t('commerce', 'Purging orphaned customers.'));
        Plugin::getInstance()->getCustomers()->purgeOrphanedCustomers();
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('commerce', 'Consolidate all guest orders.');
    }
}
