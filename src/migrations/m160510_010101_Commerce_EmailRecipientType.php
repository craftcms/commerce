<?php
namespace Craft;

class m160510_010101_Commerce_EmailRecipientType extends BaseMigration
{
	public function safeUp()
	{
		// Alter to allow nulls
		$this->alterColumn('commerce_emails','to',AttributeType::String);

		// Add recipientType
		$this->addColumnAfter('commerce_emails','recipientType',"enum('customer', 'custom') NOT NULL DEFAULT 'customer'",'subject');

		craft()->db->createCommand()->update('commerce_emails', ['recipientType' => 'custom']);
	}
}
