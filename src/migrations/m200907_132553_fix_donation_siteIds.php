<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m200907_132553_fix_donation_siteIds migration.
 */
class m200907_132553_fix_donation_siteIds extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $siteId = (new Query())
            ->select(['id'])
            ->from(['{{%sites}}'])
            ->where(['primary' => true])
            ->scalar();

        $donationIds = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_donations}}'])
            ->column();

        // Fix donation data that may have become out of sync
        if ($siteId && !empty($donationIds)) {
            $this->update('{{%elements_sites}}', ['siteId' => $siteId], ['elementId' => $donationIds], [], false);
            $this->update('{{%searchindex}}', ['siteId' => $siteId], ['elementId' => $donationIds], [], false);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200907_132553_fix_donation_siteIds cannot be reverted.\n";
        return false;
    }
}
