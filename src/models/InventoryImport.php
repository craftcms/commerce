<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\helpers\Assets;
use yii\base\InvalidConfigException;

/**
 * Inventory Import model
 *
 * @since 5.2
 */
class InventoryImport extends Model
{
    /**
     * @var int The batch size for importing
     */
    public int $batchSize = 100;

    /**
     * @var string The path to the import file
     */
    public string $importFile;

    /**
     * @var string The path to the rejected import file
     */
    public string $rejectedFile;

    /**
     * @var array The rejected rows
     */
    public array $rejectedRows = [];

    /**
     * @var bool Whether the import is complete
     */
    public bool $importComplete = false;

    /**
     * @var int The total number of rows in the import file
     */
    public int $totalRows = 0;

    public function init(): void
    {
        // check the import file exists
        if (!file_exists($this->importFile)) {
            throw new InvalidConfigException('Import file does not exist');
        }

        if (!file_exists($this->rejectedFile)) {
            $this->rejectedFile = Assets::tempFilePath('csv');
        }

        parent::init();
    }
}
