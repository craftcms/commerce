<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m201120_093135_add_language_setting_to_email_and_pdf migration.
 */
class m201120_093135_add_locale_setting_to_email_and_pdf extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_pdfs}}', 'locale')) {
            $this->addColumn('{{%commerce_pdfs}}', 'locale', $this->string()->defaultValue('localeCreated'));
        }        
        
        if (!$this->db->columnExists('{{%commerce_emails}}', 'locale')) {
            $this->addColumn('{{%commerce_emails}}', 'locale', $this->string()->defaultValue('localeCreated'));
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201120_093135_add_language_setting_to_email_and_pdf cannot be reverted.\n";
        return false;
    }
}
