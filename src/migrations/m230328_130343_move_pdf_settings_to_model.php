<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\db\Migration;

/**
 * m230328_130343_move_pdf_settings_to_model migration.
 */
class m230328_130343_move_pdf_settings_to_model extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn(Table::PDFS, 'paperOrientation', $this->string()->defaultValue('portrait'));
        $this->addColumn(Table::PDFS, 'paperSize', $this->string()->defaultValue('letter'));

        $commerceConfig = Craft::$app->getConfig()->getConfigFromFile('commerce');

        if (empty($commerceConfig)) {
            return true;
        }

        $data = [
            'paperOrientation' => $commerceConfig['pdfPaperOrientation'] ?? 'portrait',
            'paperSize' => $commerceConfig['pdfPaperSize'] ?? 'letter',
        ];

        $this->update(Table::PDFS, $data);

        $projectConfig = Craft::$app->getProjectConfig();

        $pdfs = $projectConfig->get('commerce.pdfs') ?? [];
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        foreach ($pdfs as $uid => $pdf) {
            $projectConfig->set("commerce.pdfs.$uid", array_merge($pdf, $data));
        }

        $projectConfig->muteEvents = $muteEvents;

        return true;
    }


    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230328_130343_move_pdf_settings_to_model cannot be reverted.\n";
        return false;
    }
}
