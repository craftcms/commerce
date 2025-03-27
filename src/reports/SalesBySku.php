<?php

namespace craft\commerce\reports;

use craft\commerce\base\Report;

class SalesBySku extends Report
{
    public function getHandle(): ?string
    {
        return 'salesBySku';
    }

    public function getTitle(): string
    {
        return 'Sales By SKU';
    }
}
