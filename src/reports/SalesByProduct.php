<?php

namespace craft\commerce\reports;

use craft\commerce\base\Report;

class SalesByProduct extends Report
{
    public function getHandle(): ?string
    {
        return 'salesByProduct';
    }

    public function getTitle(): string
    {
        return 'Sales By Product';
    }
}
