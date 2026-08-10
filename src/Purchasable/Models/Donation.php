<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Models;

use CraftCms\Cms\Shared\BaseModel;
use CraftCms\Commerce\Database\Table;

class Donation extends BaseModel
{
    #[\Override]
    protected $table = Table::DONATIONS;

    public $timestamps = false;
}
