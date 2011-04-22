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
	private $file_extension = '.html';

	// Default variables
	private $pages_folder = 'pages';
	private $layouts_folder = 'layouts';

	// Dynamically generated
	private $pages = array();
	private $pages_content = array();
	private $request;
	private $parser;
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

	/**
	 * @access	private
	 * @return	void
	 */
	public function run()
	{
		$this->parse_files();
		$this->load_spyc();
		$this->load_content();
		$this->parse_request();
		$this->determine_content();

		if ($this->output_status == 404 AND $this->request != '/404.html')
		{
			header("Location: /404.html");
			exit;
		}

		$this->remove_meta_keys();
		$this->initialise_text_parser();
		$this->parse_content();
		$this->output_content();
	}

	/**
	 * @access	private
	 * @return	void
	 */
	private function output_content()
	{
		switch($this->output_type)
		{
			case 'html':
				$content_type = 'Content-Type: text/html';
			break;

			case 'plain':
				$content_type = 'Content-Type: text/plain';
			break;
		}

		header($content_type, TRUE, $this->output_status);
		echo $this->output;
		exit;
	}

	/**
	 * Parse the content for this request. This is where we check whether any content
	 * keys are arrays - arrays are going to be blocks of text that should be imploded. 
	 * 
	 * @access	private
	 * @return	void
	 */ 
	private function parse_content()
	{
		$output = '';

		if ( ! is_array($this->unparsed_output))
		{
			$output = $this->unparsed_output;
		}
		else
		{
			foreach($this->unparsed_output AS $key => $content)
			{
				if (is_array($content))
				{
					$this->unparsed_output[$key] = implode(NL, $content);
				}
			}

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
		}

		$this->output = $output;
	}

	/**
	 * The text parsers are defined as plugins.
	 *
	 * @access	private
	 * @return	void
	 */
	private function initialise_text_parser()
	{
		$plugin_path = $this->system_folder.DS.'core'.DS.'plugins'.DS.$this->text_parser.DS;
		require_once $plugin_path.'plugin.'.$this->text_parser.'.php';
		$class = 'Plugin_'.$this->text_parser;
		$this->parser = new $class;	
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
	 * Match up the request to a defined page. If we can't do so or if there is no
	 * content then we'll * set the output status as 404.
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
			$error_path = $this->system_folder.DS.'layouts'.DS.'404.html';
			$this->unparsed_output = file_get_contents($error_path);
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
			$this->request = DS.'index'.$this->file_extension;
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

			if (is_array($content))
			{
				// Normalise the array keys to lowercase
				$content = array_change_key_case($content, CASE_LOWER);

				// Make sure that the indexes are suffixed with the
				// default or user-defined file extension.
				$request = str_replace('.txt', $this->file_extension, $request);

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
