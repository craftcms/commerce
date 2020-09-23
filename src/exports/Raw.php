<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\exports;

use craft\elements\exporters\Raw as CraftRaw;
use craft\commerce\elements\db\OrderQuery;
use craft\elements\db\ElementQueryInterface;

/**
 * Raw represents a "Raw data" order element exporter.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class Raw extends CraftRaw
{
    /**
     * @inheritdoc
     */
    public function export(ElementQueryInterface $query): array
    {
        // We don't want the manually eager loaded things (in query populate()) included with the raw export for orders
        // 'withAll' is true for the paginated page query, but we need to remove it for the raw export so it's fast
        /** @var OrderQuery $query */
        $query = $query->withAll(false);

        return parent::export($query);
    }
}