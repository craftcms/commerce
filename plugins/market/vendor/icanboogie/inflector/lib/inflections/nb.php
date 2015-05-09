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

	$inflect->plural('/$/', 'er');
    $inflect->plural('/r$/i', 're');
    $inflect->plural('/e$/i', 'er');

    $inflect->singular('/er$/i', '');
    $inflect->singular('/re$/i', 'r');

    $inflect->irregular('konto', 'konti');

};