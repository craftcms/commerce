<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\cart;

use Craft;
use craft\base\Model;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\models\LineItem;
use craft\helpers\ArrayHelper;

/**
 * Add Purchasable To Cart Form
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2
 */
class AddPurchasableToCartForm extends Model
{
    public ?int $id = null;
    public string $note = '';
    public array $options = [];
    public int $qty = 1;

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [[
            'id',
            'qty',
            'note',
            'options',
        ], 'safe'];

        return $rules;
    }

    public function getKey(): string
    {
        return $this->id . '-' . LineItemHelper::generateOptionsSignature($this->options);
    }
}