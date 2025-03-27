<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Report;
use craft\commerce\Plugin;
use craft\commerce\reports\SalesByProduct;
use craft\web\Controller;
use yii\web\Response;

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

        return $this->asCpScreen()
            ->crumbs($breadcrumbs)
            ->title(Craft::t('commerce', 'Reports'))
            ->selectedSubnavItem('reports')
            ->contentTemplate('commerce/reporting/_index', [
                'reportsTableEndpoint' => 'commerce/reporting/reports-table',
            ]);
    }

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
                'url' => $report->getCpEditUrl(),
            ];
        }

        return $this->asJson(['data' => $data]);
    }
    public function actionView(?string $reportHandle = null): Response
    {
        $this->requirePermission('commerce-manageReporting');

        $report = Plugin::getInstance()->getReports()->getReportByHandle($reportHandle);

        if (!$report) {
            throw new NotFoundHttpException('Report not found via route handle');
        }

        return $this->asCpScreen()
            ->crumbs([
                ['label' => Craft::t('commerce', 'Reporting'), 'url' => 'commerce/reporting'],
            ])
            ->title($report->getTitle())
            ->contentTemplate('commerce/reporting/_view', [
                'report' => $report,
            ]);
    }
}
