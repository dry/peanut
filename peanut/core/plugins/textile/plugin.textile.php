<?php

class Plugin_textile {

	private $settings = array();
	private $plugin;

	public function __construct($settings = array())
	{
		$this->settings = $settings;

		if ( ! is_object($this->plugin))
		{
			require_once 'textile.php';
			$this->plugin = new Textile;
		}
	}

	public function parse($str)
	{
		return $this->plugin->textileThis($str);
	}
}
