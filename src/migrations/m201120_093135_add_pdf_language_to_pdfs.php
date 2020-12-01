<?php

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m201120_093135_add_pdf_language_to_pdfs migration.
 */
class m201120_093135_add_pdf_language_to_pdfs extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_pdfs}}', 'pdfLanguage')) {
            $this->addColumn('{{%commerce_pdfs}}', 'pdfLanguage', $this->string());
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201120_093135_add_pdf_language_to_pdfs cannot be reverted.\n";
        return false;
    }
}
