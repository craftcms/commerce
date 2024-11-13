<?php

namespace craft\commerce\reports;

use craft\commerce\base\Report;

class SalesByProduct extends Report
{
    public function getHandle(): ?string
    {
        return 'sales-by-product';
    }

    public function getTitle(): string
    {
        return 'Sales By Product';
    }
}
