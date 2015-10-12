<?php
namespace Commerce\Gateways;

/**
 * Interface GatewayAdapterInterface
 * @package Commerce\Gateways
 *
 * @method protected array defineAttributes() Use it to define setting parameters, it's labels and rules. Must be protected
 */
interface GatewayAdapterInterface
{
    /** @return string */
    public function handle();

    /** @return string */
    public function displayName();

    /** @return string */
    public function getSettingsHtml();

    /** @return bool */
    public function requiresCreditCard();
}