<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\InventoryImport;
use craft\commerce\Plugin;
use craft\web\Controller;
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

        return $this->asCpScreen()
            ->action('commerce/inventory/import-inventory')
            ->addCrumb(Craft::t('commerce', 'Inventory'), 'commerce/inventory')
            ->selectedSubnavItem('inventory')
            ->title(Craft::t('commerce', 'Import Inventory'))
            ->formAttributes(['enctype' => 'multipart/form-data'])
            ->metaSidebarTemplate('commerce/inventory/importexport/_importMeta')
            ->submitButtonLabel(Craft::t('commerce', 'Import'))
            ->contentTemplate('commerce/inventory/importexport/_importScreen', $params);
    }

    public function actionImportInventory(): Response
    {
        $errors = [];
        $inventory = Plugin::getInstance()->getInventory();
        $this->requirePostRequest();
        $this->requirePermission('commerce-manageInventoryStockLevels');

        $file = UploadedFile::getInstanceByName('importFile');

        if (!$file) {
            return $this->asError(Craft::t('commerce', 'No file uploaded.'));
        }

        $import = new InventoryImport([
            'importFile' => $file->tempName,
        ]);

        $inventory->importInventory($import);


        return $this->asSuccess(Craft::t('commerce', 'Inventory imported.'));
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

        $dateTimeString = Craft::$app->getFormatter()->asDateTime(time(), 'yyyy-MM-dd_HHmmss');
        ;

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

        $csv->insertOne(['location', 'item', 'description', 'type', 'amount', 'notes']);

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

        $csvFile = Writer::createFromString('location,item,type,amount,notes');

        return Craft::$app->getResponse()->sendContentAsFile(
            $csvFile->toString(),
            Craft::t('commerce', 'inventory-import-template') . '.csv',
            ['mimeType' => 'text/csv']
        );
    }
}
