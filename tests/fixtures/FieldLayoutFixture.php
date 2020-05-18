<?php

namespace craftcommercetests\fixtures;

use craft\models\FieldLayout;
use craft\test\fixtures\FieldLayoutFixture as BaseFieldLayoutFixture;

/**
 * Field Layout Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class FieldLayoutFixture extends BaseFieldLayoutFixture
{
    public $dataFile = __DIR__ . '/data/field-layout.php';

    public $modelClass = FieldLayout::class;
}