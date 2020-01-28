<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191113_111954_add_plain_text_template_path migration.
 */
class m191113_111954_add_plain_text_template_path extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_emails}}', 'plainTextTemplatePath', $this->string());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191113_111954_add_plain_text_template_path cannot be reverted.\n";
        return false;
    }
}
