<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Report\Events;

class ReportEvent
{
    public mixed $startDate = null;
    public mixed $endDate = null;
    public mixed $status = null;
    public mixed $orderQuery = null;
    public mixed $columns = null;
    public mixed $orders = null;
    public mixed $format = null;
}
