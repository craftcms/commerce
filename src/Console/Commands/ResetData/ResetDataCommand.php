<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\ResetData;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Commerce\Database\Table;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Override;
use Throwable;

class ResetDataCommand extends Command
{
    use CraftCommand;
    use ConfirmableTrait;

    #[Override]
    protected $signature = 'commerce:reset-data {--force : Force the reset without confirmation}';

    #[Override]
    protected $description = 'Resets Commerce data.';

    #[Override]
    protected $aliases = ['commerce/reset-data'];

    public function handle(): int
    {
        $confirmed = $this->confirmToProceed(
            'Resetting Commerce data will permanently delete all orders, subscriptions, and payment sources, and reset discount usages.',
            fn() => true,
        );

        if (!$confirmed) {
            return self::SUCCESS;
        }

        try {
            DB::transaction(function() {
                $this->components->task('Deleting orders', function() {
                    $ids = DB::table(Table::ORDERS)->pluck('id')->all();
                    DB::table(CraftTable::ELEMENTS)->whereIn('id', $ids)->delete();
                });

                $this->components->task('Deleting subscriptions', function() {
                    $ids = DB::table(Table::SUBSCRIPTIONS)->pluck('id')->all();
                    DB::table(CraftTable::ELEMENTS)->whereIn('id', $ids)->delete();
                    // These should really be deleted with a cascade
                    DB::table(Table::SUBSCRIPTIONS)->delete();
                });

                $this->components->task('Deleting payment sources', function() {
                    DB::table(Table::PAYMENTSOURCES)->delete();
                });

                $this->components->task('Resetting discount usage data', function() {
                    DB::table(Table::CUSTOMER_DISCOUNTUSES)->delete();
                    DB::table(Table::EMAIL_DISCOUNTUSES)->delete();
                    DB::table(Table::DISCOUNTS)->update(['totalDiscountUses' => 0]);
                });
            });
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Finished resetting Commerce data.');

        return self::SUCCESS;
    }
}
