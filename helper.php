<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 * @author     Christopher Smith <chris@jalakai.co.uk>
 * @author     Gina Häußge, Michael Klier <dokuwiki@chimeric.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

class helper_plugin_include extends DokuWiki_Plugin { // DokuWiki_Helper_Plugin

    var $includes     = array();
    var $toplevel_id  = NULL;
    var $defaults     = array();

    /**
     * Constructor loads default config settings once
     */
    function helper_plugin_include() {
        $this->defaults['firstsec']  = $this->getConf('firstseconly');
        $this->defaults['editbtn']   = $this->getConf('showeditbtn');
        $this->defaults['taglogos']  = $this->getConf('showtaglogos');
        $this->defaults['footer']    = $this->getConf('showfooter');
        $this->defaults['redirect']  = $this->getConf('doredirect');
        $this->defaults['date']      = $this->getConf('showdate');
        $this->defaults['user']      = $this->getConf('showuser');
        $this->defaults['comments']  = $this->getConf('showcomments');
        $this->defaults['linkbacks'] = $this->getConf('linkbacks');
        $this->defaults['tags']      = $this->getConf('tags');
        $this->defaults['link']      = $this->getConf('showlink');
    }

    function getInfo() {
        return array(
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
                'name'   => 'Include Plugin (helper class)',
                'desc'   => 'Functions to include another page in a wiki page',
                'url'    => 'http://wiki.splitbrain.org/plugin:include',
                );
    }

    /**
     * Available methods for other plugins
     */
    function getMethods() {
        $result = array();
        $result[] = array(
                'name'   => 'get_flags',
                'desc'   => 'overrides standard values for showfooter and firstseconly settings',
                'params' => array('flags' => 'array'),
                );
        return $result;
    }

    /**
     * Overrides standard values for showfooter and firstseconly settings
     */
    function get_flags($setflags) {
        // load defaults
        $flags = array();
        $flags = $this->defaults;
        foreach ($setflags as $flag) {
            switch ($flag) {
                case 'footer':
                    $flags['footer'] = 1;
                    break;
                case 'nofooter':
                    $flags['footer'] = 0;
                    break;
                case 'firstseconly':
                case 'firstsectiononly':
                    $flags['firstsec'] = 1;
                    break;
                case 'fullpage':
                    $flags['firstsec'] = 0;
                    break;
                case 'noheader':
                    $flags['noheader'] = 1;
                    break;
                case 'editbtn':
                case 'editbutton':
                    $flags['editbtn'] = 1;
                    break;
                case 'noeditbtn':
                case 'noeditbutton':
                    $flags['editbtn'] = 0;
                    break;
                case 'permalink':
                    $flags['permalink'] = 1;
                    break;
                case 'nopermalink':
                    $flags['permalink'] = 0;
                    break;
                case 'redirect':
                    $flags['redirect'] = 1;
                    break;
                case 'noredirect':
                    $flags['redirect'] = 0;
                    break;
            }
        }
        return $flags;
    }

    /**
     * Parses the instructions list
     * 
     * called by the action plugin component, this function is called
     * recursively for the p_cached_instructions call (when the instructions
     * need to be updated)
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function parse_instructions($id, &$ins) {
        $num = count($ins);

        $lvl   = 0;
        $mode  = '';
        $page  = '';
        $flags = array();

        for($i=0; $i<$num; $i++) {
            // set current level
            if($ins[$i][0] == 'section_open') {
                $lvl = $ins[$i][1][0];
            }
            if($ins[$i][0] == 'plugin' && $ins[$i][1][0] == 'include_include' ) {
                $mode  = $ins[$i][1][1][0];
                $page  = $ins[$i][1][1][1];
                $sect  = $ins[$i][1][1][2];
                $flags = $ins[$i][1][1][3];
                
                $page = $this->_apply_macro($page);
                resolve_pageid(getNS($id), $page, $exists); // resolve shortcuts
                $flags   = $this->get_flags($flags);
                $ins_inc = $this->_get_instructions($page, $sect, $mode, $lvl, $flags);

                if(!empty($ins_inc)) {
                    // combine instructions and reset counter
                    $ins_start = array_slice($ins, 0, $i+1);
                    $ins_end   = array_slice($ins, $i+1);
                    $ins = array_merge($ins_start, $ins_inc, $ins_end);
                    $num = count($ins);
                }
            }
        }
    }

    /**
     * Returns the converted instructions of a give page/section
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _get_instructions($page, $sect, $mode, $lvl, $flags) {
        global $ID;

        if($ID == $page || !page_exists($page) || (page_exists($page) && auth_quickaclcheck($page) < AUTH_READ)) return array();
        $key = (!$sect) ? $page . '#' . $sect : $page;

        // prevent recursion
        if(!$this->includes[$key]) {
            $ins = p_cached_instructions(wikiFN($page));
            $this->includes[$key] = true;
            $this->_convert_instructions($ins, $lvl, $page, $sect, $flags);
            return $ins;
        }
    }

    /**
     * Converts instructions of the included page
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _convert_instructions(&$ins, $lvl, $page, $sect, $flags) {

        if(!empty($sect)) {
            $this->_get_section($ins, $sect);   // section required
        } elseif($flags['firstsec']) {
            $this->_get_firstsec($ins, $page);  // only first section 
        }
        
        $has_permalink = false;
        $footer_lvl    = false;
        $ns  = getNS($page);
        $num = count($ins);
        $top_lvl = $lvl; // save toplevel for later use
        for($i=0; $i<$num; $i++) {
            switch($ins[$i][0]) {
                case 'document_start':
                case 'document_end':
                case 'section_edit':
                    unset($ins[$i]);
                    break;
                case 'header':
                    $ins[$i][1][1] = $this->_get_level($lvl, $ins[$i][1][1]);
                    $lvl = $ins[$i][1][1];
                    if(!$footer_lvl) $footer_lvl = $lvl;
                    if($sect && !$sect_title) {
                        $sect_title = $ins[$i][1][0];
                    }
                    if($flags['link'] && !$has_permalink) {
                        $this->_permalink($ins[$i], $page, $sect);
                        $has_permalink = true;
                    }
                    break;
                case 'section_open':
                    $ins[$i][1][0] = $this->_get_level($lvl, $ins[$i][1][0]);
                    $lvl = $ins[$i][1][0];
                    break;
                case 'internallink':
                case 'internalmedia':
                    if($ins[$i][1][0]{0} == '.') {
                        if($ins[$i][1][0]{1} == '.') {
                            $ins[$i][1][0] = getNS($ns) . ':' . substr($ins[$i][1][0], 2); // parent namespace
                        } else {
                            $ins[$i][1][0] = $ns . ':' . substr($ins[$i][1][0], 1); // current namespace
                        }
                    } elseif (strpos($ins[$i][1][0], ':') === false) {
                        $ins[$i][1][0] = $ns . ':' . $ins[$i][1][0]; // relative links
                    }
                    break;
                case 'plugin':
                    // FIXME skip others?
                    if($ins[$i][1][0] == 'tag_tag') unset($ins[$i]);                // skip tags
                    if($ins[$i][1][0] == 'discussion_comments') unset($ins[$i]);    // skip comments
                    break;
                default:
                    break;
            }
        }

        if($flags['footer']) $this->_footer($ins, $page, $sect, $sect_title, $flags, $footer_lvl);

        // close previous section if any and re-open after inclusion
        if($top_lvl != 0) {
            array_unshift($ins, array('section_close'));
            $ins[] = array('section_open', array($top_lvl));
        }
    }

    /**
     * Appends instruction item for the include plugin footer
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _footer(&$ins, $page, $sect, $sect_title, $flags, $footer_lvl) {
        $footer = array();
        $footer[0] = 'plugin';
        $footer[1] = array('include_footer', array($page, $sect, $sect_title, $flags, $this->toplevel_id, $footer_lvl));
        $ins[] = $footer;
    }

    /**
     * Return the correct level 
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _get_level($lvl, $curr_lvl) {
        if($curr_lvl == $lvl) {
            // current level equals inclusion level 
            // return current level increased by 1
            return (($curr_lvl + 1) <= 5) ? ($curr_lvl + 1) : 5;

        } elseif(($curr_lvl < $lvl) && (($lvl - $curr_lvl) <= 1)) {
            // if current level is small than inclusion level and difference 
            // between inclusion level and current level is less than 1
            // return current level increased by 1
            return (($curr_lvl + 1) <= 5) ? ($curr_lvl + 1) : 5;

        } elseif(($curr_lvl < $lvl) && (($lvl - $curr_lvl) > 1)) {
            // if current level is less than inclusion level and
            // difference between inclusion level and the curren level is
            // greater than 1 return inclusion level increased by 1
            return (($lvl + 1) <= 5) ? ($lvl + 1) : 5;
        }
    }

    /** 
     * Get a section including its subsections 
     *
     * @author Michael Klier <chi@chimeric.de>
     */ 
    function _get_section(&$ins, $sect) { 
        $num = count($ins);
        $offset = false;
        $lvl    = false;

        for($i=0; $i<$num; $i++) {
            if ($ins[$i][0] == 'header') { 

                // found the right header 
                if (cleanID($ins[$i][1][0]) == $sect) { 
                    $offset = $i;
                    $lvl    = $ins[$i][1][1]; 
                } elseif ($offset && $lvl && ($ins[$i][1][1] <= $lvl)) {
                    $ins = array_slice($ins, $offset, ($i - $offset));
                }
            }
        }
    } 

    /**
     * Only display the first section of a page and a readmore link
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _get_firstsec(&$ins, $page) {
        $num = count($ins);
        for($i=0; $i<$num; $i++) {
            if($ins[$i][0] == 'section_close') {
                $ins = array_slice($ins, 0, $i);
                $ins[] = array('p_open', array());
                $ins[] = array('internallink',array($page, $this->getLang('readmore')));
                $ins[] = array('p_close', array());
                $ins[] = array('section_close');
                return;
            }
        }
    }

    /**
     * Makes user or date dependent includes possible
     */
    function _apply_macro($id) {
        global $INFO;
        global $auth;
        
        // if we don't have an auth object, do nothing
        if (!$auth) return $id;

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

    /**
     * Create instruction item for a permalink header
     * 
     * @param   string  $text: Headline text
     * @param   integer $level: Headline level
     * @param   integer $pos: I wish I knew what this is for... (me too ;-))
     * 
     * @author Gina Haeussge <osd@foosel.net> 
     * @author Michael Klier <chi@chimeric.de>
     */
    function _permalink(&$ins, $page, $sect) {
        $ins[0] = 'plugin';
        $ins[1] = array('include_header', array($ins[1][0], $ins[1][1], $page, $sect));
    }
  
    /**
     * Optionally display logo for the first tag found in the included page
     *
     * FIXME erm what was this for again?
     */
    function _showTagLogos() {
        if ((!$this->getConf('showtaglogos'))
                || (plugin_isdisabled('tag'))
                || (!$taghelper =& plugin_load('helper', 'tag')))
            return '';

        $subject = p_get_metadata($this->page['id'], 'subject');
        if (is_array($subject)) $tag = $subject[0];
        else list($tag, $rest) = explode(' ', $subject, 2);
        $title = str_replace('_', ' ', noNS($tag));
        resolve_pageid($taghelper->namespace, $tag, $exists); // resolve shortcuts

        $logosrc = mediaFN($logoID);
        $types = array('.png', '.jpg', '.gif'); // auto-detect filetype
        foreach ($types as $type) {
            if (!@file_exists($logosrc.$type)) continue;
            $logoID   = $tag.$type;
            $logosrc .= $type;
            list($w, $h, $t, $a) = getimagesize($logosrc);
            return ' style="min-height: '.$h.'px">'.
                '<img class="mediaright" src="'.ml($logoID).'" alt="'.$title.'"/';
        }
        return '';
    }
}
//vim:ts=4:sw=4:et:enc=utf-8:
