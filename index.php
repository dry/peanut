<?php
/**
  * ------------------------------------------------------------------
  * Peanut File-based CMS
  * ------------------------------------------------------------------
  * @package	PeanutCMS	
  * @author		Purple Dogfish Ltd <hello@purple-dogfish.co.uk>	
  * @copyright	Copyright (c) 2011, Purple Dogfish Ltd 
  * @license	http://peanutcms.com/documentation/license.html
  * @link		http://peanutcms.com
 */

// User configuration 
$config = array(
	'system_folder' => 'peanut',
	'default_layout' => 'main.html',
	'text_parser' => 'textile',
	'plugins_enabled' => array()
);

// Do not edit below this line
define("PEANUT", TRUE);
define("DS", DIRECTORY_SEPARATOR);
define("NL", PHP_EOL);

if (is_dir($config['system_folder']))
{
	$system_folder_path = dirname(__FILE__).DS.$config['system_folder'];
	$config['system_folder'] = $system_folder_path;
	$core_class_path = $system_folder_path.DS.'core'.DS.'peanut.php';
	require_once $core_class_path; 
}

$peanut = new Peanut($config);
$peanut->run();
