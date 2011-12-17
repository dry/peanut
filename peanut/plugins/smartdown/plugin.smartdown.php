<?php if ( ! defined('DS')) exit('Direct script access not permitted.');

/**
 * Smarter Markdown, with PHP Markdown Extra and SmartyPants for extra spicy content goodness.
 *
 * @author          Stephen Lewis (http://github.com/experience)
 * @package         SmartDown
 * @version         0.1.0
 */

require_once dirname(__FILE__) .DS .'markdown' .DS .'markdown.php';
require_once dirname(__FILE__) .DS .'smartypants' .DS .'smartypants.php';

class Plugin_smartdown {

    private $_settings;


    /* --------------------------------------------------------------
     * PUBLIC METHODS
     * ------------------------------------------------------------ */
    
    /**
     * Constructor.
     *
     * @access  public
     * @param   array        $settings      Plugin settings. These don't appear to be implemented by Peanut at present.
     * @return  void
     */
    public function __construct($settings = array())
    {
        $this->_settings = $settings;
    }


    /**
     * Parses the supplied string using Markdown and SmartyPants.
     *
     * @access  public
     * @param   string        $data        The string to parse.
     * @return  string
     */
    public function parse($data)
    {
        /**
         * In the absence of plugin options, we make a couple of assumptions:
         *
         * 1. Peanut template tags should be encoded within code blocks.
         * 2. SmartyPants 'smart quotes' will use the default settings.
         */

        $default_smartquotes = '2';

        $data = Markdown($data);
        $data = preg_replace_callback(
            '|' .preg_quote('<code>') .'(.*?)' .preg_quote('</code>') .'|s',
            array($this, '_encode_code_samples'),
            $data
        );
      
        return SmartyPants($data, $default_smartquotes);
    }



    /* --------------------------------------------------------------
     * PRIVATE METHODS
     * ------------------------------------------------------------ */
    
    /**
     * Encodes the Peanut layout tag delimiters in code blocks. Called from
     * preg_replace_callback.
     *
     * @access  private
     * @param   array        $matches        The regular expression matches.
     * @return  string
     */
    private function _encode_code_samples(Array $matches)
    {
        /**
         * @todo : Handle custom left_delim and right_delim characters.
         */

        $parsed = str_replace(
            array('{', '}'),
            array('&#123;', '&#125;'),
            $matches[0]
        );

        return $parsed;
    }


}


/* End of file      : plugin.smartdown.php */
/* File location    : /peanut/plugins/plugin.smartdown.php */
