<?php

namespace craft\commerce\models\subscriptions;

use craft\base\Model;

/**
 * Class SubscriptionForm
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class SubscriptionForm extends Model
{
    /**
     * Trial days for the subscription.
     *
     * @var int
     */
    public $trialDays = 0;

    // TODO this form adds support for creating a payment source on the fly when subscribing.
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['trialDays'], 'integer', 'integerOnly' => true, 'min' => 0]
        ];
    }
}
