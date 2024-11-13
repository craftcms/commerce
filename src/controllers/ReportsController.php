<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use craft\web\Controller;
use yii\web\Response;

/**
 * Reports controller
 */
class ReportsController extends Controller
{
    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * commerce/reports action
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('commerce-manageReports');

        $breadcrumbs = [
            ['label' => Craft::t('commerce', 'Reports'), 'url' => 'commerce/reports'],
        ];

        $this->view->registerAssetBundle('craft\\web\\assets\\admintable\\AdminTableAsset');

        return $this->asCpScreen()
            ->crumbs($breadcrumbs)
            ->title(Craft::t('commerce', 'Reports'))
            ->selectedSubnavItem('reports')
            ->contentTemplate('commerce/reports/_index', [
                'reportsTableEndpoint' => 'commerce/reports/reports-table',
            ]);
    }

    public function actionReportsTable(): Response
    {
        $this->requirePermission('commerce-manageReports');

        $this->requireAcceptsJson();

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

    public function actionViewReport(string $handle): Response
    {
        $this->requirePermission('commerce-manageReports');

        $report = Plugin::getInstance()->getReports()->getReportByHandle($handle);

        if (!$report) {
            throw new NotFoundHttpException('Report not found');
        }

        $this->view->registerAssetBundle('craft\\web\\assets\\admintable\\AdminTableAsset');

        return $this->asCpScreen()
            ->title($report->getTitle())
            ->contentTemplate('commerce/reports/_view', [
                'report' => $report,
            ]);
    }
}
