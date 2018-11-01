<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180718_161906_add_orderPdfAttachment migration.
 */
class m180718_161906_add_orderPdfAttachment extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_emails}}', 'attachPdf', $this->boolean());
        $this->addColumn('{{%commerce_emails}}', 'pdfTemplatePath', $this->string());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180718_161906_add_orderPdfAttachment cannot be reverted.\n";
        return false;
    }
}
