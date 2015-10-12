<?php
namespace Commerce\Gateways;

use Craft\AttributeType;
use Craft\BaseModel;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\GatewayFactory;

/**
 * Class BaseGatewayAdapter
 * @package Commerce\Gateways
 *
 * @method protected array defineAttributes() Use it to define setting parameters, it's labels and rules. Must be protected
 */
abstract class BaseGatewayAdapter extends BaseModel implements GatewayAdapterInterface
{
    /** @var AbstractGateway */
    protected $_gateway;
    protected $_selects = [];
    protected $_booleans = [];
    /** @var GatewayFactory */
    protected static $_factory;

    /**
     * Commerce_GatewayModel constructor.
     * @param array $attributes
     */
    public function __construct($attributes = null)
    {
        $this->init();
        parent::__construct($attributes);
    }

    /**
     * @param array $values
     */
    public function setAttributes($values)
    {
        parent::setAttributes($values);
        if (is_array($values)) {
            $this->getGateway()->initialize($values);
        }
    }

    /**
     * Initialize Omnipay Gateway
     */
    public function init()
    {
        $defaults = $this->getGateway()->getDefaultParameters();

        //fill selects
        $this->_selects = array_filter($defaults, 'is_array');
        foreach ($this->_selects as $param => &$values) {
            $values = array_combine($values, $values);
        }

        //fill booleans
        foreach ($defaults as $key => $value) {
            if (is_bool($value)) {
                $this->_booleans[] = $key;
            }
        }
    }

    /**
     * @return string
     */
    public function displayName()
    {
        return $this->getGateway()->getName();
    }

    /**
     * @return string
     */
    public function getSettingsHtml()
    {
        return \Craft\craft()->templates->render('commerce/_gateways/omnipay', [
            'adapter' => $this,
        ]);
    }

    /**
     * @return AbstractGateway
     */
    public function getGateway()
    {
        if (!$this->_gateway) {
            $this->_gateway = self::getFactory()->create($this->handle());
        }
        return $this->_gateway;
    }

    /**
     * Settings fields which should be displayed as select-boxes
     *
     * @return array [setting name => [choices list]]
     */
    public function getSelects()
    {
        return $this->_selects;
    }

    /**
     * Settings fields which should be displayed as check-boxes
     *
     * @return array
     */
    public function getBooleans()
    {
        return $this->_booleans;
    }

    /**
     * Returns the list of attribute names of the model.
     * @return array list of attribute names.
     */
    public function defineAttributes()
    {
        $params = $this->getGateway()->getParameters();
        $booleans = $this->getBooleans();
        $selects = $this->getSelects();

        $result = [];
        foreach (array_keys($params) as $key) {
            if (in_array($key, $booleans)) {
                $result[$key] = [AttributeType::Bool];
            } elseif (isset($selects[$key])) {
                $result[$key] = [AttributeType::Enum, 'values' => array_values($selects[$key])];
            } else {
                $result[$key] = [AttributeType::String];
            }

            $result[$key]['label'] = $this->generateAttributeLabel($key);
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function requiresCreditCard()
    {
        return true;
    }

    /**
     * @return GatewayFactory
     */
    protected static function getFactory()
    {
        if (!self::$_factory) {
            self::$_factory = new GatewayFactory();
        }

        return self::$_factory;
    }
}