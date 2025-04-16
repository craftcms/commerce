<?php

namespace craft\commerce\base;

use craft\base\CpEditable;
use craft\base\Grippable;
use craft\base\Iconic;
use craft\db\Query;

interface ReportInterface extends Grippable, Iconic, CpEditable
{
    /**
     * Returns the database query that generates the report data
     */
    public function getQuery(): Query;
    
    /**
     * Returns the processed data for the report
     */
    public function getData(): array;
    
    /**
     * Returns the column definitions for the report
     * 
     * @return array Array of columns with keys:
     * - label: The user-facing column label
     * - value: The data key for this column
     * - type: The data type (string, number, money, percent, date)
     */
    public function getColumns(): array;
    
    /**
     * Return the start date for the report
     */
    public function getStartDate(): ?\DateTime;
    
    /**
     * Return the end date for the report
     */
    public function getEndDate(): ?\DateTime;
    
    /**
     * Set the start date for the report
     */
    public function setStartDate(?\DateTime $date): void;
    
    /**
     * Set the end date for the report
     */
    public function setEndDate(?\DateTime $date): void;
    
    /**
     * Returns custom parameter definitions for the report
     * 
     * @return array Array of parameter definitions with keys:
     * - type: The parameter type (select, text, number, checkbox)
     * - label: The user-facing parameter label
     * - handle: The parameter handle
     * - default: The default value
     * - options: Array of options for select type (with value and label keys)
     * - required: Whether the parameter is required (boolean)
     */
    public function getParams(): array;
    
    /**
     * Set parameters from request data
     * 
     * @param array $params The parameter values to set
     */
    public function setParams(array $params): void;
    
    /**
     * Get current parameter values as key-value array
     * 
     * @return array Parameter values
     */
    public function getParamValues(): array;
}
