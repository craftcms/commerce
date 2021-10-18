<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\Plugin;
use craft\commerce\services\Emails;
use craft\commerce\services\Pdfs;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\StringHelper;

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
        $projectConfig = Craft::$app->getProjectConfig();

        $orderPdfFilenameFormat = Plugin::getInstance()->getSettings()->getOrderPdfFilenameFormat(true);
        $orderPdfPath = Plugin::getInstance()->getSettings()->getOrderPdfPath(true);

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
                'fileNameFormat' => $this->string(),
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
            $this->addColumn('{{%commerce_emails}}', 'pdfId', $this->integer());
        }

        $emailPdfUids = [];
        foreach ($emailPdfTemplates as $key => $email) {
            $emailPdfUids[$key] = StringHelper::UUID();
        }

        // Don't make the same config changes twice...
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '3.2.2', '>=')) {
            return;
        }

        $sortOrder = 0;

        // Migrate the settings for default PDF setting to a real PDF record
        $defaultUid = StringHelper::UUID();
        $defaultPdf = [
            'name' => 'Default',
            'handle' => 'order',
            'description' => 'Default Order PDF',
            'templatePath' => $orderPdfPath,
            'fileNameFormat' => $orderPdfFilenameFormat,
            'isDefault' => true,
            'enabled' => true,
            'sortOrder' => $sortOrder++,
            'uid' => $defaultUid// Doesnt need to be related to anything like the email ones below
        ];

        // set the default pdf from setting in project config
        $configPath = Pdfs::CONFIG_PDFS_KEY . '.' . $defaultUid;
        $projectConfig->set($configPath, $defaultPdf);

        // Create the PDF in project config for each email that has a PDF template
        foreach ($emailPdfTemplates as $key => $email) {
            $templatePath = $email['pdfTemplatePath'];

            // If the email had attachPdf set to true, but had no pdf template path, then we need to use the default one from settings.
            if (empty($templatePath) || !$templatePath) {
                $templatePath = $orderPdfPath;
            }
            $configData = [
                'name' => $email['name'] . ' PDF',
                'handle' => StringHelper::toCamelCase($email['name']),
                'description' => $email['name'],
                'templatePath' => $templatePath,
                'fileNameFormat' => $orderPdfFilenameFormat,
                'enabled' => true,
                'sortOrder' => $sortOrder++,
                'isDefault' => false,
                'uid' => $emailPdfUids[$key],
            ];

            $configPath = Pdfs::CONFIG_PDFS_KEY . '.' . $configData['uid'];
            $projectConfig->set($configPath, $configData);
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
                'pdf' => $emailPdfUids[$key], // uid generated from this migration
                'uid' => $email['uid'],
            ];

            $configPath = Emails::CONFIG_EMAILS_KEY . '.' . $email['uid'];
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
