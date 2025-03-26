<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\collections\UpdateInventoryLevelCollection;
use craft\commerce\db\Table;
use craft\commerce\enums\InventoryTransactionType;
use craft\commerce\enums\InventoryUpdateQuantityType;
use craft\commerce\models\inventory\UpdateInventoryLevel;
use craft\commerce\Plugin;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\web\Controller;
use craft\web\CpScreenResponseBehavior;
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

        return $this->_importScreen()
            ->contentTemplate('commerce/inventory/importexport/_importScreen', $params);
    }

    public function actionImportInventory()
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $inventoryService = Plugin::getInstance()->getInventory();

        $errors = [];
        
        $tempFilename = Craft::$app->getRequest()->getBodyParam('importFilename');
        if (!$tempFilename) {
            return $this->asFailure(Craft::t('commerce', 'No file specified.'));
        }
        
        $tempDirectory = Craft::$app->getPath()->getTempPath() . '/commerce-inventory-import';
        $tempFilePath = $tempDirectory . '/' . $tempFilename;
        
        if (!file_exists($tempFilePath)) {
            return $this->asFailure(Craft::t('commerce', 'File not found.'));
        }

        // check CSV file is OK
        try {
            $csv = Reader::createFromPath($tempFilePath);
            $csv->setHeaderOffset(0); //set the CSV header offset
            $csv->setEscape(''); //required in PHP8.4+ to avoid deprecation notices
        } catch (\Exception $e) {
            return $this->asFailure('Invalid CSV file.');
        }

        // Check required headers are all there
        $headers = $csv->getHeader();
        if (!in_array('location', $headers) || !in_array('item', $headers) || !in_array('action', $headers) || !in_array('amount', $headers)) {
            return $this->asFailure('Invalid CSV file. Missing required headers.');
        }

        $inventoryLocations = Plugin::getInstance()->getInventoryLocations()->getAllInventoryLocations();

        $updateInventoryLevels = UpdateInventoryLevelCollection::make();

        foreach ($csv->getRecords() as $key => $record) {
            $inventoryLocation = null;
            if (is_numeric($record['location'])) {
                $inventoryLocation = $inventoryLocations->firstWhere('id', $record['location']);
            } else {
                $inventoryLocation = $inventoryLocations->firstWhere('handle', $record['location']);
            }

            if (!$inventoryLocation) {
                $errors[$key][] = Craft::t('commerce','Invalid location: {error}', ['error' => $record['location']]);
                continue;
            }

            $item = null;
            if (is_numeric($record['item'])) {
                $item = $inventoryService->getInventoryItemById($record['item']);
            } else {
                $item = $inventoryService->getInventoryItemBySku($record['item']);
            }

            if ($item === null) {
                $errors[$key][] = Craft::t('commerce','Invalid item: {error}', ['error' => $record['item']]);
                continue;
            }

            $updateAction = $record['action'];

            if (!in_array($updateAction, ['set', 'adjust'])) {
                $errors[$key][] = Craft::t('commerce','Invalid action type: {error}', ['error' => $updateAction]);
                continue;
            }

            $amount = $record['amount'];

            if (!is_numeric($amount)) {
                $error = $record['amount'] ?: Craft::t('commerce','Missing');
                $errors[$key][] = Craft::t('commerce','Invalid amount: {error}', ['error' => $error]);
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
            $this->setFailFlash(Craft::t('commerce', 'Import could not begin due to errors.'));
            return $this->_importScreen()
                ->contentTemplate('commerce/inventory/importexport/_importScreen', ['errors' => $errors]);
        }

        $inventoryService->executeUpdateInventoryLevels($updateInventoryLevels);

        return $this->asSuccess('Inventory Imported');
    }

    private function _importScreen()
    {
        // Register Commerce CP Assets
        $this->view->registerAssetBundle(CommerceCpAsset::class);
        
        return $this->asCpScreen()
            ->action('commerce/inventory-importexport/import-inventory')
            ->addCrumb(Craft::t('commerce', 'Inventory'), 'commerce/inventory')
            ->selectedSubnavItem('inventory')
            ->redirectUrl('commerce/inventory')
            ->title(Craft::t('commerce', 'Import Inventory'))
            ->formAttributes(['enctype' => 'multipart/form-data', 'accept-charset'=>'UTF-8'])
            ->metaSidebarTemplate('commerce/inventory/importexport/_importMeta')
            ->submitButtonLabel(Craft::t('commerce', 'Import'))
            ->prepareScreen(function(Response $response, string $containerId) {
                /** @var CpScreenResponseBehavior $response */
                $view = Craft::$app->getView();
                $view->registerJsWithVars(
                    fn($containerId) => <<<JS
                        $(function() {
                            var \$container = $('#' + $containerId);
                            if (\$container.length) {
                                new Craft.Commerce.InventoryImportFileUploader('#' + $containerId);
                            }
                        });
                    JS,
                    [$containerId]
                );
            });
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
    
    /**
     * Upload a file to a temporary directory
     * 
     * @return Response
     */
    public function actionUploadTempFile(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-manageInventoryStockLevels');
        $this->requireAcceptsJson();
        
        $file = UploadedFile::getInstanceByName('file');
        
        if (!$file) {
            return $this->asFailure(Craft::t('commerce', 'No file uploaded'));
        }
        
        // Check file type
        if ($file->extension !== 'csv') {
            return $this->asFailure(Craft::t('commerce', 'Only CSV files are allowed'));
        }
        
        // Create temp directory if it doesn't exist
        $tempDirectory = Craft::$app->getPath()->getTempPath() . '/commerce-inventory-import';
        if (!is_dir($tempDirectory)) {
            mkdir($tempDirectory, 0777, true);
        }
        
        // Generate unique filename
        $filename = 'inventory-import-' . uniqid() . '.csv';
        $tempPath = $tempDirectory . '/' . $filename;
        
        // Save the file
        if (!$file->saveAs($tempPath)) {
            return $this->asFailure(Craft::t('commerce', 'Could not save the file'));
        }
        
        return $this->asJson([
            'success' => true,
            'filename' => $filename,
        ]);
    }
}
