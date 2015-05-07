# Inflector [![Build Status](https://travis-ci.org/ICanBoogie/Inflector.png?branch=master)](https://travis-ci.org/ICanBoogie/Inflector)

The Inflector transforms words from singular to plural, underscore to camel case, titelize words
and more. Inflections can be localized, the default english inflections for pluralization,
singularization, and uncountable words are kept in [lib/inflections/en.php](https://github.com/ICanBoogie/Inflector/blob/master/lib/inflections/en.php).

Inflections are currently available for the following languages:

- English (`en`)
- French (`fr`)
- Norwegian Bokmal (`nb`)
- Spanish (`es`)
- Turkish (`tr`)





### Usage

These are some examples of the inflector with the `en` locale (default).

```php
<?php

use ICanBoogie\Inflector;

$inflector = Inflector::get();

# pluralize

$inflector->pluralize('post');                       // "posts"
$inflector->pluralize('child');                      // "children"
$inflector->pluralize('sheep');                      // "sheep"
$inflector->pluralize('words');                      // "words"
$inflector->pluralize('CamelChild');                 // "CamelChildren"

# singularize

$inflector->singularize('posts');                    // "post"
$inflector->singularize('children');                 // "child"
$inflector->singularize('sheep');                    // "sheep"
$inflector->singularize('word');                     // "word"
$inflector->singularize('CamelChildren');            // "CamelChild"

# camelize

$inflector->camelize('active_model');                // 'ActiveModel'
$inflector->camelize('active_model', true);          // 'activeModel'
$inflector->camelize('active_model/errors');         // 'ActiveModel\Errors'
$inflector->camelize('active_model/errors', true);   // 'activeModel\Errors'

# underscore

$inflector->underscore('ActiveModel');               // 'active_model'
$inflector->underscore('ActiveModel\Errors');        // 'active_model/errors'

# humanize

$inflector->humanize('employee_salary');             // "Employee salary"
$inflector->humanize('author_id');                   // "Author"

# titleize

$inflector->titleize('man from the boondocks');      // "Man From The Boondocks"
$inflector->titleize('x-men: the last stand');       // "X Men: The Last Stand"
$inflector->titleize('TheManWithoutAPast');          // "The Man Without A Past"
$inflector->titleize('raiders_of_the_lost_ark');     // "Raiders Of The Lost Ark"

# ordinal

$inflector->ordinal(1);                              // "st"
$inflector->ordinal(2);                              // "nd"
$inflector->ordinal(1002);                           // "nd"
$inflector->ordinal(1003);                           // "rd"
$inflector->ordinal(-11);                            // "th"
$inflector->ordinal(-1021);                          // "st"

# ordinalize

$inflector->ordinalize(1);                           // "1st"
$inflector->ordinalize(2);                           // "2nd"
$inflector->ordinalize(1002);                        // "1002nd"
$inflector->ordinalize(1003);                        // "1003rd"
$inflector->ordinalize(-11);                         // "-11th"
$inflector->ordinalize(-1021);                       // "-1021st"
```

Helpers makes it easy to use default locale inflections.

```php
<?php

namespace ICanBoogie;

echo pluralize('child');                             // "children"
echo pluralize('genou', 'fr');                       // "genoux"
echo singularize('lærere', 'nb');                    // "lærer"
echo pluralize('üçgen', 'tr');                       // "üçgenler"
```




### Acknowledgements

Most of the code and documentation was adapted from [Ruby On Rails](http://rubyonrails.org/)'s 
[Inflector](http://api.rubyonrails.org/classes/ActiveSupport/Inflector.html) and
[David Celis](https://github.com/davidcelis)' [inflections](https://github.com/davidcelis/inflections).

Significant differences:

- The Ruby module separator `::` as been replaced by the PHP namespace separator `\`.
- The plural of "octopus" is "octopuses" (not "octopi"), the plural of "virus" is "viruses"
(not viri) and the pural of "cow" is "cows" (not "kine").
- The following methods have been removed: `tableize`, `classify`, `demodulize`,
`constantize`, `deconstantize` and `foreign_key`. They can be easily implemented in specific
inflectors.
- Added the `hyphenate` method, which is a combination of `underscore` and `dasherize`.
- One specifies `true` rather than `false` to `camelize()` to downcase the first letter of
the camel cased string.





## Requirement

The package requires PHP 5.3 or later.





## Installation

The recommended way to install this package is through [Composer](http://getcomposer.org/).
Create a `composer.json` file and run `php composer.phar install` command to install it:

```json
{
	"minimum-stability": "dev",
	"require":
	{
		"icanboogie/inflector": "*"
	}
}
```





### Cloning the repository

The package is [available on GitHub](https://github.com/ICanBoogie/Inflector), its repository can be
cloned with the following command line:

	$ git clone git://github.com/ICanBoogie/Inflector.git





## Documentation

The package is documented as part of the [ICanBoogie](http://icanboogie.org/) framework
[documentation](http://icanboogie.org/docs/). The documentation for the package and its
dependencies can be generated with the `make doc` command. The documentation is generated in
the `docs` directory using [ApiGen](http://apigen.org/). The package directory can later by
cleaned with the `make clean` command.

The following classes are documented: 

- [Inflector](http://icanboogie.org/docs/class-ICanBoogie.Inflector.html)
- [Inflections](http://icanboogie.org/docs/class-ICanBoogie.Inflections.html)





## Testing

The test suite is ran with the `make test` command. [Composer](http://getcomposer.org/) is
automatically installed as well as all the dependencies required to run the suite. The package
directory can later be cleaned with the `make clean` command.

The package is continuously tested by [Travis CI](http://about.travis-ci.org/).

[![Build Status](https://travis-ci.org/ICanBoogie/Inflector.png?branch=master)](https://travis-ci.org/ICanBoogie/Inflector)





## License

ICanBoogie/Inflector is licensed under the New BSD License - See the [LICENSE](https://raw.github.com/ICanBoogie/Inflector/master/LICENSE) file for details.