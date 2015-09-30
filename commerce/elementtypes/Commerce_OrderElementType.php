<?php
namespace Craft;

require_once(__DIR__.'/Commerce_BaseElementType.php');

/**
 * Class Commerce_OrderElementType
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.elementtypes
 * @since     1.0
 */
class Commerce_OrderElementType extends Commerce_BaseElementType
{

	/**
	 * @return null|string
	 */
	public function getName ()
	{
		return Craft::t('Orders');
	}

	/**
	 * @return bool
	 */
	public function hasContent ()
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function hasTitles ()
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public function hasStatuses ()
	{
		return false;
	}


	/**
	 * @param null $context
	 *
	 * @return array
	 */
	public function getSources ($context = null)
	{
		$sources = [
			'*' => [
				'label'    => Craft::t('All Orders'),
				'criteria' => ['completed' => true]
			]
		];

		$sources[] = ['heading' => Craft::t("Order Status")];

		foreach (craft()->commerce_orderStatus->getAll() as $orderStatus)
		{
			$key = 'orderStatus:'.$orderStatus->handle;
			$sources[$key] = [
				'statusColor' => $orderStatus->color,
				'label'       => $orderStatus->name,
				'criteria'    => ['orderStatus' => $orderStatus]
			];
		}


		$sources[] = ['heading' => Craft::t("Carts")];

		$edge = new DateTime();
		$interval = new DateInterval("PT1H");
		$interval->invert = 1;
		$edge->add($interval);

		$sources['carts:active'] = [
			'label'    => Craft::t('Active Carts'),
			'criteria' => ['updatedAfter' => $edge, 'dateOrdered' => ":empty:"]
		];

		$sources['carts:inactive'] = [
			'label'    => Craft::t('Inactive Carts'),
			'criteria' => ['updatedBefore' => $edge, 'dateOrdered' => ":empty:"]
		];

		return $sources;
	}

	/**
	 * @param null $source
	 *
	 * @return array
	 */
	public function defineTableAttributes ($source = null)
	{
		if (explode(':', $source)[0] == 'carts')
		{
			return [
				'number'      => Craft::t('Number'),
				'dateUpdated' => Craft::t('Last Updated'),
				'totalPrice'  => Craft::t('Total')
			];
		}

		return [
			'number'      => Craft::t('Number'),
			'orderStatus' => Craft::t('Status'),
			'totalPrice'  => Craft::t('Total Payable'),
			'dateOrdered' => Craft::t('Completed'),
			'datePaid'    => Craft::t('Paid')
		];
	}

	/**
	 * @return array
	 */
	public function defineSearchableAttributes ()
	{
		return ['number'];
	}

	/**
	 * @param BaseElementModel $element
	 * @param string           $attribute
	 *
	 * @return mixed|string
	 */
	public function getTableAttributeHtml (BaseElementModel $element, $attribute)
	{

		if ($attribute == 'totalPrice')
		{
			$currency = craft()->commerce_settings->getOption('defaultCurrency');

			return craft()->numberFormatter->formatCurrency($element->totalPrice, strtoupper($currency));
		}

		if ($attribute == 'orderStatus')
		{
			if ($element->orderStatus)
			{
				return $element->orderStatus->printName();
			}
			else
			{
				return sprintf('<span class="commerce status %s"></span> %s', '', '');
			}
		}

		return parent::getTableAttributeHtml($element, $attribute);
	}

	/**
	 * @return array
	 */
	public function defineSortableAttributes ()
	{
		return [
			'number'        => Craft::t('Number'),
			'dateOrdered'   => Craft::t('Completed At'),
			'totalPrice'    => Craft::t('Total Payable'),
			'orderStatusId' => Craft::t('Order Status'),
		];
	}


	/**
	 * @return array
	 */
	public function defineCriteriaAttributes ()
	{
		return [
			'number'        => AttributeType::Mixed,
			'dateOrdered'   => AttributeType::Mixed,
			'updatedOn'     => AttributeType::Mixed,
			'updatedAfter'  => AttributeType::Mixed,
			'updatedBefore' => AttributeType::Mixed,
			'orderStatus'   => AttributeType::Mixed,
			'orderStatusId' => AttributeType::Mixed,
			'completed'     => AttributeType::Bool,
			'customer'      => AttributeType::Mixed,
			'customerId'    => AttributeType::Mixed,
			'user'          => AttributeType::Mixed,
		];
	}

	/**
	 * @param DbCommand            $query
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return void
	 */
	public function modifyElementsQuery (DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect('orders.id,
        orders.number,
        orders.couponCode,
        orders.itemTotal,
        orders.baseDiscount,
        orders.baseShippingCost,
        orders.totalPrice,
        orders.totalPaid,
        orders.orderStatusId,
        orders.dateOrdered,
        orders.email,
        orders.dateOrdered,
        orders.datePaid,
        orders.currency,
        orders.lastIp,
        orders.message,
        orders.returnUrl,
        orders.cancelUrl,
        orders.orderStatusId,
        orders.billingAddressId,
        orders.billingAddressData,
        orders.shippingAddressId,
        orders.shippingAddressData,
        orders.shippingMethodId,
        orders.paymentMethodId,
        orders.customerId,
        orders.dateUpdated')
			->join('commerce_orders orders', 'orders.id = elements.id');

		if ($criteria->completed)
		{
			if ($criteria->completed == true)
			{
				$query->andWhere('orders.dateOrdered is not null');
				$criteria->completed = null;
			}
		}

		if ($criteria->dateOrdered)
		{
			$query->andWhere(DbHelper::parseParam('orders.dateOrdered', $criteria->dateOrdered, $query->params));
		}

		if ($criteria->number)
		{
			$query->andWhere(DbHelper::parseParam('orders.number', $criteria->number, $query->params));
		}

		if ($criteria->orderStatus)
		{
			if ($criteria->orderStatus instanceof Commerce_OrderStatusModel)
			{
				$criteria->orderStatusId = $criteria->orderStatus->id;
				$criteria->orderStatus = null;
			}
			else
			{
				$query->andWhere(DbHelper::parseParam('orders.orderStatusId', $criteria->orderStatus, $query->params));
			}
		}

		if ($criteria->orderStatusId)
		{
			$query->andWhere(DbHelper::parseParam('orders.orderStatusId', $criteria->orderStatusId, $query->params));
		}

		if($criteria->user)
		{
			if ($criteria->user instanceof UserModel)
			{
				$id = craft()->commerce_customer->getById($criteria->user->id)->id;
				if ($id)
				{
					$criteria->customerId = $id;
					$criteria->customer = null;
				}
				else
				{
					$query->andWhere(DbHelper::parseParam('orders.customerId', 'IS NULL', $query->params));
				}
			}
		}

		if ($criteria->customer)
		{
			if ($criteria->customer instanceof Commerce_CustomerModel)
			{
				if ($criteria->customer->id)
				{
					$criteria->customerId = $criteria->customer->id;
					$criteria->customer = null;
				}
				else
				{
					$query->andWhere(DbHelper::parseParam('orders.customerId', 'IS NULL', $query->params));
				}
			}
		}

		if ($criteria->customerId)
		{
			$query->andWhere(DbHelper::parseParam('orders.customerId', $criteria->customerId, $query->params));
		}

		if ($criteria->updatedOn)
		{
			$query->andWhere(DbHelper::parseDateParam('orders.dateUpdated', $criteria->updatedOn, $query->params));
		}
		else
		{
			if ($criteria->updatedAfter)
			{
				$query->andWhere(DbHelper::parseDateParam('orders.dateUpdated', '>='.$criteria->updatedAfter, $query->params));
			}

			if ($criteria->updatedBefore)
			{
				$query->andWhere(DbHelper::parseDateParam('orders.dateUpdated', '<'.$criteria->updatedBefore, $query->params));
			}
		}
	}


	/**
	 * Populate the Order.
	 *
	 * @param array $row
	 *
	 * @return BaseModel
	 */
	public function populateElementModel ($row)
	{
		return Commerce_OrderModel::populateModel($row);
	}

}
