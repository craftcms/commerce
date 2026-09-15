<?php

declare(strict_types=1);

namespace craftcommercetests\fixtures;

use craft\commerce\models\TaxCategory;
use craft\commerce\Plugin;

/**
 * Class TaxCategoryFixture
 * @package craftcommercetests\fixtures
 */
class TaxCategoryFixture extends BaseModelFixture
{
    /**
     * @inheritdoc
     */
    public $dataFile = __DIR__ . '/data/tax-category.php';

    /**
     * @inheritdoc
     */
    public $modelClass = TaxCategory::class;

    /**
     * @inheritDoc
     */
    public string $saveMethod = 'saveTaxCategory';

    /**
     * @inheritDoc
     */
    public string $deleteMethod = 'deleteTaxCategoryById';

    /**
     * @inheritDoc
     */
    public $service = 'taxCategories';

    /**
     * @inheritDoc
     */
    public function init(): void
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }
}
