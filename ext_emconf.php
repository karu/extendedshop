<?php

########################################################################
# Extension Manager/Repository config file for ext "extendedshop".
#
# Auto generated 29-04-2012 16:38
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Webformat Shop System',
	'description' => 'This is a new shop extension with new features for the presentation of products and the management of the orders. This is a small list of the features now available:
- management for offer price e discount
- management for color and size options
- advanced management fo orders
- users registration
- static address and page title for the products
- ...
Beside the shop provides a locallang file with the translation of all the labels in the template. So, if you want a new language, you have to modify only the locallang.php file and not the template.
This shop also provides two native payment gateways for PayPal and for Banca Sella (the last with one-time passwords).
NOW SUPPORTS MULTI-LANGUAGE',
	'category' => 'plugin',
	'shy' => 0,
	'version' => '4.0.3',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'TYPO3_version' => '3.5.0-0.0.0',
	'PHP_version' => '3.0.0-0.0.0',
	'module' => 'mod1',
	'state' => 'beta',
	'uploadfolder' => 1,
	'createDirs' => '',
	'modify_tables' => 'tt_address,fe_users',
	'clearcacheonload' => 1,
	'lockType' => '',
	'author' => 'Mauro Lorenzutti - Webformat srl',
	'author_email' => 'mauro.lorenzutti@webformat.com',
	'author_company' => 'Webformat srl',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
);

?>