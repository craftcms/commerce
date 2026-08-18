<?php

declare(strict_types=1);

namespace craftcommercetests\fixtures;

use CraftCms\Commerce\Database\Table;
use Illuminate\Support\Facades\DB;
use yii\test\DbFixture;
use yii\test\FileFixtureTrait;

/**
 * Inserts rows directly into the `commerce_producttypes_taxcategories` pivot table via the query
 * builder — there is no Eloquent record for this table, since
 * {@see \CraftCms\Commerce\Tax\TaxCategories} manages it entirely through `DB::table()`.
 */
class ProductTypesTaxCategoriesFixture extends DbFixture
{
    use FileFixtureTrait;

    public string $dataFile = __DIR__ . '/data/product-types-tax-categories.php';

    public array $data = [];

    #[\Override]
    public function load(): void
    {
        $this->data = $this->loadData($this->dataFile, false);

        foreach ($this->data as $row) {
            DB::table(Table::PRODUCTTYPES_TAXCATEGORIES)->insert($row);
        }
    }

    #[\Override]
    public function unload(): void
    {
        foreach ($this->data as $row) {
            DB::table(Table::PRODUCTTYPES_TAXCATEGORIES)->where('id', $row['id'])->delete();
        }

        $this->data = [];
    }
}
