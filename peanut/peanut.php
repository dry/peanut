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
	private $default_layout = 'main.html';
	private $text_parser;
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
	private $output_layout_name;
	private $output_layout;
	private $unparsed_output;
	private $output;
	private $output_type = '.html';
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
		ob_start();
		$this->parse_files();
		$this->load_content();
		$this->parse_request();
		$this->determine_content();

		if ($this->output_status == 404 AND $this->request != '/404'.$this->file_extension)
		{
			ob_end_clean();
			header("Location: /404.html");
			exit;
		}

		$this->initialise_text_parser();
		$this->parse_content();
		$this->output_content();
		ob_end_flush();
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

			case 'text':
				$content_type = 'Content-Type: text/plain';
			break;

			default:
				$content_type = 'Content-Type: text/html';
		}

		header($content_type, TRUE, $this->output_status);
		echo $this->output;
		exit;
	}

	/**
	 * Parse the content for this request. This is where we check whether any content
	 * keys are arrays - arrays are going to be blocks of text that should be implemented
	 * as custom fields. 
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
				if ($key != 'title')
				{
					$this->unparsed_output[$key] = $this->parser->parse(trim($content));
				}
			}

			if ($this->output_layout)
			{
				$output = $this->output_layout;
			}
			else
			{
				$output = $this->default_layout;
			}

			foreach($this->unparsed_output AS $key => $value)
			{
				$output = str_replace(
					$this->left_delim.$key.$this->right_delim,
					$value,
					$output
				);
			}

			// Parse memory usage
			$output = str_replace(
				$this->left_delim.'memory_usage'.$this->right_delim,
				$this->get_memory_usage(),
				$output
			);

			// Let's get rid of any leftover variables
			$pattern = $this->left_delim.'.*'.$this->right_delim;
			$output = preg_replace('/'.$pattern.'/U', '', $output);		

			// We sort out any dynamic layouts
			if (substr($this->output_layout_name, -4, 4) == '.php')
			{
				$output = eval(' ?>'.$output.'<?php ');
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
		$plugin_path = $this->system_folder.DS.'plugins'.DS.$this->text_parser.DS;
		require_once $plugin_path.'plugin.'.$this->text_parser.'.php';
		$class = 'Plugin_'.$this->text_parser;
		$this->parser = new $class;	
	}

	/**
	 * Match up the request to a defined page. If we can't do so or if there is no
	 * content then we'll set the output status as 404.
	 *
	 * @access	private
	 * @return 	void
	 */
	private function determine_content()
	{
		// First we check whether the request is a page. If not i.e. it's
		// a directory/folder then we normalise it to be suffixed with
		// index and the user-defined file extension.
		$ext_length = strlen($this->file_extension);
		if (substr($this->request, -$ext_length, $ext_length) != $this->file_extension)
		{
			$this->request = rtrim($this->request, '/').'/';
			$this->request .= 'index'.$this->file_extension;
		}

		if (isset($this->pages_content[$this->request]))
		{
			$this->unparsed_output = $this->pages_content[$this->request];

			if (isset($this->unparsed_output['layout']))
			{
				$this->output_layout_name = trim($this->unparsed_output['layout']);
			}
			else
			{
				$this->output_layout_name = $this->default_layout;
			}

			$layout_path = $this->system_folder.DS.'layouts'.DS;
			$layout = $layout_path.$this->output_layout_name;
			$this->output_layout = file_get_contents($layout);

			// Special layout settings
			if (isset($this->unparsed_output['status']))
			{
				$this->output_status = intval($this->unparsed_output['status']);
			}

			if (isset($this->unparsed_output['type']))
			{
				$this->output_type = trim($this->unparsed_output['type']);
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
	}

	/**
	 * Interate through the detected pages and get their contents.
	 * This populates the $pages_content class variable.
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
			$content = file_get_contents($page);
			$content = preg_match_all('/([a-zA-Z]+):.*\R(.*)\-\-/Us', $content, $matches);
			unset($matches[0]);
			$content = array();
			$keys = array_flip($matches[1]);

			foreach($keys AS $key => $value)
			{
				$content[$key] = $matches[2][$value];
			}		

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

	/**
	 * Helper function to calculate the overall memory usage
	 *
	 * @access	private
	 * @return	string	Formatted memory usage
	 */
	 private function get_memory_usage()
	 {
		$units = array('b', 'kb', 'mb', 'gb');
		$total = memory_get_usage();

		return sprintf("%01.1f", ($total / 1024)).'KB';
	}
}
