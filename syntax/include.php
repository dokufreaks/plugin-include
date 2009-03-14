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
                'url'    => 'http://wiki.splitbrain.org/plugin:include', 
                ); 
    } 

    function getType() { return 'substition'; }
    function getSort() { return 303; }
    function getPType() { return 'block'; }

    function connectTo($mode) {  
        $this->Lexer->addSpecialPattern("{{page>.+?}}", $mode, 'plugin_include_include');  
        $this->Lexer->addSpecialPattern("{{section>.+?}}", $mode, 'plugin_include_include'); 
    } 

    function handle($match, $state, $pos, &$handler) {

        $match = substr($match, 2, -2); // strip markup
        list($match, $flags) = explode('&', $match, 2);

        // break the pattern up into its parts 
        list($include, $id, $section) = preg_split('/>|#/u', $match, 3); 
        return array($include, $id, cleanID($section), explode('&', $flags)); 
    }

    function render($format, &$renderer, $data) {
        global $ID;

        list($type, $raw_id, $section, $flags, $lvl, $toc) = $data; 

        $id = $this->_applyMacro($raw_id);
        $nocache = ($id != $raw_id);

        resolve_pageid(getNS($ID), $id, $exists); // resolve shortcuts

        if ($nocache) $renderer->info['cache'] = false;                 // prevent caching
        if (AUTH_READ > auth_quickaclcheck($id)) return true;           // check for permission 

        $this->helper =& plugin_load('helper', 'include');

        $this->helper->setMode($type);
        $this->helper->setFlags($flags);

        if (!$this->helper->setPage(compact('type','id','section','exists'))) {
            return false;
        }

        // handle render formats
        switch($format) {
            case 'xhtml':

                // check for toc to prepend eventually
                if(!empty($toc)) {
                    foreach($toc as $data) {
                        $item = array();
                        $item['hid'] = $renderer->_headerToLink($data[0], 'true');
                        $item['title'] = $data[0];
                        $item['type'] = ul;
                        $item['level'] = $data[1];
                        array_push($this->helper->toc, $item);
                    }
                }


                $this->helper->setLevel($lvl);

                // close current section
                if ($lvl && ($type == 'section')) $renderer->doc .= '</div>';

                // include the page
                $this->helper->renderXHTML($renderer, $info);

                // propagate any cache prevention from included pages into this page
                if ($info['cache'] == false) $renderer->info['cache'] = false;

                // resume current section
                if ($lvl && ($type == 'section')) $renderer->doc .= '<div class="level'.$lvl.'">';

                return true; 
                break;

            case 'odt':

                // current section level
                $clevel = 0;
                preg_match_all('|<text:h text:style-name="Heading_20_\d" text:outline-level="(\d)">|i', $renderer->doc, $matches, PREG_SET_ORDER);
                $n = count($matches) - 1;
                if ($n > -1) $clevel = $matches[$n][1];
                $this->helper->setLevel($clevel);

                // include the page now
                $this->helpeer->renderODT($renderer);

                return true;
                break;

            case 'metadata':
                if (!$nocache) {
                    $renderer->meta['relation']['haspart'][$id] = @file_exists(wikiFN($id));
                }
                return true;
                break;
            default;
                return false;
                break;
        }
    }

    /**
     * Makes user or date dependent includes possible
     */
    function _applyMacro($id) {
        global $INFO, $auth;
        
        // if we don't have an auth object, do nothing
        if (!$auth)
        	return $id;

        $user     = $_SERVER['REMOTE_USER'];
        $userdata = $auth->getUserData($user);
        $group    = $userdata['grps'][0];

        $replace = array( 
                '@USER@'  => cleanID($user), 
                '@NAME@'  => cleanID($INFO['userinfo']['name']),
                '@GROUP@' => cleanID($group),
                '@YEAR@'  => date('Y'), 
                '@MONTH@' => date('m'), 
                '@DAY@'   => date('d'), 
                ); 
        return str_replace(array_keys($replace), array_values($replace), $id); 
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
