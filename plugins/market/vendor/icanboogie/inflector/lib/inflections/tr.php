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

return function(Inflections $inflect) {

	$inflect->plural('/([aoıu][^aoıueöiü]{0,6})$/u', '\1lar');
	$inflect->plural('/([eöiü][^aoıueöiü]{0,6})$/u', '\1ler');

	$inflect->singular('/l[ae]r$/i', '');

	$inflect->irregular('ben', 'biz');
	$inflect->irregular('sen', 'siz');
	$inflect->irregular('o', 'onlar');

};