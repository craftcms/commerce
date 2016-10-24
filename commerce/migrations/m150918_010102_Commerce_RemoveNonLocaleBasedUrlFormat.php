<?php
namespace Craft;

class m150918_010102_Commerce_RemoveNonLocaleBasedUrlFormat extends BaseMigration
{
    public function safeUp()
    {
        $table = craft()->db->schema->getTable('commerce_producttypes');
        if (isset($table->columns['urlFormat'])) {
            $this->dropColumn('commerce_producttypes', 'urlFormat');
        }

        return true;
    }
}