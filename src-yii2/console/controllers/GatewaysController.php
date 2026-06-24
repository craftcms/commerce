<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use craft\commerce\Plugin as Commerce;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

/**
 * Gateways controller.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.3
 */
class GatewaysController extends Controller
{
    public $defaultAction = 'index';

    /**
     * Default action. See `commerce/gateways/list`.
     */
    public function actionIndex()
    {
        return $this->runAction('list');
    }

    /**
     * Lists the currently-configured, non-archived gateways.
     */
    public function actionList()
    {
        $gateways = Commerce::getInstance()->getGateways()->getAllGateways();
        $rows = collect($gateways)
            ->map(fn($gateway) =>
                /** @var \craft\commerce\base\Gateway $gateway */
                [
                $gateway->id,
                $gateway->name,
                $gateway->handle,
                $gateway->getIsFrontendEnabled() ? 'Yes' : 'No',
                $gateway::class,
                $gateway->uid,
            ])
            ->all();

        Console::table([
            'ID',
            'Name',
            'Handle',
            'Enabled',
            'Type',
            'UUID',
        ], $rows);
    }

    /**
     * Gets a Webhook URL for the provided gateway
     *
     * @param string $handle
     */
    public function actionWebhookUrl(string $handle)
    {
        $gateway = Commerce::getInstance()->getGateways()->getGatewayByHandle($handle);

        if (!$gateway) {
            $this->stderr("A gateway with handle `$handle` does not exist." . PHP_EOL, Console::FG_YELLOW);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Webhook URL for the {$gateway->name} gateway:" . PHP_EOL);
        $this->stdout($gateway->getWebhookUrl() . PHP_EOL, Console::FG_BLUE);

        return ExitCode::OK;
    }
}
