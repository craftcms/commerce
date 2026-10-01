<?php

declare(strict_types=1);

use CraftCms\Cms\Database\Migration;
use CraftCms\Cms\Shared\Enums\Color;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\LineItemStatuses;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy line item status colors that have no direct {@see Color} equivalent.
     *
     * @var array<string, string>
     */
    private const array COLOR_MAP = [
        'turquoise' => 'teal',
        'grey' => 'gray',
        'light' => 'white',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', DB::getTablePrefix() . Table::LINEITEMSTATUSES, DB::getTablePrefix() . Table::LINEITEMSTATUSES . '_color_check'));
        }

        Schema::table(Table::LINEITEMSTATUSES, function(Blueprint $table) {
            $table->string('color')->default(Color::Green->value)->change();
        });

        foreach (self::COLOR_MAP as $legacy => $color) {
            DB::table(Table::LINEITEMSTATUSES)->where('color', $legacy)->update(['color' => $color]);
        }

        foreach (ProjectConfig::get(LineItemStatuses::CONFIG_STATUSES_KEY) ?? [] as $uid => $config) {
            $color = $config['color'] ?? null;

            if (isset(self::COLOR_MAP[$color])) {
                ProjectConfig::set(LineItemStatuses::CONFIG_STATUSES_KEY . ".$uid.color", self::COLOR_MAP[$color]);
            }
        }
    }
};
