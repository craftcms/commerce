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

/**
 * The Inflector transforms words from singular to plural, class names to table names, modularized
 * class names to ones without, and class names to foreign keys. Inflections can be localized, the
 * default english inflections for pluralization, singularization, and uncountable words are
 * kept in `lib/inflections/en.php`.
 *
 * @property-read Inflections $inflections Inflections used by the inflector.
 */
class Inflector
{
	static private $inflectors = array();

	/**
	 * Returns an inflector for the specified locale.
	 *
	 * Note: Inflectors are shared for the same locale. If you need to alter an inflector you
	 * MUST clone it first.
	 *
	 * @param string $locale
	 *
	 * @return \ICanBoogie\Inflector
	 */
	static public function get($locale='en')
	{
		if (isset(self::$inflectors[$locale]))
		{
			return self::$inflectors[$locale];
		}

		return self::$inflectors[$locale] = new static(Inflections::get($locale));
	}

	/**
	 * Inflections used by the inflector.
	 *
	 * @var Inflections
	 */
	protected $inflections;

	/**
	 * Initializes the {@link $inflections} property.
	 *
	 * @param Inflections $inflections
	 */
	protected function __construct(Inflections $inflections=null)
	{
		$this->inflections = $inflections ?: new Inflections;
	}

	/**
	 * Returns the {@link $inflections} property.
	 *
	 * @param string $property
	 *
	 * @throws PropertyNotDefined in attempt to read an unaccessible property. If the {@link PropertyNotDefined}
	 * class is not available a {@link \InvalidArgumentException} is thrown instead.
	 */
	public function __get($property)
	{
		static $readers = array('inflections');

		if (in_array($property, $readers))
		{
			return $this->$property;
		}

		if (class_exists('ICanBoogie\PropertyNotDefined'))
		{
			throw new PropertyNotDefined(array($property, $this));
		}
		else
		{
			throw new \InvalidArgumentException("Property not defined: $property");
		}
	}

	/**
	 * Clone inflections.
	 */
	public function __clone()
	{
		$this->inflections = clone $this->inflections;
	}

	/**
	 * Applies inflection rules for {@link singularize} and {@link pluralize}.
	 *
	 * <pre>
	 * $this->apply_inflections('post', $this->plurals);    // "posts"
	 * $this->apply_inflections('posts', $this->singulars); // "post"
	 * </pre>
	 *
	 * @param string $word
	 * @param array $rules
	 *
	 * @return string
	 */
	private function apply_inflections($word, array $rules)
	{
		$rc = (string) $word;

		if (!$rc)
		{
			return $rc;
		}

		if (preg_match('/\b\w+\Z/', downcase($rc), $matches))
		{
			if (isset($this->inflections->uncountables[$matches[0]]))
			{
				return $rc;
			}
		}

		foreach ($rules as $rule => $replacement)
		{
			$rc = preg_replace($rule, $replacement, $rc, -1, $count);

			if ($count) break;
		}

		return $rc;
	}

	/**
	 * Returns the plural form of the word in the string.
	 *
	 * <pre>
	 * $this->pluralize('post');       // "posts"
	 * $this->pluralize('children');   // "child"
	 * $this->pluralize('sheep');      // "sheep"
	 * $this->pluralize('words');      // "words"
	 * $this->pluralize('CamelChild'); // "CamelChild"
	 * </pre>
	 *
	 * @param string $word
	 *
	 * @return string
	 */
	public function pluralize($word)
	{
		return $this->apply_inflections($word, $this->inflections->plurals);
	}

	/**
	 * The reverse of {@link pluralize}, returns the singular form of a word in a string.
	 *
	 * <pre>
	 * $this->singularize('posts');         // "post"
	 * $this->singularize('childred');      // "child"
	 * $this->singularize('sheep');         // "sheep"
	 * $this->singularize('word');          // "word"
	 * $this->singularize('CamelChildren'); // "CamelChild"
	 * </pre>
	 *
	 * @param string $word
	 *
	 * @return string
	 */
	public function singularize($word)
	{
		return $this->apply_inflections($word, $this->inflections->singulars);
	}

	/**
	 * By default, {@link camelize} converts strings to UpperCamelCase.
	 *
	 * {@link camelize} will also convert "/" to "\" which is useful for converting paths to
	 * namespaces.
	 *
	 * <pre>
	 * $this->camelize('active_model');                // 'ActiveModel'
	 * $this->camelize('active_model', true);          // 'activeModel'
	 * $this->camelize('active_model/errors');         // 'ActiveModel\Errors'
	 * $this->camelize('active_model/errors', true);   // 'activeModel\Errors'
	 * </pre>
	 *
	 * As a rule of thumb you can think of {@link camelize} as the inverse of {@link underscore},
	 * though there are cases where that does not hold:
	 *
	 * <pre>
	 * $this->camelize($this->underscore('SSLError')); // "SslError"
	 * </pre>
	 *
	 * @param string $term
	 * @param bool $downcase_first_letter If `false` then {@link camelize} produces
	 * lowerCamelCase.
	 *
	 * @return string
	 */
	public function camelize($term, $downcase_first_letter=false)
	{
		$string = (string) $term;
		$acronyms = $this->inflections->acronyms;

		if ($downcase_first_letter)
		{
			$string = preg_replace_callback('/^(?:' . trim($this->inflections->acronym_regex, '/') . '(?=\b|[A-Z_])|\w)/', function($matches) {

				return downcase($matches[0]);

			}, $string, 1);
		}
		else
		{
			$string = preg_replace_callback('/^[a-z\d]*/', function($matches) use($acronyms) {

				$m = $matches[0];

				return !empty($acronyms[$m]) ? $acronyms[$m] : capitalize($m);

			}, $string, 1);
		}

		$string = preg_replace_callback('/(?:_|-|(\/))([a-z\d]*)/i', function($matches) use($acronyms) {

			list(, $m1, $m2) = $matches;

			return $m1 . (isset($acronyms[$m2]) ? $acronyms[$m2] : capitalize($m2));

		}, $string);

		$string = str_replace('/', '\\', $string);

		return $string;
	}

	/**
	 * Makes an underscored, lowercase form from the expression in the string.
	 *
	 * Changes "\" to "/" to convert namespaces to paths.
	 *
	 * <pre>
	 * $this->underscore('ActiveModel');        // 'active_model'
	 * $this->underscore('ActiveModel\Errors'); // 'active_model/errors'
	 * </pre>
	 *
	 * As a rule of thumb you can think of {@link underscore} as the inverse of {@link camelize()},
	 * though there are cases where that does not hold:
	 *
	 * <pre>
	 * $this->camelize($this->underscore('SSLError')); // "SslError"
	 * </pre>
	 *
	 * @param string $camel_cased_word
	 *
	 * @return string
	 */
	public function underscore($camel_cased_word)
	{
		$word = (string) $camel_cased_word;
		$word = str_replace('\\', '/', $word);
		$word = preg_replace_callback('/(?:([A-Za-z\d])|^)(' . trim($this->inflections->acronym_regex, '/') . ')(?=\b|[^a-z])/', function($matches) {

			list(, $m1, $m2) = $matches;

			return $m1 . ($m1 ? '_' : '') . downcase($m2);

		}, $word);

		$word = preg_replace('/([A-Z\d]+)([A-Z][a-z])/', '\1_\2', $word);
		$word = preg_replace('/([a-z\d])([A-Z])/','\1_\2', $word);
		$word = strtr($word, "-", "_");
		$word = downcase($word);

		return $word;
	}

	/**
	 * Capitalizes the first word and turns underscores into spaces and strips a trailing "_id",
	 * if any. Like {@link titleize()}, this is meant for creating pretty output.
	 *
	 * <pre>
	 * $this->humanize('employee_salary'); // "Employee salary"
	 * $this->humanize('author_id');       // "Author"
	 * </pre>
	 *
	 * @param string $lower_case_and_underscored_word
	 *
	 * @return string
	 */
	public function humanize($lower_case_and_underscored_word)
	{
		$result = (string) $lower_case_and_underscored_word;

		foreach ($this->inflections->humans as $rule => $replacement)
		{
			$result = preg_replace($rule, $replacement, $result, 1, $count);

			if ($count) break;
		}

		$acronyms = $this->inflections->acronyms;

		$result = preg_replace('/_id$/', "", $result);
		$result = strtr($result, '_', ' ');
		$result = preg_replace_callback('/([a-z\d]*)/i', function($matches) use($acronyms) {

			list($m) = $matches;

			return !empty($acronyms[$m]) ? $acronyms[$m] : downcase($m);
		}, $result);

		$result = preg_replace_callback('/^\w/', function($matches) {

			return upcase($matches[0]);

		}, $result);

		return $result;
	}

	/**
	 * Capitalizes all the words and replaces some characters in the string to create a nicer
	 * looking title. {@link titleize()} is meant for creating pretty output. It is not used in
	 * the Rails internals.
	 *
	 * <pre>
	 * $this->titleize('man from the boondocks');  // "Man From The Boondocks"
	 * $this->titleize('x-men: the last stand');   // "X Men: The Last Stand"
	 * $this->titleize('TheManWithoutAPast');      // "The Man Without A Past"
	 * $this->titleize('raiders_of_the_lost_ark'); // "Raiders Of The Lost Ark"
	 * </pre>
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function titleize($str)
	{
		$str = $this->underscore($str);
		$str = $this->humanize($str);
		$str = preg_replace_callback('/\b(?<![\'’`])[a-z]/', function($matches) {

			return capitalize($matches[0]);

		}, $str);

		return $str;
	}

	/**
	 * Replaces underscores with dashes in the string.
	 *
	 * <pre>
	 * $this->dasherize('puni_puni'); // "puni-puni"
	 * </pre>
	 *
	 * @param string $underscored_word
	 *
	 * @return string
	 */
	public function dasherize($underscored_word)
	{
		return strtr($underscored_word, '_', '-');
	}

	/**
	 * Makes an hyphenated, lowercase form from the expression in the string.
	 *
	 * This is a combination of {@link underscore} and {@link dasherize}.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function hyphenate($str)
	{
		return $this->dasherize($this->underscore($str));
	}

	/**
	 * Returns the suffix that should be added to a number to denote the position in an ordered
	 * sequence such as 1st, 2nd, 3rd, 4th.
	 *
	 * <pre>
	 * $this->ordinal(1);     // "st"
	 * $this->ordinal(2);     // "nd"
	 * $this->ordinal(1002);  // "nd"
	 * $this->ordinal(1003);  // "rd"
	 * $this->ordinal(-11);   // "th"
	 * $this->ordinal(-1021); // "st"
	 * </pre>
	 */
	public function ordinal($number)
	{
		$abs_number = abs($number);

		if (($abs_number % 100) > 10 && ($abs_number % 100) < 14)
		{
			return 'th';
		}

		switch ($abs_number % 10)
		{
			case 1; return "st";
			case 2; return "nd";
			case 3; return "rd";
			default: return "th";
		}
	}

	/**
	 * Turns a number into an ordinal string used to denote the position in an ordered sequence
	 * such as 1st, 2nd, 3rd, 4th.
	 *
	 * <pre>
	 * $this->ordinalize(1);     // "1st"
	 * $this->ordinalize(2);     // "2nd"
	 * $this->ordinalize(1002);  // "1002nd"
	 * $this->ordinalize(1003);  // "1003rd"
	 * $this->ordinalize(-11);   // "-11th"
	 * $this->ordinalize(-1021); // "-1021st"
	 * </pre>
	 */
	public function ordinalize($number)
	{
		return $number . $this->ordinal($number);
	}
}