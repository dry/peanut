<?php if ( ! defined("PEANUT")) exit('Please check the documentation.');
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
class Peanut {

	// User configurable
	private $system_folder;
	private $default_layout;
	private $text_parser;
	private $plugins_enabled;

	// Default variables
	private $pages_folder = 'pages';
	private $layouts_folder = 'layouts';

	// Dynamically generated
	private $pages = array();
	private $pages_content = array();
	private $request;
	private $output;
	private $output_type = 'html';
	private $output_status = 200;

	public function __construct($config = array())
	{
		foreach($config AS $config_item => $config_value)
		{
			$this->{$config_item} = $config_value;
		}
	}

	public function run()
	{
		$this->parse_files();
		$this->load_spyc();
		$this->load_content();
		$this->parse_request();
		$this->parse_content();
		$this->output_content();
	}

	private function output_content()
	{
		switch($this->output_type)
		{
			case 'html':
				$content_type = 'Content-Type: text/html';
			break;
		}

		header($content_type, TRUE, $this->output_status);

		echo $this->output;
		exit;
	}

	private function parse_content()
	{
		if (isset($this->pages_content[$this->request]))
		{
			$page = $this->pages_content[$this->request];

			if (isset($page['layout']))
			{
				$layout_path = $this->system_folder.DS.'layouts'.DS;
				$output = file_get_contents($layout_path.$page['layout']);
				$output = str_replace('{title}', $page['title'], $output);
				$output = str_replace('{body}', $page['body'], $output);
				$this->output = $output;
			}
			else
			{
				echo $this->pages_content[$this->request]['title'];
			}
		}
		else
		{
			$error_layout_path = $this->system_folder.DS.'layouts'.DS.'404.html';
			$this->output = file_get_contents($error_layout_path);
			$this->output_status = 404;
		}
	}

	private function parse_request()
	{
		$keys = array(
			'REQUEST_URI',
			'QUERY_STRING',
			'REDIRECT_QUERY_STRING',
			'REDIRECT_URL'
		);

		foreach($keys AS $possible_key)
		{
			if (isset($_SERVER[$possible_key]))
			{
				$this->request = $_SERVER[$possible_key];
				break;
			}
		}

		if ($this->request == '/')
		{
			$this->request = DS.'index.txt';
		}
	}

	private function parse_files()
	{
		$pages = array();
		$path = $this->system_folder.DS.$this->pages_folder;

		$scan = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path)
		);

		foreach($scan AS $name => $object)
		{
			$pages[] = $name;
		}

		$this->pages = $pages;
	}

	private function load_content()
	{
		$path = $this->system_folder.DS.$this->pages_folder;

		foreach($this->pages AS $key => $page)
		{
			$request = str_replace($path, '', $page);
			$content = Spyc::YAMLLoad($page);

			if (isset($content['slug']))
			{
				$this->pages_content['/'.$content['slug']] = $content;
			}
			else
			{
				$this->pages_content[$request] = $content;
			}
		}
	}

	private function load_spyc()
	{
		$path = str_replace('/', DS, '/core/libraries/spyc/spyc.php');
		require_once $this->system_folder.$path;
	}

	private function debug($data)
	{
		echo '<pre>';
		print_r($data);
		echo '</pre>';
	}
}
