<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

class Order extends BaseModel
{
    #[\Override]
    protected $table = Table::ORDERS;

    public $timestamps = false;
}
