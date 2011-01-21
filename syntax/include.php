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

    function getType() { return 'substition'; }
    function getSort() { return 303; }
    function getPType() { return 'block'; }

    function connectTo($mode) {  
        $this->Lexer->addSpecialPattern("{{page>.+?}}", $mode, 'plugin_include_include');  
        $this->Lexer->addSpecialPattern("{{section>.+?}}", $mode, 'plugin_include_include'); 
        $this->Lexer->addSpecialPattern("{{namespace>.+?}}", $mode, 'plugin_include_include'); 
        $this->Lexer->addSpecialPattern("{{tagtopic>.+?}}", $mode, 'plugin_include_include'); 
    } 

    function handle($match, $state, $pos, &$handler) {

        $match = substr($match, 2, -2); // strip markup
        list($match, $flags) = explode('&', $match, 2);

        // break the pattern up into its parts 
        list($mode, $page, $sect) = preg_split('/>|#/u', $match, 3); 
        $check = null;
        if (isset($sect)) $sect = sectionID($sect, $check);
        return array($mode, $page, $sect, explode('&', $flags));
    }

    /**
     * Renders the included page(s)
     *
     * @author Michael Hamann <michael@content-space.de>
     */
    function render($format, &$renderer, $data) {
        global $ID, $conf;

        // static stack that records all ancestors of the child pages
        static $page_stack = array();

        // when there is no id just assume the global $ID is the current id
        if (empty($page_stack)) $page_stack[] = $ID;

        $parent_id = $page_stack[count($page_stack)-1];
        $root_id = $page_stack[0];

        list($mode, $page, $sect, $flags, $level) = $data;

        if (!$this->helper)
            $this->helper =& plugin_load('helper', 'include');
        $flags = $this->helper->get_flags($flags);

        $pages = $this->helper->_get_included_pages($mode, $page, $sect, $parent_id);

        if ($format == 'metadata') {

            // remove old persistent metadata of previous versions of the include plugin
            if (isset($renderer->persistent['plugin_include'])) {
                unset($renderer->persistent['plugin_include']);
                unset($renderer->meta['plugin_include']);
            }

            $renderer->meta['plugin_include']['instructions'][] = compact('mode', 'page', 'sect', 'parent_id');
            if (!isset($renderer->meta['plugin_include']['pages']))
               $renderer->meta['plugin_include']['pages'] = array(); // add an array for array_merge
            $renderer->meta['plugin_include']['pages'] = array_merge($renderer->meta['plugin_include']['pages'], $pages);
            $renderer->meta['plugin_include']['include_content'] = isset($_REQUEST['include_content']);
        }

        foreach ($pages as $page) {
            extract($page);

            if (in_array($id, $page_stack)) continue;
            array_push($page_stack, $id);

            // add references for backlink
            if ($format == 'metadata')
                $renderer->meta['relation']['references'][$id] = $exists;

            $instructions = $this->helper->_get_instructions($id, $sect, $mode, $level, $flags, $root_id);

            $renderer->nest($instructions);

            array_pop($page_stack);
        }

        // When all includes have been handled remove the current id
        // in order to allow the rendering of other pages
        if (count($page_stack) == 1) array_pop($page_stack);

        return true;
    }
}
// vim:ts=4:sw=4:et:
