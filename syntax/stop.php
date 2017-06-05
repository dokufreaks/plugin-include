<?php 
/** 
 * Include Plugin: displays a wiki page within another 
 * Usage: 
 * {{includestop}} stop including the current page at this point
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html) 
 * @author     LarsDW223
 */ 
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/'); 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/'); 
require_once(DOKU_PLUGIN.'syntax.php'); 
  
/** 
 * All DokuWiki plugins to extend the parser/rendering mechanism 
 * need to inherit from this class 
 */ 
class syntax_plugin_include_stop extends DokuWiki_Syntax_Plugin { 

    /**
     * Get syntax plugin type.
     *
     * @return string The plugin type.
     */
    function getType() { return 'formatting'; }

    /**
     * Get sort order of syntax plugin.
     *
     * @return int The sort order.
     */
    function getSort() { return 303; }

    /**
     * Connect patterns/modes
     *
     * @param $mode mixed The current mode
     */
    function connectTo($mode) {  
        $this->Lexer->addSpecialPattern("{{includestop}}", $mode, 'plugin_include_stop'); 
    }

    /**
     * Handle syntax matches
     *
     * @param string       $match   The current match
     * @param int          $state   The match state
     * @param int          $pos     The position of the match
     * @param Doku_Handler $handler The hanlder object
     * @return array The instructions of the plugin
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        return true;
    }

    /**
     * Renders the include stop - dummy.
     * 'includestop' is handled in helper_plugin_include::_shorten_instructions()
     */
    function render($format, Doku_Renderer $renderer, $data) {
        return true;
    }
}
// vim:ts=4:sw=4:et:
