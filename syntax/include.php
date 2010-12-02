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
    var $taghelper = null;

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
        return array($mode, $page, cleanID($sect), explode('&', $flags)); 
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

        $pages = array();
        switch($mode) {
        case 'namespace':
            $ns    = str_replace(':', '/', cleanID($page));
            search($pagearrays, $conf['datadir'], 'search_list', '', $ns);
            if (is_array($pagearrays)) {
                foreach ($pagearrays as $pagearray) {
                    $pages[] = $pagearray['id'];
                }
            }
            sort($pages);
            break;
        case 'tagtopic':
            if (!$this->taghelper)
                $this->taghelper =& plugin_load('helper', 'tag');
            if(!$this->taghelper) {
                msg('You have to install the tag plugin to use this functionality!', -1);
                return;
            }
            $tag   = $page;
            $sect  = '';
            $pagearrays = $this->taghelper->getTopic('', null, $tag);
            foreach ($pagearrays as $pagearray) {
                $pages[] = $pagearray['id'];
            }
            break;
        default:
            $page = cleanID($this->helper->_apply_macro($page));
            resolve_pageid(getNS($parent_id), $page, $exists); // resolve shortcuts
            $pages[] = $page;
        }

        foreach ($pages as $page) {
            if (in_array($page, $page_stack)) continue;
            array_push($page_stack, $page);

            if($format == 'metadata') {
                $renderer->meta['plugin_include']['pages'][] = $page; // FIXME: record raw id so caching can check if the current replacements still match
                // recording all included pages might make sense for metadata cache updates, though really knowing all included pages
                // probably means we just need to always purge the metadata cache or move rendered metadata to other cache files that depend on user/groups
                // for namespace/tag includes we probably need to purge the cache everytime so they should be recorded in the metadata so we know when that's necessary
            }

            $perm = auth_quickaclcheck($page);

            if($perm < AUTH_READ) continue;

            if(!page_exists($page)) {
                if($flags['footer']) {
                    $renderer->nest(array($this->helper->_footer($page, $sect, '', $flags, $level, $root_id)));
                }
            } else {
                $instructions = $this->helper->_get_instructions($page, $sect, $mode, $level, $flags, $root_id);
                $renderer->nest($instructions);
            }

            array_pop($page_stack);
        }

        // When all includes have been handled remove the current id
        // in order to allow the rendering of other pages
        if (count($page_stack) == 1) array_pop($page_stack);

        return true;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
