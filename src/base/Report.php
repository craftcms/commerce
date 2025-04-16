<?php

namespace craft\commerce\base;

use craft\db\Query;
use craft\helpers\UrlHelper;
use DateTime;

abstract class Report implements ReportInterface
{
    /**
     * @var DateTime|null
     */
    protected ?DateTime $_startDate = null;

    /**
     * @var DateTime|null
     */
    protected ?DateTime $_endDate = null;

    /**
     * @var array|null Cache for report data
     */
    protected ?array $_data = null;
    
    /**
     * @var array Custom parameter values
     */
    protected array $_paramValues = [];

    /**
     * Returns the report title
     * 
     * @return string
     */
    abstract public function getTitle(): string;

    /**
     * Returns the report handle
     * 
     * @return string|null
     */
    public function getHandle(): ?string
    {
        return null;
    }

    /**
     * Returns the report columns
     * 
     * @return array
     */
    abstract public function getColumns(): array;

    /**
     * @inheritDoc
     */
    public function getData(): array
    {
        if ($this->_data === null) {
            $this->_data = $this->getQuery()->all();
        }
        
        return $this->_data;
    }

    /**
     * Returns the CP URL for this report
     * 
     * @return string|null
     */
    public function getCpEditUrl(): ?string
    {
        return $this->getHandle() ? UrlHelper::cpUrl('commerce/reporting/' . $this->getHandle()) : null;
    }

    /**
     * Returns the icon for this report
     * 
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getQuery(): Query
    {
        return new Query();
    }
    
    /**
     * @inheritDoc
     */
    public function getStartDate(): ?DateTime
    {
        if ($this->_startDate === null) {
            $this->_startDate = (new DateTime())->modify('-30 days');
        }
        
        return $this->_startDate;
    }
    
    /**
     * @inheritDoc
     */
    public function getEndDate(): ?DateTime
    {
        if ($this->_endDate === null) {
            $this->_endDate = new DateTime();
        }
        
        return $this->_endDate;
    }
    
    /**
     * @inheritDoc
     */
    public function setStartDate(?DateTime $date): void
    {
        $this->_startDate = $date;
        $this->_data = null; // Reset cached data
    }
    
    /**
     * @inheritDoc
     */
    public function setEndDate(?DateTime $date): void
    {
        $this->_endDate = $date;
        $this->_data = null; // Reset cached data
    }
    
    /**
     * @inheritDoc
     */
    public function getParams(): array
    {
        return [];
    }
    
    /**
     * @inheritDoc
     */
    public function setParams(array $params): void
    {
        // Initialize with default values
        $this->_paramValues = [];
        
        foreach ($this->getParams() as $param) {
            $handle = $param['handle'];
            $default = $param['default'] ?? null;
            
            // Set from request or use default
            $this->_paramValues[$handle] = $params[$handle] ?? $default;
        }
        
        // Reset cached data since parameters changed
        $this->_data = null;
    }
    
    /**
     * @inheritDoc
     */
    public function getParamValues(): array
    {
        // If no params have been set yet, initialize with defaults
        if (empty($this->_paramValues)) {
            foreach ($this->getParams() as $param) {
                $handle = $param['handle'];
                $default = $param['default'] ?? null;
                $this->_paramValues[$handle] = $default;
            }
        }
        
        return $this->_paramValues;
    }
    
    /**
     * Returns all parameters as URL query string
     * 
     * @return string
     */
    public function getParamsAsQueryString(): string
    {
        $params = $this->getParamValues();
        
        // Add date parameters
        if ($this->getStartDate()) {
            $params['startDate'] = $this->getStartDate()->format('Y-m-d');
        }
        
        if ($this->getEndDate()) {
            $params['endDate'] = $this->getEndDate()->format('Y-m-d');
        }
        
        return http_build_query($params);
    }
    
    /**
     * Returns the headers for CSV export
     * 
     * @return array
     */
    public function getCsvHeaders(): array
    {
        $headers = [];
        
        foreach ($this->getColumns() as $column) {
            $headers[] = $column['label'];
        }
        
        return $headers;
    }
    
    /**
     * Returns the data formatted for CSV export
     * 
     * @return array
     */
    public function getCsvData(): array
    {
        $data = $this->getData();
        $columns = $this->getColumns();
        $rows = [];
        
        foreach ($data as $row) {
            $csvRow = [];
            
            foreach ($columns as $column) {
                $csvRow[] = $row[$column['value']] ?? '';
            }
            
            $rows[] = $csvRow;
        }
        
        return $rows;
    }
}
