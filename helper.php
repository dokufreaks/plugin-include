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

    var $defaults  = array();
    var $sec_close = true;
    var $taghelper = null;
    var $includes  = array(); // deprecated - compatibility code for the blog plugin

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
        $this->defaults['linkonly']  = $this->getConf('linkonly');
        $this->defaults['inline']    = false;
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
                case 'linkonly':
                    $flags['linkonly'] = 1;
                    break;
                case 'nolinkonly':
                case 'include_content':
                    $flags['linkonly'] = 0;
                    break;
                case 'inline':
                    $flags['inline'] = 1;
                    break;
            }
        }
        // the include_content URL parameter overrides flags
        if (isset($_REQUEST['include_content']))
            $flags['linkonly'] = 0;
        return $flags;
    }

    /**
     * Returns the converted instructions of a give page/section
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author Michael Hamann <michael@content-space.de>
     */
    function _get_instructions($page, $sect, $mode, $lvl, $flags, $root_id = null) {
        $key = ($sect) ? $page . '#' . $sect : $page;
        $this->includes[$key] = true; // legacy code for keeping compatibility with other plugins

        // keep compatibility with other plugins that don't know the $root_id parameter
        if (is_null($root_id)) {
            global $ID;
            $root_id = $ID;
        }

        if ($flags['linkonly']) {
            $ins = array(
                array('p_open', array()),
                array('internallink', array(':'.$key)),
                array('p_close', array()),
            );
        } else {
            if (page_exists($page)) {
                $ins = p_cached_instructions(wikiFN($page));
            } else {
                $ins = array();
            }

            $this->_convert_instructions($ins, $lvl, $page, $sect, $flags, $root_id);
        }
        return $ins;
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
    function _convert_instructions(&$ins, $lvl, $page, $sect, $flags, $root_id) {
        global $conf;

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
                    if ($flags['inline'])
                        unset($ins[$i]);
                    else
                        $conv_idx[] = $i;
                    break;
                case 'section_close':
                    if ($flags['inline'])
                        unset($ins[$i]);
                case 'internallink':
                case 'internalmedia':
                    // make sure parameters aren't touched
                    $link_params = '';
                    $link_id = $ins[$i][1][0];
                    $link_parts = explode('?', $link_id, 2);
                    if (count($link_parts) === 2) {
                        $link_id = $link_parts[0];
                        $link_params = $link_parts[1];
                    }
                    // resolve the id without cleaning it
                    $link_id = resolve_id($ns, $link_id, false);
                    // this id is internal (i.e. absolute) now, add ':' to make resolve_id work again
                    if ($link_id{0} != ':') $link_id = ':'.$link_id;
                    // restore parameters
                    $ins[$i][1][0] = ($link_params != '') ? $link_id.'?'.$link_params : $link_id;
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
                        // adapt indentation level of nested includes
                        case 'include_include':
                            if (!$flags['inline'] && $flags['indent'])
                                $ins[$i][1][1][4] += $lvl;
                            break;
                    }
                    break;
                default:
                    break;
            }
        }

        // calculate difference between header/section level and include level
        $diff = 0;
        if (!isset($lvl_max)) $lvl_max = 0; // if no level found in target, set to 0
        $diff = $lvl - $lvl_max + 1;
        if ($no_header) $diff -= 1;  // push up one level if "noheader"

        // convert headers and set footer/permalink
        $hdr_deleted      = false;
        $has_permalink    = false;
        $footer_lvl       = false;
        $contains_secedit = false;
        $section_close_at = false;
        foreach($conv_idx as $idx) {
            if($ins[$idx][0] == 'header') {
                if ($section_close_at === false) {
                    // store the index of the first heading (the begin of the first section)
                    $section_close_at = $idx;
                }

                if($no_header && !$hdr_deleted) {
                    unset ($ins[$idx]);
                    $hdr_deleted = true;
                    continue;
                }

                if($flags['indent']) {
                    $lvl_new = (($ins[$idx][1][1] + $diff) > 5) ? 5 : ($ins[$idx][1][1] + $diff);
                    $ins[$idx][1][1] = $lvl_new;
                }

                if($ins[$idx][1][1] <= $conf['maxseclevel'])
                    $contains_secedit = true;

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

        // close last open section of the included page if there is any
        if ($contains_secedit) {
            array_push($ins, array('plugin', array('include_close_last_secedit', array())));
        }

        // add edit button
        if($flags['editbtn']) {
            $perm = auth_quickaclcheck($page);
            $can_edit = page_exists($page) ? $perm >= AUTH_EDIT : $perm >= AUTH_CREATE;
            if ($can_edit)
                $this->_editbtn($ins, $page, $sect, $sect_title, ($flags['redirect'] ? $root_id : false));
        }

        // add footer
        if($flags['footer']) {
            $ins[] = $this->_footer($page, $sect, $sect_title, $flags, $footer_lvl, $root_id);
        }

        // wrap content at the beginning of the include that is not in a section in a section
        if ($lvl > 0 && $section_close_at !== 0 && $flags['indent'] && !$flags['inline']) {
            if ($section_close_at === false) {
                $ins[] = array('section_close', array());
                array_unshift($ins, array('section_open', array($lvl)));
            } else {
                $section_close_idx = array_search($section_close_at, array_keys($ins));
                if ($section_close_idx > 0) {
                    $before_ins = array_slice($ins, 0, $section_close_idx);
                    $after_ins = array_slice($ins, $section_close_idx);
                    $ins = array_merge($before_ins, array(array('section_close', array())), $after_ins);
                    array_unshift($ins, array('section_open', array($lvl)));
                }
            }
        }

        // add instructions entry wrapper
        array_unshift($ins, array('plugin', array('include_wrap', array('open', $page, $flags['redirect']))));
        array_push($ins, array('plugin', array('include_wrap', array('close'))));

        // close previous section if any and re-open after inclusion
        if($lvl != 0 && $this->sec_close && !$flags['inline']) {
            array_unshift($ins, array('section_close', array()));
            $ins[] = array('section_open', array($lvl));
        }
    }

    /**
     * Appends instruction item for the include plugin footer
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _footer($page, $sect, $sect_title, $flags, $footer_lvl, $root_id) {
        $footer = array();
        $footer[0] = 'plugin';
        $footer[1] = array('include_footer', array($page, $sect, $sect_title, $flags, $root_id, $footer_lvl));
        return $footer;
    }

    /**
     * Appends instruction item for an edit button
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _editbtn(&$ins, $page, $sect, $sect_title, $root_id) {
        $editbtn = array();
        $editbtn[0] = 'plugin';
        $editbtn[1] = array('include_editbtn', array($page, $sect, $sect_title, $root_id));
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

        $check = array(); // used for sectionID() in order to get the same ids as the xhtml renderer

        for($i=0; $i<$num; $i++) {
            if ($ins[$i][0] == 'header') { 

                // found the right header 
                if (sectionID($ins[$i][1][0], $check) == $sect) {
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
                $ins[] = array('internallink', array($page, $this->getLang('readmore')));
                $ins[] = array('p_close', array());
                $ins[] = array('section_close', array());
                return;
            }
        }
    }

    /**
     * Gives a list of pages for a given include statement
     *
     * @author Michael Hamann <michael@content-space.de>
     */
    function _get_included_pages($mode, $page, $sect, $parent_id) {
        global $conf;
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
            break;
        case 'tagtopic':
            if (!$this->taghelper)
                $this->taghelper =& plugin_load('helper', 'tag');
            if(!$this->taghelper) {
                msg('You have to install the tag plugin to use this functionality!', -1);
                return array();
            }
            $tag   = $page;
            $sect  = '';
            $pagearrays = $this->taghelper->getTopic('', null, $tag);
            foreach ($pagearrays as $pagearray) {
                $pages[] = $pagearray['id'];
            }
            break;
        default:
            $page = $this->_apply_macro($page);
            resolve_pageid(getNS($parent_id), $page, $exists); // resolve shortcuts and clean ID
            if (auth_quickaclcheck($page) >= AUTH_READ)
                $pages[] = $page;
        }

        sort($pages);

        $result = array();
        foreach ($pages as $page) {
            $perm = auth_quickaclcheck($page);
            $exists = page_exists($page);
            $result[] = array('id' => $page, 'exists' => $exists, 'can_edit' => ($perm >= AUTH_EDIT), 'parent_id' => $parent_id);
        }
        return $result;
    }

    /**
     * This function generates the list of all included pages from a list of metadata
     * instructions.
     */
    function _get_included_pages_from_meta_instructions($instructions) {
        $pages = array();
        foreach ($instructions as $instruction) {
            extract($instruction);
            $pages = array_merge($pages, $this->_get_included_pages($mode, $page, $sect, $parent_id));
        }
        return $pages;
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
// vim:ts=4:sw=4:et:
