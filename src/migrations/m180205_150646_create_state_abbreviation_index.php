<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;

/**
 * m180205_150646_create_state_abbreviation_index migration.
 */
class m180205_150646_create_state_abbreviation_index extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // name,countryId => countryId,name
        MigrationHelper::dropIndexIfExists('{{%commerce_states}}', ['name', 'countryId'], true, $this);
        $this->createIndex(null, '{{%commerce_states}}', ['countryId', 'name'], true);

        // countryId,abbreviation
        $this->createIndex(null, '{{%commerce_states}}', ['countryId', 'abbreviation'], true);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180205_150646_create_state_abbreviation_index cannot be reverted.\n";
        return false;
    }
}
