<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\fixtures;

use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;

/**
 * Payment Currencies Fixture
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
 */
class PaymentCurrenciesFixture extends BaseModelFixture
{
    /**
     * @inheritDoc
     */
    public $modelClass = PaymentCurrency::class;

    /**
     * @inheritDoc
     */
    public string $deleteMethod = 'deletePaymentCurrencyById';

    /**
     * @inheritDoc
     */
    public string $saveMethod = 'savePaymentCurrency';

    /**
     * @inheritDoc
     */
    public $service = 'paymentCurrencies';

    /**
     * @inheritDoc
     */
    public $dataFile = __DIR__.'/data/payment-currencies.php';

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->service = Plugin::getInstance()->get($this->service);

        parent::init();
    }
}
