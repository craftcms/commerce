<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

use Craft;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\Plugin;
use craft\queue\BaseJob;
use League\Csv\Reader;
use yii\base\Exception;
use yii\queue\RetryableJobInterface;

/**
 * Import Inventory Job
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.4
 */
class ImportInventory extends BaseJob implements RetryableJobInterface
{
    /**
     * @var string The path to the temporary file
     */
    public string $filePath;

    /**
     * @inheritDoc
     */
    public function execute($queue): void
    {
        $this->setProgress($queue, 0.1);

        // Verify file exists
        if (!file_exists($this->filePath)) {
            throw new Exception('File not found: ' . $this->filePath);
        }

        // Check CSV file is OK and has required headers
        try {
            $csv = Reader::createFromPath($this->filePath);
            $csv->setHeaderOffset(0);
            $csv->setEscape(''); // required in PHP8.4+ to avoid deprecation notices
        } catch (\Exception $e) {
            throw new Exception('Invalid CSV file: ' . $e->getMessage());
        }

        // Check required headers are all there
        $headers = $csv->getHeader();
        if (!in_array('location', $headers) || !in_array('item', $headers) || !in_array('action', $headers) || !in_array('amount', $headers)) {
            throw new Exception('Invalid CSV file. Missing required headers.');
        }

        $this->setProgress($queue, 0.2);
        
        $inventoryService = Plugin::getInstance()->getInventory();
        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();
        $updateInventoryLevels = UpdateInventoryLevelCollection::make();
        $errors = [];
        $processedRecords = 0;
        $totalRecords = $csv->count();

        foreach ($csv->getRecords() as $key => $record) {
            $inventoryLocation = null;
            if (is_numeric($record['location'])) {
                $inventoryLocation = $inventoryLocations->firstWhere('id', $record['location']);
            } else {
                $inventoryLocation = $inventoryLocations->firstWhere('handle', $record['location']);
            }

            if (!$inventoryLocation) {
                $errors[$key][] = Craft::t('commerce', 'Invalid location: {error}', ['error' => $record['location']]);
                continue;
            }

            $item = null;
            if (is_numeric($record['item'])) {
                $item = $inventoryService->getInventoryItemById($record['item']);
            } else {
                $item = $inventoryService->getInventoryItemBySku($record['item']);
            }

            if ($item === null) {
                $errors[$key][] = Craft::t('commerce', 'Invalid item: {error}', ['error' => $record['item']]);
                continue;
            }

            $updateAction = $record['action'];

            if (!in_array($updateAction, ['set', 'adjust'])) {
                $errors[$key][] = Craft::t('commerce', 'Invalid action type: {error}', ['error' => $updateAction]);
                continue;
            }

            $amount = $record['amount'];

            if (!is_numeric($amount)) {
                $error = $record['amount'] ?: Craft::t('commerce', 'Missing');
                $errors[$key][] = Craft::t('commerce', 'Invalid amount: {error}', ['error' => $error]);
                continue;
            }

            $notes = $record['notes'] ?? '';

            // if no errors for this row, add it to collection
            if (empty($errors[$key])) {
                $update = new UpdateInventoryLevel();
                $update->inventoryLocationId = $inventoryLocation->id;
                $update->inventoryItemId = $item->id;
                $update->quantity = $amount;
                $update->note = $notes;
                $update->updateAction = InventoryUpdateQuantityType::from($updateAction);
                $update->type = InventoryTransactionType::AVAILABLE->value;
                $updateInventoryLevels->add($update);
            }
            
            $processedRecords++;
            $this->setProgress($queue, 0.2 + (0.7 * ($processedRecords / $totalRecords)));
        }

        if ($errors) {
            Craft::error('Inventory import had errors: ' . print_r($errors, true), __METHOD__);
            throw new Exception('Inventory import failed with ' . count($errors) . ' error(s).');
        }

        // Execute the inventory updates
        $inventoryService->executeUpdateInventoryLevels($updateInventoryLevels);

        @unlink($this->filePath);
        
        $this->setProgress($queue, 1);
    }

    /**
     * @inheritDoc
     */
    public function getTtr(): int
    {
        return 300; // 5 minutes
    }

    /**
     * @inheritDoc
     */
    public function canRetry($attempt, $error): bool
    {
        return $attempt < 3;
    }

    /**
     * @inheritDoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('commerce', 'Importing inventory data');
    }
}
