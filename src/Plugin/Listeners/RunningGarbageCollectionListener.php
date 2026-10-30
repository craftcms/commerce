<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Plugin\Listeners;

use CraftCms\Cms\GarbageCollection\Actions\DeletePartialElements;
use CraftCms\Cms\GarbageCollection\Events\RunningGarbageCollection;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use CraftCms\Commerce\Transfer\Elements\Transfer;
use Illuminate\Support\Facades\DB;

class RunningGarbageCollectionListener
{
    public function handle(RunningGarbageCollection $event): void
    {
        app(Carts::class)->purgeIncompleteCarts();

        DB::table(Table::VARIANTS)->whereNull('primaryOwnerId')->delete();

        foreach ([
            [Donation::class, Table::DONATIONS],
            [Order::class, Table::ORDERS],
            [Product::class, Table::PRODUCTS],
            [Variant::class, Table::VARIANTS],
            [Transfer::class, Table::TRANSFERS],
        ] as [$elementType, $table]) {
            app(DeletePartialElements::class, [
                'garbageCollection' => $event->garbageCollection,
                'elementType' => $elementType,
                'table' => $table,
            ])();
        }
    }
}
