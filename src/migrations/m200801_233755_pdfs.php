<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;

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
            ->select(['name','pdfTemplatePath'])
            ->from(['{{%commerce_emails}}'])
            ->all();

        $schema = $this->db->getSchema();
        $schema->refresh();

        $rawTableName = $schema->getRawTableName('{{%commerce_pdfs}}');
        $table = $schema->getTableSchema($rawTableName);

        if(!$table) {
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

//        $this->createTable('{{%commerce_emails_pdfs}}', [
//            'id' => $this->primaryKey(),
//            'emailId' => $this->integer(),
//            'pdfId' => $this->string()->notNull(),
//            'dateCreated' => $this->dateTime()->notNull(),
//            'dateUpdated' => $this->dateTime()->notNull(),
//            'uid' => $this->uid(),
//        ]);

//        foreach ($emailPdfTemplates as $template)
//        {
//            $this->getDb()->createCommand()
//                ->insert('{{%commerce_pdfs}}',[
//                    'name' => $template['name'],
//                    'templatePath' => $template['pdfTemplatePath'],
//                ])->execute();
//
//            $id = $this->getDb()->getLastInsertID();
//        }
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
