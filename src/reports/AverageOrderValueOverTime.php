<?php

namespace craft\commerce\reports;

use craft\commerce\base\Report;

class AverageOrderValueOverTime extends Report
{
    public function getHandle(): ?string
    {
        return 'averageOrderValueOverTime';
    }

    public function getTitle(): string
    {
        return 'Average order value over time';
    }
}
