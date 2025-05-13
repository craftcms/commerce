<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\db;

use craft\commerce\elements\Donation;
use yii\db\Connection;

/**
 * DonationQuery represents a SELECT SQL statement for donations in a way that is independent of DBMS.
 *
 * @method Donation[]|array all($db = null)
 * @method Donation|array|null one($db = null)
 * @method Donation|array|null nth(int $n, Connection $db = null)
 * @method self status(array|string|null $value)
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 * @doc-path donations.md
 */
class DonationQuery extends PurchasableQuery
{
    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('commerce_donations');

        $this->query->select([
            'commerce_donations.id',
        ]);

        if ($this->sku) {
            $this->subQuery->andWhere(['commerce_donations.sku' => $this->sku]);
        }

        return parent::beforePrepare();
    }
}
