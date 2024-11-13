<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use craft\base\Component;
use craft\commerce\base\Report as Report;
use Illuminate\Support\Collection;

/**
 * Reports Service
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.x
 */
class Reports extends Component
{
    /**
     * @var ?Collection
     */
    private ?Collection $_allReports = null;

    public function getAllReports(): Collection
    {
        if ($this->_allReports === null) {
            $this->_allReports = collect([
                new \craft\commerce\reports\SalesByProduct(),
                new \craft\commerce\reports\SalesBySku(),
                new \craft\commerce\reports\AverageOrderValueOverTime(),
            ]);
        }

        return $this->_allReports;
    }

    public function getReportByHandle(string $handle): ?Report
    {
        return $this->getAllReports()->firstWhere(function($report) use ($handle) {
            return $report->getHandle() === $handle;
        });
    }
}
