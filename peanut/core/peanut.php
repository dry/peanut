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
	private $left_delim = '{';
	private $right_delim = '}';

	// Default variables
	private $pages_folder = 'pages';
	private $layouts_folder = 'layouts';

	// Dynamically generated
	private $pages = array();
	private $pages_content = array();
	private $request;
	private $output_layout;
	private $unparsed_output;
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
		$this->determine_content();
		$this->remove_meta_keys();
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
		$output = '';

		if (isset($this->unparsed_output['body']))
		{
			$body = implode(NL.NL, $this->unparsed_output['body']);

			$this->unparsed_output['body'] = $body;

			if ($this->output_layout)
			{
				$output = $this->output_layout;

				foreach($this->unparsed_output AS $key => $value)
				{
					$output = str_replace(
						$this->left_delim.$key.$this->right_delim,
						$value,
						$output
					);
				}
			}
			else
			{
				foreach($this->unparsed_output AS $key => $value)
				{
					$output .= $value;
				}
			}

			$this->output = $output;
		}
	}

	/**
	 * Remove known meta keys from the content.
	 *
	 * @access	private
	 * @return	void
	 */
	private function remove_meta_keys()
	{
		$known_metas = array(
			'slug' => '',
			'layout' => ''
		);

		foreach($this->pages_content AS $key => $content)
		{
			$this->pages_content[$key] = array_diff_key(
				$content,
				$known_metas
			);
		}
	}

	/**
	 * Match up the request to a defined page. If we can't do so then we'll
	 * set the output status as 404.
	 *
	 * @access	private
	 * @return 	void
	 */
	private function determine_content()
	{
		if (isset($this->pages_content[$this->request]))
		{
			$this->unparsed_output = $this->pages_content[$this->request];

			if (isset($this->unparsed_output['layout']))
			{
				$layout_path = $this->system_folder.DS.'layouts'.DS;
				$this->output_layout = file_get_contents(
					$layout_path.$this->unparsed_output['layout']
				);
			}
		}
		else
		{
			$this->output_status = 404;
		}
	}

	/**
	 * The .htaccess file effectively turns every request into a query so
	 * we'll only check the possible server query string environment variables
	 * and ignore everything else. We assume that all requests are prefixed
	 * with DIRECTORY_SEPARATOR.
	 *
	 * @access	private
	 * @return	void
	 */
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

	/**
	 * Interate through the detected pages and get their contents.
	 * This populates the $pages_content class variable. If the content
	 * contains a slug then the $pages_content is keyed with that instead
	 * of the page filename.
	 *
	 * @access	private
	 * @return	void
	 */
	private function load_content()
	{
		$path = $this->system_folder.DS.$this->pages_folder;

		foreach($this->pages AS $key => $page)
		{
			$request = str_replace($path, '', $page);
			$content = Spyc::YAMLLoad($page);

			// Normalise the array keys to lowercase
			$content = array_change_key_case($content, CASE_LOWER);

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

	/**
	 * @access	private
	 * @return	void
	 */
	private function load_spyc()
	{
		$path = str_replace('/', DS, '/core/libraries/spyc/spyc.php');
		require_once $this->system_folder.$path;
	}

	/**
	 * Grab all the defined pages and assign them to the $pages
	 * class variable.
	 *
	 * @access	private
	 * @return	void
	 */	
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

	private function debug($data)
	{
		echo '<pre>';
		print_r($data);
		echo '</pre>';
	}
}
