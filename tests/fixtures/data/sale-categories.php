<?php

use craft\elements\Category;

return [
    [
        'id' => '9000',
        'saleId' => '1001',
        'categoryId' => Category::find()->title('Commerce Category')->one()->id,
    ],
    [
        'id' => '9001',
        'saleId' => '1001',
        'categoryId' => Category::find()->title('Commerce Category #2')->one()->id,
    ],
];