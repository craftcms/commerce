<?php
namespace Commerce\Gateways;

use Craft\AttributeType;
use Craft\BaseModel;
use Craft\Commerce_PaymentMethodModel;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\GatewayFactory;
use Commerce\Gateways\PaymentFormModels\BasePaymentFormModel;
use Omnipay\Common\CreditCard;
use Omnipay\Common\Message\AbstractRequest as OmnipayRequest;
use Commerce\Exception\NotImplementedException;
use Omnipay\Common\ItemBag;

/**
 * Class BaseGatewayAdapter
 *
 * @package Commerce\Gateways
 */
abstract class BaseGatewayAdapter extends BaseModel implements GatewayAdapterInterface
{
	/** @var AbstractGateway */
	protected $_gateway;
	protected $_selects = [];
	protected $_booleans = [];
	/** @var GatewayFactory */
	protected static $_factory;
	/** @var Commerce_PaymentMethodModel */
	private $_paymentMethod;

	/**
	 * Commerce_GatewayModel constructor.
	 *
	 * @param array $attributes
	 */
	public function __construct($attributes = null)
	{
		$this->init();
		parent::__construct($attributes);
	}

	/**
	 * @return Commerce_PaymentMethodModel|null
	 */
	public function getPaymentMethod()
	{
		return $this->_paymentMethod;
	}

	/**
	 * @param Commerce_PaymentMethodModel $paymentMethod
	 */
	public function setPaymentMethod(Commerce_PaymentMethodModel $paymentMethod)
	{
		$this->_paymentMethod = $paymentMethod;
	}

	/**
	 * @param mixed $values
	 *
	 * @return void
	 */
	public function setAttributes($values)
	{
		parent::setAttributes($values);
		if (is_array($values))
		{
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
		foreach ($this->_selects as $param => &$values)
		{
			$values = array_combine($values, $values);
		}

		//fill booleans
		foreach ($defaults as $key => $value)
		{
			if (is_bool($value))
			{
				$this->_booleans[] = $key;
			}
		}
	}

	/**
	 * Returns an item bag of the right type for this gateway adapter
	 *
	 * @return ItemBag
	 */
	public function createItemBag()
	{
		return new ItemBag();
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
		if (!$this->_gateway)
		{
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
	 *
	 * @return array list of attribute names.
	 */
	public function defineAttributes()
	{
		$params = $this->getGateway()->getParameters();
		$booleans = $this->getBooleans();
		$selects = $this->getSelects();

		$result = [];
		foreach (array_keys($params) as $key)
		{
			if (in_array($key, $booleans))
			{
				$result[$key] = [AttributeType::Bool];
			}
			elseif (isset($selects[$key]))
			{
				$result[$key] = [AttributeType::Enum, 'values' => array_values($selects[$key])];
			}
			else
			{
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

	public function cpPaymentsEnabled()
	{
		return false;
	}

    public function useNotifyUrl()
    {
        return false;
    }

	/**
	 * @return BasePaymentFormModel
	 * @throws NotImplementedException
	 */
	public function getPaymentFormModel()
	{
		throw new NotImplementedException();
	}

	public function getPaymentFormHtml(array $params)
	{
		return "";
	}

	public function populateCard(CreditCard $card, BaseModel $paymentForm)
	{

	}

	public function populateRequest(OmnipayRequest $request, BaseModel $paymentForm)
	{

	}

	/**
	 * @return GatewayFactory
	 */
	protected static function getFactory()
	{
		if (!self::$_factory)
		{
			self::$_factory = new GatewayFactory();
		}

		return self::$_factory;
	}
}
