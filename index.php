<?php

class Peanut {

	// User configurable items
	private $system = 'peanut';
	private $pages_folder = 'pages';
	private $layouts_folder = 'layouts';
	private $layout = 'template.html';

	// Do not edit below this line
	private $pages = array();
	private $pages_content = array();
	private $request;

	public function __construct()
	{
		$this->load_spyc();
		$this->parse_files();
		$this->load_content();
		$this->parse_request();
		$this->output_content();
	}

	private function output_content()
	{
		$page = $this->pages_content[$this->request];
		$body = '';

		foreach($page AS $key => $value)
		{
			if (is_numeric($key))
			{
				$body .= '<p>'.$value.'</p>'."\n";
			}
		}

		if (isset($page['template']))
		{
			$template = file_get_contents(getcwd().'/peanut/layouts/'.$page['template']);
			$template = str_replace('{title}', $page['title'], $template);
			$template = str_replace('{body}', $body, $template);
			echo $template;
		}
		else
		{
			echo $this->pages_content[$this->request]['title'];
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
			$this->request = DIRECTORY_SEPARATOR.'index.txt';
		}
	}

	private function parse_files()
	{
		$pages = array();
		$path = $this->system.DIRECTORY_SEPARATOR.$this->pages_folder;

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
		$path = $this->system.DIRECTORY_SEPARATOR.$this->pages_folder;

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
		require_once $this->system.DIRECTORY_SEPARATOR.'spyc/spyc.php';
	}

	private function debug($data)
	{
		echo '<pre>';
		print_r($data);
		echo '</pre>';
	}
}

$peanut = new Peanut();
