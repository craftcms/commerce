<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models\subscriptions;

use craft\base\Model;

/**
 * Class SubscriptionForm
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SubscriptionForm extends Model
{
    /**
     * Trial days for the subscription.
     *
     * @var int
     */
    public $trialDays;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules[] = parent::rules();

        $rules[] = [['trialDays'], 'integer', 'integerOnly' => true, 'min' => 0];

        return $rules;
    }
}
