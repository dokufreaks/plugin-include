<?php 
/** 
 * Include Plugin: displays a wiki page within another 
 * Usage: 
 * {{page>page}} for "page" in same namespace 
 * {{page>:page}} for "page" in top namespace 
 * {{page>namespace:page}} for "page" in namespace "namespace" 
 * {{page>.namespace:page}} for "page" in subnamespace "namespace" 
 * {{page>page#section}} for a section of "page" 
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html) 
 * @author     Esther Brunner <wikidesign@gmail.com>
 * @author     Christopher Smith <chris@jalakai.co.uk>
 * @author     Gina Häußge, Michael Klier <dokuwiki@chimeric.de>
 */ 
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/'); 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/'); 
require_once(DOKU_PLUGIN.'syntax.php'); 
  
/** 
 * All DokuWiki plugins to extend the parser/rendering mechanism 
 * need to inherit from this class 
 */ 
class syntax_plugin_include_include extends DokuWiki_Syntax_Plugin { 

    var $helper = null;

    function getInfo() { 
        return array( 
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner', 
                'email'  => 'dokuwiki@chimeric.de', 
                'date'   => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
                'name'   => 'Include Plugin', 
                'desc'   => 'Displays a wiki page (or a section thereof) within another', 
                'url'    => 'http://dokuwiki.org/plugin:include', 
                ); 
    } 

    function getType() { return 'substition'; }
    function getSort() { return 303; }
    function getPType() { return 'block'; }

    function connectTo($mode) {  
        $this->Lexer->addSpecialPattern("{{page>.+?}}", $mode, 'plugin_include_include');  
        $this->Lexer->addSpecialPattern("{{section>.+?}}", $mode, 'plugin_include_include'); 
        $this->Lexer->addSpecialPattern("{{namespace>.+?}}", $mode, 'plugin_include_include'); 
    } 

    function handle($match, $state, $pos, &$handler) {

        $match = substr($match, 2, -2); // strip markup
        list($match, $flags) = explode('&', $match, 2);

        // break the pattern up into its parts 
        list($mode, $page, $sect) = preg_split('/>|#/u', $match, 3); 
        return array($mode, $page, cleanID($sect), explode('&', $flags)); 
    }

    function render($format, &$renderer, $data) {
        return false;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
