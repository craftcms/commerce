<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\db\Table;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\models\inventory\InventoryManualMovement;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\models\InventoryImport;
use craft\commerce\Plugin;
use craft\helpers\ArrayHelper;
use craft\web\Controller;
use craft\web\UploadedFile;
use League\Csv\Reader;
use League\Csv\Writer;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * Inventory Importexport controller
 */
class InventoryImportexportController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * commerce/inventory-importexport action
     */
    public function actionIndex(): Response
    {
        $params = [];

        return $this->asCpScreen()
            ->action('commerce/inventory-importexport/import-inventory')
            ->addCrumb(Craft::t('commerce', 'Inventory'), 'commerce/inventory')
            ->selectedSubnavItem('inventory')
            ->redirectUrl('commerce/inventory')
            ->title(Craft::t('commerce', 'Import Inventory'))
            ->formAttributes(['enctype' => 'multipart/form-data'])
            ->metaSidebarTemplate('commerce/inventory/importexport/_importMeta')
            ->submitButtonLabel(Craft::t('commerce', 'Import'))
            ->contentTemplate('commerce/inventory/importexport/_importScreen', $params);
    }

    public function actionImportInventory()
    {
        $errors = [];
        $inventory = Plugin::getInstance()->getInventory();
        $this->requirePostRequest();
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $file = UploadedFile::getInstanceByName('importFile');

        if (!$file) {
            return $this->asFailure('No file uploaded.');
        }

        // check CSV file has certain headers
        try {
            $csv = Reader::createFromPath($file->tempName);
            $csv->setHeaderOffset(0); //set the CSV header offset
            $csv->setEscape(''); //required in PHP8.4+ to avoid deprecation notices
        } catch (\Exception $e) {
            return $this->asFailure('Invalid CSV file.');
        }

        $headers = $csv->getHeader();

        if (!in_array('location', $headers) || !in_array('item', $headers) || !in_array('action', $headers) || !in_array('amount', $headers)) {
            return $this->asFailure('Invalid CSV file. Missing required headers.');
        }

        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();

        $updateInventoryLevels = UpdateInventoryLevelCollection::make();

        foreach ($csv->getRecords() as $key => $record) {

            $inventoryLocation = null;
            if(is_numeric($record['location'])) {
                $inventoryLocation = $inventoryLocations->firstWhere('id', $record['location']);
            }else{
                $inventoryLocation = $inventoryLocations->firstWhere('handle', $record['location']);
            }

            if (!$inventoryLocation) {
                $errors[$key][] = 'Invalid location: ' . $record['location'];
                continue;
            }

            $item = null;
            if(is_numeric($record['item'])) {
                $item = Plugin::getInstance()->getInventory()->getInventoryItemById($record['item']);
            }else{
                $item = Plugin::getInstance()->getInventory()->getInventoryItemBySku($record['item']);
            }

            if ($item === null) {
                $errors[$key][] = 'Invalid item: ' . $record['location'];
                continue;
            }

            $updateAction = $record['action'];

            if(!in_array($updateAction, ['set', 'adjust'])) {
                $errors[$key][] = 'Invalid action type: ' . $record['action'];
                continue;
            }

            $amount = $record['amount'];

            if(!is_numeric($amount)) {
                $errors[$key][] = 'Invalid amount: ' . $record['amount'];
                continue;
            }

            $notes = $record['notes'] ?? '';

            // if $errors[$key]['errors'] is not empty add the line to array
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
        }

        if ($errors) {
            $this->setFailFlash(Craft::t('commerce', 'There was a problem with the import.'));
            return $this->asCpScreen()
                ->action('commerce/inventory-importexport/import-inventory')
                ->addCrumb(Craft::t('commerce', 'Inventory'), 'commerce/inventory')
                ->selectedSubnavItem('inventory')
                ->redirectUrl('commerce/inventory')
                ->title(Craft::t('commerce', 'Import Inventory'))
                ->formAttributes(['enctype' => 'multipart/form-data'])
                ->metaSidebarTemplate('commerce/inventory/importexport/_importMeta')
                ->submitButtonLabel(Craft::t('commerce', 'Import'))
                ->contentTemplate('commerce/inventory/importexport/_importScreen', ['errors' => $errors]);
        }

        Plugin::getInstance()->getInventory()->executeUpdateInventoryLevels($updateInventoryLevels);

        return $this->asSuccess('Inventory Imported');
    }

    /**
     * @return Response
     * @throws InvalidConfigException
     * @throws \yii\web\ForbiddenHttpException
     * @throws \yii\web\HttpException
     * @throws \yii\web\RangeNotSatisfiableHttpException
     */
    public function actionExport(): Response
    {
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $inventoryLocationId = (int)Craft::$app->getRequest()->getParam('inventoryLocationId');
        $inventoryLocation = Plugin::getInstance()->getInventoryLocations()->getInventoryLocationById($inventoryLocationId);

        $dateTimeString = Craft::$app->getFormatter()->asDateTime(time(), 'yyyy-MM-dd_HHmmss');;

        $inventoryQuery = Plugin::getInstance()->getInventory()->getInventoryLevelQuery()
            ->andWhere(['inventoryLocationId' => $inventoryLocation->id]);

        $inventoryQuery->leftJoin(['purchasables' => Table::PURCHASABLES], '[[purchasables.id]] = [[ii.purchasableId]]');
        $inventoryQuery->addSelect(['sku' => 'purchasables.sku']);
        $inventoryQuery->addSelect(['description' => 'purchasables.description']);

        $response = Craft::$app->getResponse();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="inventory.csv"');
        $response->format = \yii\web\Response::FORMAT_RAW;

        // Open output stream
        $stream = fopen('php://output', 'w');

        // Create a CSV writer instance
        $csv = Writer::createFromStream($stream);

        $csv->insertOne(['location', 'item', 'description', 'action', 'amount', 'notes']);

        foreach ($inventoryQuery->each() as $row) {
            $data = [
                $row['inventoryLocationId'],
                $row['sku'],
                $row['description'],
                'set',
                $row['onHandTotal'],
                '',
            ];
            $csv->insertOne($data);
        }

        // Close the stream (optional, but good practice)
        fclose($stream);

        // End the response to prevent further output
        Craft::$app->end();
    }

    public function actionExampleTemplate()
    {
        // return csv example template
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $csvFile = Writer::createFromString('location,item,action,amount,notes');

        return Craft::$app->getResponse()->sendContentAsFile(
            $csvFile->toString(),
            Craft::t('commerce', 'inventory-import-template') . '.csv',
            ['mimeType' => 'text/csv']
        );
    }
}
