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

	# http://grammaire.reverso.net/5_5_01_pluriel_des_noms_et_des_adjectifs.shtml

	$inflect->plural('/$/', 's');
	$inflect->singular('/s$/', '');

	$inflect->plural('/(bijou|caillou|chou|genou|hibou|joujou|pou|au|eu|eau)$/', '\1x');
	$inflect->singular('/(bijou|caillou|chou|genou|hibou|joujou|pou|au|eu|eau)x$/', '\1');

	$inflect->plural('/(bleu|émeu|landau|lieu|pneu|sarrau)$/', '\1s');
	$inflect->plural('/al$/', 'aux');
	$inflect->plural('/ail$/', 'ails');
	$inflect->singular('/(journ|chev)aux$/', '\1al');
	$inflect->singular('/ails$/', 'ail');

	$inflect->plural('/(b|cor|ém|gemm|soupir|trav|vant|vitr)ail$/', '\1aux');
	$inflect->singular('/(b|cor|ém|gemm|soupir|trav|vant|vitr)aux$/', '\1ail');

	$inflect->plural('/(s|x|z)$/', '\1');

	$inflect->irregular('monsieur', 'messieurs');
	$inflect->irregular('madame', 'mesdames');
	$inflect->irregular('mademoiselle', 'mesdemoiselles');
};