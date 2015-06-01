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
 * Spanish inflections.
 *
 * @see http://www.studyspanish.com/lessons/plnoun.htm
 * @see http://spanish.about.com/cs/writing/a/writing_plurals.htm
 */
return function(Inflections $inflect) {

	$inflect->plural('/$/', 's');
	$inflect->plural('/([^aeéiou])$/i', '\1es');
	$inflect->plural('/([aeiou]s)$/i', '\1');
	$inflect->plural('/z$/i', 'ces');
	$inflect->plural('/á([sn])$/i', 'a\1es');
	$inflect->plural('/é([sn])$/i', 'e\1es');
	$inflect->plural('/í([sn])$/i', 'i\1es');
	$inflect->plural('/ó([sn])$/i', 'o\1es');
	$inflect->plural('/ú([sn])$/i', 'u\1es');

	$inflect->singular('/s$/', '');
	$inflect->singular('/es$/', '');
	$inflect->singular('/ces$/', 'z');
	$inflect->singular('/iones$/', 'ión');
	$inflect->singular('/ereses$/', 'erés');

	$inflect->irregular('el', 'los');
	$inflect->irregular('lunes', 'lunes');
	$inflect->irregular('rompecabezas', 'rompecabezas');
	$inflect->irregular('crisis', 'crisis');
	$inflect->irregular('papá', 'papás');
	$inflect->irregular('mamá', 'mamás');
	$inflect->irregular('sofá', 'sofás');

	// because 'mes' is considered already a plural
	$inflect->irregular('mes', 'meses');
};