<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

class SpanishInflectionsTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var Inflector
	 */
	static private $inflector;

	static public function setUpBeforeClass()
	{
		self::$inflector = Inflector::get('es');
	}

	/**
	 * @dataProvider provide_singular_to_plural
	 */
	public function test_singular_to_plural($singular, $plural)
	{
		$this->assertEquals($plural, self::$inflector->pluralize($singular));
	}

	/**
	 * @dataProvider provide_irregular
	 */
	public function test_irregular_singular_to_plural($singular, $plural)
	{
		$this->assertEquals($plural, self::$inflector->pluralize($singular));
	}

	/**
	 * @dataProvider provide_singular_to_plural
	 */
	public function test_plural_to_singular($singular, $plural)
	{
		$this->assertEquals($singular, self::$inflector->singularize($plural));
	}

	/**
	 * @dataProvider provide_irregular
	 */
	public function test_irregular_plural_to_singular($singular, $plural)
	{
		$this->assertEquals($singular, self::$inflector->singularize($plural));
	}

	public function provide_singular_to_plural()
	{
		$singular_to_plural = require __DIR__ . '/cases/es/singular_to_plural.php';
		$dataset = array();

		foreach ($singular_to_plural as $singular => $plural)
		{
			$dataset[] = array($singular, $plural);
		}

		return $dataset;
	}

	public function provide_irregular()
	{
		$irregulars = require __DIR__ . '/cases/es/irregular.php';
		$dataset = array();

		foreach ($irregulars as $singular => $plural)
		{
			$dataset[] = array($singular, $plural);
		}

		return $dataset;
	}
}