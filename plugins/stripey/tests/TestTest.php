<?php

class TestTest extends PHPUnit_Framework_TestCase
{
    public function testAssertWorks()
    {
        $stack = array();
        $this->assertEquals(0, count($stack));
    }

}