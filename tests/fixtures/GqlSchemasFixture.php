<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\db\Table;
use craft\db\Query;
use craft\records\GqlSchema;
use craft\test\ActiveFixture;

/**
 * Class GqlSchemasFixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 5.0.7
 */
class GqlSchemasFixture extends ActiveFixture
{
    /**
     * @inheritdoc
     */
    public $modelClass = GqlSchema::class;

    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/gql-schemas.php';

    /**
     * @inheritdoc
     */
    public $depends = [
        ProductFixture::class,
    ];

    /**
     * @inheritdoc
     */
    protected function loadData($file, $throwException = true)
    {
        $file = parent::loadData($file, $throwException);
        $productTypeUids = (new Query())->select('uid')->from(Table::PRODUCTTYPES)->column();
        $siteUids = (new Query())->select('uid')->from(\craft\db\Table::SITES)->column();

        foreach ($file as &$row) {
            if (!isset($row['scope'])) {
                continue;
            }

            foreach ($siteUids as $siteUid) {
                $row['scope'][] = 'sites.' . $siteUid . ':read';
            }

            foreach ($productTypeUids as $typeUid) {
                $row['scope'][] = 'productTypes.' . $typeUid . ':read';
            }
        }

        return $file;
    }
}
