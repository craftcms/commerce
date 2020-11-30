<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use Craft;

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
        if (!$this->db->columnExists('{{%commerce_pdfs}}', 'language')) {
            $this->addColumn('{{%commerce_pdfs}}', 'language', $this->string()->defaultValue('orderLanguage'));
        }

        if (!$this->db->columnExists('{{%commerce_emails}}', 'language')) {
            $this->addColumn('{{%commerce_emails}}', 'language', $this->string()->defaultValue('orderLanguage'));
        }


        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '3.2.14', '<')) {

            $emailUids = (new Query())
                ->select(['uid'])
                ->from('{{%commerce_emails}}')
                ->column();

            $pdfUids = (new Query())
                ->select(['uid'])
                ->from('{{%commerce_pdfs}}')
                ->column();

            foreach ($projectConfig->get('commerce.emails') ?? [] as $uid => $email) {
                if (in_array($uid, $emailUids, false)) {
                    $email['language'] = 'orderLanguage';
                    $projectConfig->set("commerce.emails.{$uid}", $email);
                }
            }

            foreach ($projectConfig->get('commerce.pdfs') ?? [] as $uid => $pdf) {
                if (in_array($uid, $pdfUids, false)) {
                    $pdf['language'] = 'orderLanguage';
                    $projectConfig->set("commerce.pdfs.{$uid}", $pdf);
                }
            }
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
