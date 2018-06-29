<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\eway\gateways\gateway as EwayGateway;
use craft\commerce\multisafepay\gateways\gateway as MultiSafepayGateway;
use craft\commerce\paypal\gateways\PayPalExpress;
use craft\commerce\paypal\gateways\PayPalRest;
use craft\commerce\sagepay\gateways\Direct;
use craft\commerce\sagepay\gateways\Server;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m180329_161901_gateway_send_cart_info migration.
 */
class m180329_161901_gateway_send_cart_info extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $gateways = (new Query())
            ->select(['id', 'settings', 'type', 'sendCartInfo'])
            ->from(['{{%commerce_gateways}}'])
            ->where([
                'type' => [
                    PayPalExpress::class,
                    'craft\\commerce\\gateways\\PayPal_Express',
                    PayPalRest::class,
                    Direct::class,
                    'craft\\commerce\\gateways\\SagePay_Direct',
                    Server::class,
                    'craft\\commerce\\gateways\\SagePay_Server',
                    EwayGateway::class,
                    'craft\\commerce\\gateways\\Eway_RapidDirect',
                    MultiSafepayGateway::class,
                    'craft\\commerce\\gateways\\MultiSafepay_Rest'
                ]
            ])
            ->all($this->db);

        foreach ($gateways as $gateway) {

            $settings = Json::decodeIfJson($gateway['settings']);

            if (!is_array($settings)) {
                echo 'Gateway ' . $gateway['id'] . ' (' . $gateway['type'] . ') settings were invalid JSON: ' . $gateway['settings'] . "\n";
                $settings = [];
            }

            $settings['sendCartInfo'] = $gateway['sendCartInfo'];

            $this->update('{{%commerce_gateways}}', ['settings' => Json::encode($settings)], ['id' => $gateway['id']]);
        }

        $this->dropColumn('{{%commerce_gateways}}', 'sendCartInfo');
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180329_161901_gateway_send_cart_info cannot be reverted.\n";
        return false;
    }
}
