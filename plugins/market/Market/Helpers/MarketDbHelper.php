<?php

namespace Market\Helpers;

class MarketDbHelper {
    private static $transactionsStackSize = 0;
    /** @var \CDbTransaction */
    private static $transaction = null;

    /**
     * This and two next are special functions which allow making nested transactions
*/
    public static function beginStackedTransaction()
    {
        if(self::$transactionsStackSize == 0) {
            self::$transaction = \Craft\craft()->db->beginTransaction();
        }

        ++self::$transactionsStackSize;
    }

    public static function commitStackedTransaction()
    {
        self::$transactionsStackSize && --self::$transactionsStackSize; //decrement only when positive

        if(self::$transactionsStackSize == 0) {
            self::$transaction->commit();
        }
    }

    public static function rollbackStackedTransaction()
    {
        self::$transactionsStackSize && --self::$transactionsStackSize; //decrement only when positive

        if(self::$transactionsStackSize == 0) {
            self::$transaction->rollback();
        }
    }
}