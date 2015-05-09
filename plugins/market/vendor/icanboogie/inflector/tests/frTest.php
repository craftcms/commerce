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

class FrenchInflectionsTest extends \PHPUnit_Framework_TestCase
{
	public function test_plurals()
	{
		foreach (require __DIR__ . '/cases/fr/singular_to_plural.php' as $plural => $singular)
		{
			$this->assertEquals($plural, pluralize($singular, 'fr'));
		}
	}

	public function test_singular()
	{
		foreach (require __DIR__ . '/cases/fr/singular_to_plural.php' as $plural => $singular)
		{
			$this->assertEquals($singular, singularize($plural, 'fr'));
		}
	}

	public function test_irregular()
	{
		foreach (require __DIR__ . '/cases/fr/irregular.php' as $singular => $plural)
		{
			$this->assertEquals($singular, singularize($plural, 'fr'));
			$this->assertEquals($plural, pluralize($singular, 'fr'));
		}
	}
}