<?php

class ExampleTest extends \PHPUnit_Framework_TestCase
{
	public $craft;

    protected function setUp()
    {
		$this->craft = \Craft\craft();
    }

    protected function tearDown()
    {
    }

    // tests
    public function testIsCpRequest()
    {
		$d = $this->craft->config->resourceTrigger;
		$this->assertTrue($d == "cpresources");
    }

}