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

class NorwegianBokmalInflectionsTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var Inflector
	 */
	static private $inflector;

	static public function setUpBeforeClass()
	{
		self::$inflector = Inflector::get('nb');
	}

	public function test_svake_substantiv_er()
	{
		$this->assertEquals('hunder', self::$inflector->pluralize('hund'));
		$this->assertEquals('hund', self::$inflector->singularize('hunder'));

		$this->assertEquals('dager', self::$inflector->pluralize('dag'));
		$this->assertEquals('dag', self::$inflector->singularize('dager'));

		$this->assertEquals('tester', self::$inflector->pluralize('test'));
		$this->assertEquals('test', self::$inflector->singularize('tester'));
	}

	public function test_svake_substantiv_r()
	{
		$this->assertEquals('lærere', self::$inflector->pluralize('lærer'));
		$this->assertEquals('lærer', self::$inflector->singularize('lærere'));

		$this->assertEquals('kalendere', self::$inflector->pluralize('kalender'));
		$this->assertEquals('kalender', self::$inflector->singularize('kalendere'));
	}

	public function test_svake_substantiv_e()
	{
		$this->assertEquals('bakker', self::$inflector->pluralize('bakke'));
		# TODO: Fix this. How do we keep this form apart from "hunder"?
		# $this->assertEquals('bakke', 'bakker'.singularize(:nb)

		$this->assertEquals('epler', self::$inflector->pluralize('eple'));
		# $this->assertEquals('eple', self::$inflector->singularize('epler'));
	}

	public function test_sterke_verb()
	{
		$this->assertEquals('konti', self::$inflector->pluralize('konto'));
		$this->assertEquals('konto', self::$inflector->singularize('konti'));
	}
}