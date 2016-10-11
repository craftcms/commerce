<?php
namespace Craft;

class m160406_010101_Commerce_RemoveUnusedAuthorId extends BaseMigration
{
	public function safeUp()
	{
		MigrationHelper::dropForeignKeyIfExists('commerce_products',['authorId']);
		MigrationHelper::dropIndexIfExists('commerce_products',['authorId']);
        if (craft()->db->columnExists('commerce_products', 'authorId'))
        {
            $this->dropColumn('commerce_products', 'authorId');
        }
	}
}
