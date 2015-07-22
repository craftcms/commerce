<?php

namespace Market\Helpers;

class MarketDbHelper
{
	private static $transactionsStackSize = 0;
	/** @var \CDbTransaction */
	private static $transaction = NULL;

	/**
	 * This and two next are special functions which allow making nested
	 * transactions
	 */
	public static function beginStackedTransaction()
	{
		if (self::$transactionsStackSize == 0) {
			if(\Craft\craft()->db->getCurrentTransaction() === null){
				self::$transaction = \Craft\craft()->db->beginTransaction();
			}else{
				// If we are at zero but 3rd party has a current transaction in play
				self::$transaction = \Craft\craft()->db->getCurrentTransaction();
				// By setting to 1, we will never commit, but whoever started it should.
				self::$transactionsStackSize = 1;
			}
		}

		++self::$transactionsStackSize;
	}

	public static function commitStackedTransaction()
	{
		self::$transactionsStackSize && --self::$transactionsStackSize; //decrement only when positive

		if (self::$transactionsStackSize == 0) {
			self::$transaction->commit();
		}
	}

	public static function rollbackStackedTransaction()
	{
		self::$transactionsStackSize && --self::$transactionsStackSize; //decrement only when positive

		if (self::$transactionsStackSize == 0) {
			self::$transaction->rollback();
		}
	}
}