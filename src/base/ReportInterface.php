<?php

namespace craft\commerce\base;

use craft\base\CpEditable;
use craft\base\Grippable;
use craft\base\Iconic;
use craft\db\Query;

interface ReportInterface extends Grippable, Iconic, CpEditable
{
    public function getQuery(): Query;
}
