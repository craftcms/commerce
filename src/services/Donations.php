<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\events\SiteEvent;
use craft\helpers\Db;
use yii\base\Component;

/**
 * Donations service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class Donations extends Component
{
    /**
     * Handle the changing of the primary site
     *
     * @param SiteEvent $event
     * @throws \yii\db\Exception
     */
    public function afterChangePrimarySiteHandler(SiteEvent $event)
    {
        if (!$event->site) {
            return;
        }

        $siteId = $event->site->id;
        $donationIds = (new Query())
            ->select(['id'])
            ->from([Table::DONATIONS])
            ->column();

        if (empty($donationIds)) {
            return;
        }

        // Now swap the sites
        $updateColumns = ['siteId' => $siteId];
        $updateCondition = ['elementId' => $donationIds];

        Db::update(CraftTable::ELEMENTS_SITES, $updateColumns, $updateCondition, [], false);
        Db::update(CraftTable::SEARCHINDEX, $updateColumns, $updateCondition, [], false);
    }
}