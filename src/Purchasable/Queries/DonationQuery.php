<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Queries;

use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Purchasable\Elements\Donation;
use Tpetry\QueryExpressions\Language\Alias;

/**
 * @extends PurchasableQuery<Donation>
 */
class DonationQuery extends PurchasableQuery
{
    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        parent::__construct(Donation::class, $config);

        $this->query->join(new Alias(Table::DONATIONS, 'commerce_donations'), 'commerce_donations.id', '=', 'elements.id');

        $this->beforeQuery(function(self $query) {
            if ($query->sku) {
                $query->where('commerce_donations.sku', $query->sku);
            }
        });
    }
}
