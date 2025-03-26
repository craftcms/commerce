<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\Plugin;
use craft\commerce\queue\jobs\ImportInventory;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\web\Controller;
use craft\web\CpScreenResponseBehavior;
use craft\web\UploadedFile;
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
            })
            ->contentTemplate('commerce/inventory/importexport/_importScreen', $params);
    }

    public function actionImportInventory()
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-manageInventoryStockLevels');
        
        $tempFilename = Craft::$app->getRequest()->getBodyParam('importFilename');
        if (!$tempFilename) {
            return $this->asFailure(Craft::t('commerce', 'No file specified.'));
        }
        
        $tempDirectory = Craft::$app->getPath()->getTempPath() . '/commerce-inventory-import';
        $tempFilePath = $tempDirectory . '/' . $tempFilename;
        
        if (!file_exists($tempFilePath)) {
            return $this->asFailure(Craft::t('commerce', 'File not found.'));
        }

        // Create and queue the import job
        $jobId = Craft::$app->getQueue()->push(new ImportInventory([
            'description' => Craft::t('commerce', 'Import inventory from CSV'),
            'filePath' => $tempFilePath,
        ]));

        $message = Craft::t('commerce', 'Inventory import has been queued.');
        return $this->asSuccess($message);
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
            ->formAttributes(['enctype' => 'multipart/form-data', 'accept-charset' => 'UTF-8'])
            ->metaSidebarTemplate('commerce/inventory/importexport/_importMeta')
            ->submitButtonLabel(Craft::t('commerce', 'Import'));
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
