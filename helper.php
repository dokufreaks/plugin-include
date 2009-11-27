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

require_once(DOKU_INC.'inc/search.php');

class helper_plugin_include extends DokuWiki_Plugin { // DokuWiki_Helper_Plugin

    var $includes     = array();
    var $hasparts     = array();
    var $toplevel_id  = NULL;
    var $toplevel     = 0;
    var $defaults     = array();
    var $include_key     = '';

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
        $this->defaults['linkbacks'] = $this->getConf('showlinkbacks');
        $this->defaults['tags']      = $this->getConf('showtags');
        $this->defaults['link']      = $this->getConf('showlink');
        $this->defaults['permalink'] = $this->getConf('showpermalink');
        $this->defaults['indent']    = $this->getConf('doindent');
    }

    function getInfo() {
        return array(
                'author' => 'Gina Häußge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
                'name'   => 'Include Plugin (helper class)',
                'desc'   => 'Functions to include another page in a wiki page',
                'url'    => 'http://dokuwiki.org/plugin:include',
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
                case 'link':
                    $flags['link'] = 1;
                    break;
                case 'nolink':
                    $flags['link'] = 0;
                    break;
                case 'user':
                    $flags['user'] = 1;
                    break;
                case 'nouser':
                    $flags['user'] = 0;
                    break;
                case 'comments':
                    $flags['comments'] = 1;
                    break;
                case 'nocomments':
                    $flags['comments'] = 0;
                    break;
                case 'linkbacks':
                    $flags['linkbacks'] = 1;
                    break;
                case 'nolinkbacks':
                    $flags['linkbacks'] = 0;
                    break;
                case 'tags':
                    $flags['tags'] = 1;
                    break;
                case 'notags':
                    $flags['tags'] = 0;
                    break;
                case 'date':
                    $flags['date'] = 1;
                    break;
                case 'nodate':
                    $flags['date'] = 0;
                    break;
                case 'indent':
                    $flags['indent'] = 1;
                    break;
                case 'noindent':
                    $flags['indent'] = 0;
                    break;
            }
        }
        return $flags;
    }

    /**
     * Parses the instructions list of the page which contains the includes
     * 
     * @author Michael Klier <chi@chimeric.de>
     */
    function parse_instructions($id, &$ins) {
        global $conf;
        global $INFO;

        $num = count($ins);

        $lvl      = false;
        $prev_lvl = false;
        $mode     = '';
        $page     = '';
        $flags    = array();
        $range    = false;
        $scope    = $id;

        for($i=0; $i<$num; $i++) {
            // set current level
            if($ins[$i][0] == 'section_open') {
                $lvl = $ins[$i][1][0];
                if($i > $range) $prev_lvl = $lvl;
            }

            if($ins[$i][0] == 'plugin' && $ins[$i][1][0] == 'include_include' ) {
                // found no previous section set lvl to 0
                if(!$lvl) $lvl = 0; 

                $mode  = $ins[$i][1][1][0];

                if($mode == 'namespace') {
                    $ns    = str_replace(':', '/', cleanID($ins[$i][1][1][1]));
                    $sect  = '';
                    $flags = $ins[$i][1][1][3];

                    $pages = array();
                    search($pages, $conf['datadir'], 'search_list', '', $ns);
                    sort($pages);

                    if(!empty($pages)) {
                        $ins_inc = array();
                        foreach($pages as $page) {
                            $perm = auth_quickaclcheck($page['id']);
                            array_push($this->hasparts, $page['id']);
                            if($perm < AUTH_READ) continue;
                            $ins_tmp[0]       = 'plugin';
                            $ins_tmp[1][0]    = 'include_include';
                            $ins_tmp[1][1][0] = 'page';
                            $ins_tmp[1][1][1] = $page['id'];
                            $ins_tmp[1][1][2] = '';
                            $ins_tmp[1][1][3] = $flags;
                            $ins_inc = array_merge($ins_inc, array($ins_tmp));
                        }
                        $ins_start = array_slice($ins, 0, $i+1);
                        $ins_end   = array_slice($ins, $i+1);
                        $ins       = array_merge($ins_start, $ins_inc, $ins_end);
                    }
                    unset($ins[$i]);
                    $i--;
                }

                if($mode == 'page' || $mode == 'section') {
                    $page  = $ins[$i][1][1][1];
                    $perm = auth_quickaclcheck($page);

                    array_push($this->hasparts, $page);
                    if($perm < AUTH_READ) continue;

                    $sect  = $ins[$i][1][1][2];
                    $flags = $ins[$i][1][1][3];

                    $page = $this->_apply_macro($page);
                    resolve_pageid(getNS($scope), $page, $exists); // resolve shortcuts
                    $ins[$i][1][1][4] = $scope;
                    $scope = $page;
                    $flags = $this->get_flags($flags);

                    if(!page_exists($page)) {
                        if($flags['footer']) {
                            $ins[$i] = $this->_footer($page, $sect, '', $flags, 0);
                        } else {
                            unset($ins[$i]);
                        }
                    } else {
                        $ins_inc = $this->_get_instructions($page, $sect, $mode, $lvl, $flags);
                        if(!empty($ins_inc)) {
                            // combine instructions and reset counter
                            $ins_start = array_slice($ins, 0, $i+1);
                            $ins_end   = array_slice($ins, $i+1);
                            $range = $i + count($ins_inc);
                            $ins = array_merge($ins_start, $ins_inc, $ins_end);
                            $num = count($ins);
                        }
                    }
                }
            }

            // check if we left the range of possible sub includes and reset lvl and scope to toplevel_id
            if($range && ($i >= $range)) {
                $lvl = ($prev_lvl == 0) ? 1 : $prev_lvl;
                $prev_lvl = false;
                $range    = false;
                // reset scope to toplevel_id
                $scope = $this->toplevel_id;
            }
        }

        if(!empty($INFO['userinfo'])) {
            $include_key = $INFO['userinfo']['name'] . '|' . implode('|', $INFO['userinfo']['grps']);
        } else {
            $include_key = '@ALL';
        }

        $meta = array();
        $meta = p_get_metadata($id, 'plugin_include');
        $meta['pages'] = array_unique($this->hasparts);
        $meta['keys'][$include_key] = true;
        p_set_metadata($id, array('plugin_include' => $meta), true, true);
    }

    /**
     * Returns the converted instructions of a give page/section
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _get_instructions($page, $sect, $mode, $lvl, $flags) {
        global $ID;
        
        $key = ($sect) ? $page . '#' . $sect : $page;

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
     * The funcion iterates over the given list of instructions and generates
     * an index of header and section indicies. It also removes document
     * start/end instructions, converts links, and removes unwanted
     * instructions like tags, comments, linkbacks.
     *
     * Later all header/section levels are convertet to match the current
     * inclusion level.
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _convert_instructions(&$ins, $lvl, $page, $sect, $flags) {

        // filter instructions if needed
        if(!empty($sect)) {
            $this->_get_section($ins, $sect);   // section required
        }

        if($flags['firstsec']) {
            $this->_get_firstsec($ins, $page);  // only first section 
        }
        
        $ns  = getNS($page);
        $num = count($ins);

        $conv_idx = array(); // conversion index
        $lvl_max  = false;   // max level
        $first_header = -1;
        $no_header  = false;
        $sect_title = false;

        for($i=0; $i<$num; $i++) {
            switch($ins[$i][0]) {
                case 'document_start':
                case 'document_end':
                case 'section_edit':
                    unset($ins[$i]);
                    break;
                case 'header':
                    // get section title of first section
                    if($sect && !$sect_title) {
                        $sect_title = $ins[$i][1][0];
                    }
                    // check if we need to skip the first header
                    if((!$no_header) && $flags['noheader']) {
                        $no_header = true;
                    }

                    $conv_idx[] = $i;
                    // get index of first header
                    if($first_header == -1) $first_header = $i;
                    // get max level of this instructions set
                    if(!$lvl_max || ($ins[$i][1][1] < $lvl_max)) {
                        $lvl_max = $ins[$i][1][1];
                    }
                    break;
                case 'section_open':
                    $conv_idx[] = $i;
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
                    // FIXME skip other plugins?
                    switch($ins[$i][1][0]) {
                        case 'tag_tag':                 // skip tags
                        case 'discussion_comments':     // skip comments
                        case 'linkback':                // skip linkbacks
                        case 'data_entry':              // skip data plugin
                        case 'meta':                    // skip meta plugin
                            unset($ins[$i]);
                            break;
                    }
                    break;
                default:
                    break;
            }
        }

        // calculate difference between header/section level and include level
        $diff = 0;
        if (!$lvl_max) $lvl_max = 0; // if no level found in target, set to 0
        $diff = $lvl - $lvl_max + 1;
        if ($no_header) $diff -= 1;  // push up one level if "noheader"

        // convert headers and set footer/permalink
        $hdr_deleted   = false;
        $has_permalink = false;
        $footer_lvl    = false;
        foreach($conv_idx as $idx) {
            if($ins[$idx][0] == 'header') {
                if($no_header && !$hdr_deleted) {
                    unset ($ins[$idx]);
                    $hdr_deleted = true;
                    continue;
                }

                if($flags['indent']) {
                    $lvl_new = (($ins[$idx][1][1] + $diff) > 5) ? 5 : ($ins[$idx][1][1] + $diff);
                    $ins[$idx][1][1] = $lvl_new;
                }

                // set permalink
                if($flags['link'] && !$has_permalink && ($idx == $first_header)) {
                    $this->_permalink($ins[$idx], $page, $sect, $flags);
                    $has_permalink = true;
                }

                // set footer level
                if(!$footer_lvl && ($idx == $first_header) && !$no_header) {
                    if($flags['indent']) {
                        $footer_lvl = $lvl_new;
                    } else {
                        $footer_lvl = $lvl_max;
                    }
                }
            } else {
                // it's a section
                if($flags['indent']) {
                    $lvl_new = (($ins[$idx][1][0] + $diff) > 5) ? 5 : ($ins[$idx][1][0] + $diff);
                    $ins[$idx][1][0] = $lvl_new;
                }

                // check if noheader is used and set the footer level to the first section
                if($no_header && !$footer_lvl) {
                    if($flags['indent']) {
                        $footer_lvl = $lvl_new;
                    } else {
                        $footer_lvl = $lvl_max;
                    }
                } 
            }
        }

        // add edit button
        if($flags['editbtn'] && (auth_quickaclcheck($page) >= AUTH_EDIT)) {
            $this->_editbtn($ins, $page, $sect, $sect_title);
        }

        // add footer
        if($flags['footer']) {
            $ins[] = $this->_footer($page, $sect, $sect_title, $flags, $footer_lvl);
        }

        // add instructions entry divs
        array_unshift($ins, array('plugin', array('include_div', array('open', $page))));
        array_push($ins, array('plugin', array('include_div', array('close'))));

        // close previous section if any and re-open after inclusion
        if($lvl != 0) {
            array_unshift($ins, array('section_close'));
            $ins[] = array('section_open', array($lvl));
        }
    }

    /**
     * Appends instruction item for the include plugin footer
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _footer($page, $sect, $sect_title, $flags, $footer_lvl) {
        $footer = array();
        $footer[0] = 'plugin';
        $footer[1] = array('include_footer', array($page, $sect, $sect_title, $flags, $this->toplevel_id, $footer_lvl));
        return $footer;
    }

    /**
     * Appends instruction item for an edit button
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _editbtn(&$ins, $page, $sect, $sect_title) {
        $editbtn = array();
        $editbtn[0] = 'plugin';
        $editbtn[1] = array('include_editbtn', array($page, $sect, $sect_title, $this->toplevel_id));
        $ins[] = $editbtn;
    }

    /**
     * Convert instruction item for a permalink header
     * 
     * @author Michael Klier <chi@chimeric.de>
     */
    function _permalink(&$ins, $page, $sect, $flags) {
        $ins[0] = 'plugin';
        $ins[1] = array('include_header', array($ins[1][0], $ins[1][1], $page, $sect, $flags));
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
        $end    = false;

        for($i=0; $i<$num; $i++) {
            if ($ins[$i][0] == 'header') { 

                // found the right header 
                if (cleanID($ins[$i][1][0]) == $sect) { 
                    $offset = $i;
                    $lvl    = $ins[$i][1][1]; 
                } elseif ($offset && $lvl && ($ins[$i][1][1] <= $lvl)) {
                    $end = $i - $offset;
                    break;
                }
            }
        }
        $offset = $offset ? $offset : 0;
        $end = $end ? $end : ($num - 1);
        if(is_array($ins)) {
            $ins = array_slice($ins, $offset, $end);
        }
    } 

    /**
     * Only display the first section of a page and a readmore link
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _get_firstsec(&$ins, $page) {
        $num = count($ins);
        $first_sect = false;
        for($i=0; $i<$num; $i++) {
            if($ins[$i][0] == 'section_close') {
                $first_sect = $i;
            }
            if(($first_sect) && ($ins[$i][0] == 'section_open')) {
                $ins = array_slice($ins, 0, $first_sect);
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
        $group    = $INFO['userinfo']['grps'][0];

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
//vim:ts=4:sw=4:et:enc=utf-8:
