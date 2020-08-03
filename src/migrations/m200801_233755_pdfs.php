<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\commerce\services\Pdfs;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\StringHelper;
use yii\db\Expression;

/**
 * m200801_233755_pdfs migration.
 */
class m200801_233755_pdfs extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $emailPdfTemplates = (new Query())
            ->select(['*'])
            ->from(['{{%commerce_emails}}'])
            ->where(['attachPdf' => true])
            ->all();

        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName('{{%commerce_pdfs}}');
        $table = $schema->getTableSchema($rawTableName);

        if (!$table) {
            $this->createTable('{{%commerce_pdfs}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string()->notNull(),
                'handle' => $this->string()->notNull(),
                'description' => $this->string(),
                'templatePath' => $this->string()->notNull(),
                'enabled' => $this->boolean(),
                'isDefault' => $this->boolean(),
                'sortOrder' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        if ($this->db->columnExists('{{%commerce_emails}}', 'attachPdf')) {
            $this->dropColumn('{{%commerce_emails}}', 'attachPdf');
        }

        if ($this->db->columnExists('{{%commerce_emails}}', 'pdfTemplatePath')) {
            $this->dropColumn('{{%commerce_emails}}', 'pdfTemplatePath');
        }

        if (!$this->db->columnExists('{{%commerce_emails}}', 'pdfId')) {
            $this->addColumn('{{%commerce_emails}}', 'pdfId', );
        }

        $emailPdfUids = [];
        foreach ($emailPdfTemplates as $key => $email) {
            $emailPdfUids[$key] = StringHelper::UUID();
        }

        // Don't make the same config changes twice...
        $projectConfig = \Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '3.2.1', '>=')) {
            return;
        }

        $sortOrder = 0;

        // Migrate the settings for default PDF setting to a real PDF record
        $defaultUid = StringHelper::UUID();
        $defaultPdf = [
            'name' => 'Default',
            'handle' => 'order',
            'description' => 'Default Order PDF',
            'templatePath' => Plugin::getInstance()->getSettings()->orderPdfPath,
            'fileNameFormat' => Plugin::getInstance()->getSettings()->orderPdfFilenameFormat,
            'isDefault' => true,
            'enabled' => true,
            'sortOrder' => $sortOrder++,
            'uid' => $defaultUid// Doesnt need to be related to anything like the email ones below
        ];

        // set the default pdf from setting in project config
        $configPath = Pdfs::CONFIG_PDFS_KEY . '.' . $defaultUid;
        $projectConfig->set($configPath, $data);

        // Create the PDF in project config for each email that has a PDF template
        foreach ($emailPdfTemplates as $key => $email) {
            $templatePath = $email['pdfTemplatePath'];

            // If the email had attachPdf set to true, but had no pdf template path, then we need to use the default one from settings.
            if(empty($templatePath) || !$templatePath){
                $templatePath = Plugin::getInstance()->getSettings()->orderPdfPath;
            }
            $configData = [
                'name' => $email['name'] . 'PDF',
                'handle' => StringHelper::toCamelCase($email['name']),
                'description' => $email['name'],
                'templatePath' => $templatePath,
                'fileNameFormat' => $email['fileNameFormat'],
                'enabled' => true,
                'sortOrder' => $sortOrder++,
                'isDefault' => false,
                'uid' => $emailPdfUids[$key]
            ];

            $configPath = Pdfs::CONFIG_PDFS_KEY . '.' . $configData['uid'];
            $projectConfig->set($configPath, $data);
        }

        // Update all emails that had a pdf template with the related uid
        foreach ($emailPdfTemplates as $key => $email) {
            $data = [
                'name' => $email['name'],
                'subject' => $email['subject'],
                'recipientType' => $email['recipientType'],
                'to' => $email['to'],
                'bcc' => $email['bcc'],
                'cc' => $email['cc'],
                'replyTo' => $email['replyTo'],
                'enabled' => $email['enabled'],
                'templatePath' => $email['templatePath'],
                'plainTextTemplatePath' => $email['plainTextTemplatePath'],
                'pdfUid' => $emailPdfUids[$key], // uid generated from this migration
                'uid' => $email['uid']
            ];

            $configPath = Pdfs::CONFIG_PDFS_KEY . '.' . $email['uid'];
            $projectConfig->set($configPath, $data);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200801_233755_pdfs cannot be reverted.\n";
        return false;
    }
}
