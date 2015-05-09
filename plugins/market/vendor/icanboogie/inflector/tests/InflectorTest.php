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

class InflectorTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var Inflector
	 */
	static private $inflector;

	static public function setUpBeforeClass()
	{
		self::$inflector = Inflector::get('en');
	}

	public function test_pluralize_plurals()
	{
		$this->assertEquals("plurals", self::$inflector->pluralize("plurals"));
		$this->assertEquals("Plurals", self::$inflector->pluralize("Plurals"));
	}

	public function test_pluralize_empty_string()
	{
		$this->assertEquals("", self::$inflector->pluralize(""));
	}

	public function test_uncountability()
	{
		foreach (self::$inflector->inflections->uncountables as $word)
		{
			$this->assertEquals($word, self::$inflector->singularize($word));
			$this->assertEquals($word, self::$inflector->pluralize($word));
			$this->assertEquals(self::$inflector->pluralize($word), self::$inflector->singularize($word));
		}
	}

	public function test_uncountable_word_is_not_greedy()
	{
		$inflector = clone self::$inflector;

		$uncountable_word = "ors";
		$countable_word = "sponsor";

		$inflector->inflections->uncountable($uncountable_word);

		$this->assertEquals($uncountable_word, $inflector->singularize($uncountable_word));
		$this->assertEquals($uncountable_word, $inflector->pluralize($uncountable_word));
		$this->assertEquals($inflector->pluralize($uncountable_word), $inflector->singularize($uncountable_word));

		$this->assertEquals("sponsor", $inflector->singularize($countable_word));
		$this->assertEquals("sponsors", $inflector->pluralize($countable_word));
		$this->assertEquals("sponsor", $inflector->singularize($inflector->pluralize($countable_word)));
	}

	public function test_pluralize_singulars()
	{
		$ar = require __DIR__ . '/cases/singular_to_plural.php';

		foreach ($ar as $singular => $plural)
		{
			$this->assertEquals($plural, self::$inflector->pluralize($singular));
			$this->assertEquals($plural, self::$inflector->pluralize($plural));
			$this->assertEquals(ucfirst($plural), self::$inflector->pluralize(ucfirst($singular)));
			$this->assertEquals(ucfirst($plural), self::$inflector->pluralize(ucfirst($plural)));
		}
	}

	public function test_singularize_plurals()
	{
		$ar = require __DIR__ . '/cases/singular_to_plural.php';

		foreach ($ar as $singular => $plural)
		{
			$this->assertEquals($singular, self::$inflector->singularize($plural));
			$this->assertEquals($singular, self::$inflector->singularize($singular));
			$this->assertEquals(ucfirst($singular), self::$inflector->singularize(ucfirst($plural)));
			$this->assertEquals(ucfirst($singular), self::$inflector->singularize(ucfirst($singular)));
		}
	}

	public function test_camelize()
	{
		$ar = require __DIR__ . '/cases/camel_to_underscore.php';

		foreach ($ar as $camel => $underscore)
		{
			$this->assertEquals($camel, self::$inflector->camelize($underscore));
			$this->assertEquals($camel, camelize($underscore));
		}

		$ar = require __DIR__ . '/cases/camel_to_dash.php';

		foreach ($ar as $camel => $dash)
		{
			$this->assertEquals($camel, self::$inflector->camelize($dash));
			$this->assertEquals($camel, camelize($dash));
		}
	}

	public function test_camelize_with_lower_upcases_the_first_letter()
	{
		$this->assertEquals('Capital', self::$inflector->camelize('capital'));
		$this->assertEquals('Capital', camelize('capital'));
	}

	public function test_camelize_with_lower_downcases_the_first_letter()
	{
		$this->assertEquals('capital', self::$inflector->camelize('Capital', true));
		$this->assertEquals('capital', camelize('Capital', true));
	}

	public function test_camelize_with_underscores()
	{
		$this->assertEquals("CamelCase", self::$inflector->camelize('Camel_Case'));
		$this->assertEquals("CamelCase", camelize('Camel_Case'));
	}

	public function test_acronyms()
	{
		$inflect = clone self::$inflector;
		$inflections = $inflect->inflections;
		$inflections->acronym("API");
		$inflections->acronym("HTML");
		$inflections->acronym("HTTP");
		$inflections->acronym("RESTful");
		$inflections->acronym("W3C");
		$inflections->acronym("PhD");
		$inflections->acronym("RoR");
		$inflections->acronym("SSL");

		# camelize underscore humanize titleize
		$ar = array
		(
			array("API",               "api",                "API",                "API"),
			array("APIController",     "api_controller",     "API controller",     "API Controller"),
			array("Nokogiri\HTML",     "nokogiri/html",      "Nokogiri/HTML",      "Nokogiri/HTML"),
			array("HTTPAPI",           "http_api",           "HTTP API",           "HTTP API"),
			array("HTTP\Get",          "http/get",           "HTTP/get",           "HTTP/Get"),
			array("SSLError",          "ssl_error",          "SSL error",          "SSL Error"),
			array("RESTful",           "restful",            "RESTful",            "RESTful"),
			array("RESTfulController", "restful_controller", "RESTful controller", "RESTful Controller"),
			array("IHeartW3C",         "i_heart_w3c",        "I heart W3C",        "I Heart W3C"),
			array("PhDRequired",       "phd_required",       "PhD required",       "PhD Required"),
			array("IRoRU",             "i_ror_u",            "I RoR u",            "I RoR U"),
			array("RESTfulHTTPAPI",    "restful_http_api",   "RESTful HTTP API",   "RESTful HTTP API"),

			# misdirection
			array("Capistrano",        "capistrano",         "Capistrano",         "Capistrano"),
			array("CapiController",    "capi_controller",    "Capi controller",    "Capi Controller"),
			array("HttpsApis",         "https_apis",         "Https apis",         "Https Apis"),
			array("Html5",             "html5",              "Html5",              "Html5"),
			array("Restfully",         "restfully",          "Restfully",          "Restfully"),
			array("RoRails",           "ro_rails",           "Ro rails",           "Ro Rails")
		);

		foreach ($ar as $a)
		{
			list($camel, $under, $human, $title) = $a;

			$this->assertEquals($camel, $inflect->camelize($under));
			$this->assertEquals($camel, $inflect->camelize($camel));
			$this->assertEquals($under, $inflect->underscore($under));
			$this->assertEquals($under, $inflect->underscore($camel));
			$this->assertEquals($title, $inflect->titleize($under));
			$this->assertEquals($title, $inflect->titleize($camel));
			$this->assertEquals($human, $inflect->humanize($under));
		}
	}

	public function test_acronym_override()
	{
		$inflect = clone self::$inflector;
		$inflections = $inflect->inflections;
		$inflections->acronym("API");
		$inflections->acronym("LegacyApi");

		$this->assertEquals("LegacyApi", $inflect->camelize("legacyapi"));
		$this->assertEquals("LegacyAPI", $inflect->camelize("legacy_api"));
		$this->assertEquals("SomeLegacyApi", $inflect->camelize("some_legacyapi"));
		$this->assertEquals("Nonlegacyapi", $inflect->camelize("nonlegacyapi"));
	}

	public function test_acronyms_camelize_lower()
	{
		$inflect = clone self::$inflector;
		$inflections = $inflect->inflections;
		$inflections->acronym("API");
		$inflections->acronym("HTML");

		$this->assertEquals("htmlAPI", $inflect->camelize("html_api", true));
		$this->assertEquals("htmlAPI", $inflect->camelize("htmlAPI", true));
		$this->assertEquals("htmlAPI", $inflect->camelize("HTMLAPI", true));
	}

	public function test_underscore_to_lower_camel()
	{
		foreach (require __DIR__ . '/cases/underscore_to_lower_camel.php' as $underscored => $lower_camel)
		{
			$this->assertEquals($lower_camel, self::$inflector->camelize($underscored, true));
		}
	}

	public function test_camelize_with_namespace()
	{
		foreach (require __DIR__ . '/cases/camel_with_namespace_to_underscore_with_slash.php' as $camel => $underscore)
		{
			$this->assertEquals($camel, self::$inflector->camelize($underscore));
		}
	}

	public function test_underscore_acronym_sequence()
	{
		$inflect = clone self::$inflector;
		$inflections = $inflect->inflections;
		$inflections->acronym("API");
		$inflections->acronym("JSON");
		$inflections->acronym("HTML");

		$this->assertEquals("json_html_api", $inflect->underscore("JSONHTMLAPI"));
	}

	public function test_underscore()
	{
		foreach (require __DIR__ . '/cases/camel_to_underscore.php' as $camel => $underscore)
		{
			$this->assertEquals($underscore, self::$inflector->underscore($camel));
		}

		foreach (require __DIR__ . '/cases/camel_to_underscore_without_reverse.php' as $camel => $underscore)
		{
			$this->assertEquals($underscore, self::$inflector->underscore($camel));
		}
	}

	public function test_underscore_with_slashes()
	{
		foreach (require __DIR__ . '/cases/camel_with_namespace_to_underscore_with_slash.php' as $camel => $underscore)
		{
			$this->assertEquals($underscore, self::$inflector->underscore($camel));
		}
	}

	public function test_hyphenate()
	{
		$ar = array
		(
			'AlterCSSClassNames' => 'alter-css-class-names'
		)

		+ require __DIR__ . '/cases/underscores_to_dashes.php';

		$inflector = self::$inflector;

		foreach ($ar as $str => $hyphenated)
		{
			$this->assertEquals($hyphenated, $inflector->hyphenate($str));
		}
	}

	public function test_humanize()
	{
		foreach (require __DIR__ . '/cases/underscore_to_human.php' as $underscore => $human)
		{
			$this->assertEquals($human, self::$inflector->humanize($underscore));
		}
	}

	public function test_humanize_by_rule()
	{
		$inflector = clone self::$inflector;
		$inflections = $inflector->inflections;
		$inflections->human('/_cnt$/i', '\1_count');
		$inflections->human('/^prefx_/i', '\1');

		$this->assertEquals("Jargon count", $inflector->humanize("jargon_cnt"));
		$this->assertEquals("Request", $inflector->humanize("prefx_request"));
	}

	public function test_humanize_by_string()
	{
		$inflector = clone self::$inflector;
		$inflections = $inflector->inflections;
		$inflections->human("col_rpted_bugs", "Reported bugs");

		$this->assertEquals("Reported bugs", $inflector->humanize("col_rpted_bugs"));
		$this->assertEquals("Col rpted bugs", $inflector->humanize("COL_rpted_bugs"));
	}

	public function test_ordinal()
	{
		foreach (require __DIR__ . '/cases/ordinal_numbers.php' as $number => $ordinalized)
		{
			$this->assertEquals($ordinalized, $number . self::$inflector->ordinal($number));
		}
	}

	public function test_ordinalize()
	{
		foreach (require __DIR__ . '/cases/ordinal_numbers.php' as $number => $ordinalized)
		{
			$this->assertEquals($ordinalized, self::$inflector->ordinalize($number));
		}
	}

	public function test_dasherize()
	{
		foreach (require __DIR__ . '/cases/underscores_to_dashes.php' as $underscored => $dasherized)
		{
			$this->assertEquals($dasherized, self::$inflector->dasherize($underscored));
		}
	}

	public function test_underscore_as_reverse_of_dasherize()
	{
		foreach (require __DIR__ . '/cases/underscores_to_dashes.php' as $underscored => $dasherized)
		{
			$this->assertEquals($underscored, self::$inflector->underscore(self::$inflector->dasherize($underscored)));
		}
	}

	public function test_irregularities_between_singular_and_plural()
	{
		$inflect = clone self::$inflector;

		foreach (require __DIR__ . '/cases/irregularities.php' as $singular => $plural)
		{
			$inflect->inflections->irregular($singular, $plural);
			$this->assertEquals($singular, $inflect->singularize($plural));
			$this->assertEquals($plural, $inflect->pluralize($singular));
		}
	}

	public function test_pluralize_of_irregularity_plural_should_be_the_same()
	{
		$inflect = clone self::$inflector;

		foreach (require __DIR__ . '/cases/irregularities.php' as $singular => $plural)
		{
			$inflect->inflections->irregular($singular, $plural);
			$this->assertEquals($plural, $inflect->pluralize($plural));
		}
	}

	public function test_pluralize_of_irregularity_singular_should_be_the_same()
	{
		$inflect = clone self::$inflector;

		foreach (require __DIR__ . '/cases/irregularities.php' as $singular => $plural)
		{
			$inflect->inflections->irregular($singular, $plural);
			$this->assertEquals($singular, $inflect->singularize($singular));
		}
	}
}