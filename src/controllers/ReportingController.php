<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Report;
use craft\commerce\Plugin;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use DateTime;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use craft\commerce\helpers\AdminTable;

/**
 * Reports controller
 */
class ReportingController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * commerce/reports action
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('commerce-manageReporting');

        $breadcrumbs = [
            ['label' => Craft::t('commerce', 'Reporting'), 'url' => 'commerce/reporting'],
        ];

        $this->view->registerAssetBundle('craft\\web\\assets\\admintable\\AdminTableAsset');
        
        // Set up the AdminTable configuration
        $adminTable = new AdminTable();
        $adminTable
            ->container('#reports-vue-admin-table')
            ->columns([
                AdminTable::createTitleColumn(Craft::t('commerce', 'Title')),
                AdminTable::createHandleColumn(Craft::t('commerce', 'Handle')),
            ])
            ->fullPane(true)
            ->emptyMessage(Craft::t('commerce', 'No reports exist yet.'))
            ->padded(fn () => true)
            ->tableDataEndpoint('commerce/reporting/reports-table')
            ->search(true);
            
        // Pass the table configuration to the template
        return $this->asCpScreen()
            ->crumbs($breadcrumbs)
            ->title(Craft::t('commerce', 'Reports'))
            ->selectedSubnavItem('reports')
            ->contentTemplate('commerce/reporting/_index', [
                'adminTable' => $adminTable,
            ]);
    }

    /**
     * Returns the data for the reports admin table
     */
    public function actionReportsTable(): Response
    {
        $this->requirePermission('commerce-manageReporting');
        $this->requireAcceptsJson();

        /** @var Report[] $reports */
        $reports = Plugin::getInstance()->getReports()->getAllReports();

        $data = [];

        foreach ($reports as $report) {
            $data[] = [
                'title' => $report->getTitle(),
                'handle' => $report->getHandle(),
                'url' => $report->getCpEditUrl(),
                'icon' => $report->getIcon(),
            ];
        }

        return $this->asJson(['data' => $data]);
    }

    /**
     * Displays a report
     */
    public function actionView(?string $reportHandle = null): Response
    {
        $this->requirePermission('commerce-manageReporting');

        $report = Plugin::getInstance()->getReports()->getReportByHandle($reportHandle);

        if (!$report) {
            throw new NotFoundHttpException('Report not found via route handle');
        }
        
        // Get the date range filters
        $startDate = $this->request->getParam('startDate');
        $endDate = $this->request->getParam('endDate');
        $dateRange = $this->request->getParam('dateRange', 'custom');
        
        // Set date ranges on the report if provided
        if ($startDate) {
            $report->setStartDate(DateTimeHelper::toDateTime($startDate));
        }
        
        if ($endDate) {
            $report->setEndDate(DateTimeHelper::toDateTime($endDate));
        }
        
        // Get custom parameters from request
        $paramValues = [];
        foreach ($report->getParams() as $param) {
            $handle = $param['handle'];
            $paramValues[$handle] = $this->request->getParam($handle);
        }
        
        // Set parameters on report
        $report->setParams($paramValues);
        
        // Get report data
        $reportData = $report->getData();
        
        return $this->asCpScreen()
            ->crumbs([
                ['label' => Craft::t('commerce', 'Reporting'), 'url' => 'commerce/reporting'],
            ])
            ->title($report->getTitle())
            ->contentTemplate('commerce/reporting/_view', [
                'report' => $report,
                'reportData' => $reportData,
                'startDate' => $report->getStartDate(),
                'endDate' => $report->getEndDate(),
                'dateRange' => $dateRange,
                'params' => $report->getParams(),
                'paramValues' => $report->getParamValues(),
            ]);
    }
    
    /**
     * Downloads a report as CSV
     */
    public function actionDownload(string $reportHandle): Response
    {
        $this->requirePermission('commerce-manageReporting');
        
        $report = Plugin::getInstance()->getReports()->getReportByHandle($reportHandle);
        
        if (!$report) {
            throw new NotFoundHttpException('Report not found');
        }
        
        // Get the date range filters
        $startDate = $this->request->getParam('startDate');
        $endDate = $this->request->getParam('endDate');
        $dateRange = $this->request->getParam('dateRange', 'custom');
        
        // Set date ranges on the report if provided
        if ($startDate) {
            $report->setStartDate(DateTimeHelper::toDateTime($startDate));
        }
        
        if ($endDate) {
            $report->setEndDate(DateTimeHelper::toDateTime($endDate));
        }
        
        // Get custom parameters from request
        $paramValues = [];
        foreach ($report->getParams() as $param) {
            $handle = $param['handle'];
            $paramValues[$handle] = $this->request->getParam($handle);
        }
        
        // Set parameters on report
        $report->setParams($paramValues);
        
        $filename = $report->getHandle() . '_' . date('Y-m-d') . '.csv';
        
        // Get CSV headers and data
        $headers = $report->getCsvHeaders();
        $rows = $report->getCsvData();
        
        return $this->response->sendContentAsFile(
            $this->generateCsv($headers, $rows),
            $filename,
            ['mimeType' => 'text/csv']
        );
    }
    
    /**
     * Generates a CSV file from headers and rows
     */
    private function generateCsv(array $headers, array $rows): string
    {
        $csv = fopen('php://temp', 'r+');
        
        // Add headers
        fputcsv($csv, $headers);
        
        // Add data rows
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        
        rewind($csv);
        $csvContent = stream_get_contents($csv);
        fclose($csv);
        
        return $csvContent;
    }
}
