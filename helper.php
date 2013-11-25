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
        $this->defaults['noheader']  = $this->getConf('noheader');
        $this->defaults['firstsec']  = $this->getConf('firstseconly');
        $this->defaults['editbtn']   = $this->getConf('showeditbtn');
        $this->defaults['taglogos']  = $this->getConf('showtaglogos');
        $this->defaults['footer']    = $this->getConf('showfooter');
        $this->defaults['redirect']  = $this->getConf('doredirect');
        $this->defaults['date']      = $this->getConf('showdate');
        $this->defaults['mdate']     = $this->getConf('showmdate');
        $this->defaults['user']      = $this->getConf('showuser');
        $this->defaults['comments']  = $this->getConf('showcomments');
        $this->defaults['linkbacks'] = $this->getConf('showlinkbacks');
        $this->defaults['tags']      = $this->getConf('showtags');
        $this->defaults['link']      = $this->getConf('showlink');
        $this->defaults['permalink'] = $this->getConf('showpermalink');
        $this->defaults['indent']    = $this->getConf('doindent');
        $this->defaults['linkonly']  = $this->getConf('linkonly');
        $this->defaults['title']     = $this->getConf('title');
        $this->defaults['pageexists']  = $this->getConf('pageexists');
        $this->defaults['parlink']   = $this->getConf('parlink');
        $this->defaults['inline']    = false;
        $this->defaults['order']     = $this->getConf('order');
        $this->defaults['rsort']     = $this->getConf('rsort');
        $this->defaults['depth']     = $this->getConf('depth');
        $this->defaults['revision']  = $this->getConf('revision');
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
        global $REV;
        global $DATE_AT;
        // load defaults
        $flags = $this->defaults;
        foreach ($setflags as $flag) {
            $value = '';
            if (strpos($flag, '=') !== -1) {
                list($flag, $value) = explode('=', $flag, 2);
            }
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
                case 'showheader':
                case 'header':
                    $flags['noheader'] = 0;
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
                case 'mdate':
                    $flags['mdate'] = 1;
                    break;
                case 'nomdate':
                    $flags['mdate'] = 0;
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
                case 'title':
                    $flags['title'] = 1;
                    break;
                case 'notitle':
                    $flags['title'] = 0;
                    break;
                case 'pageexists':
                    $flags['pageexists'] = 1;
                    break;
                case 'nopageexists':
                    $flags['pageexists'] = 0;
                    break;
                case 'existlink':
                    $flags['pageexists'] = 1;
                    $flags['linkonly'] = 1;
                    break;
                case 'parlink':
                    $flags['parlink'] = 1;
                    break;
                case 'noparlink':
                    $flags['parlink'] = 0;
                    break;
                case 'order':
                    $flags['order'] = $value;
                    break;
                case 'sort':
                    $flags['rsort'] = 0;
                    break;
                case 'rsort':
                    $flags['rsort'] = 1;
                    break;
                case 'depth':
                    $flags['depth'] = max(intval($value), 0);
                    break;
                case 'beforeeach':
                    $flags['beforeeach'] = $value;
                    break;
                case 'aftereach':
                    $flags['aftereach'] = $value;
                    break;
            }
        }
        // the include_content URL parameter overrides flags
        if (isset($_REQUEST['include_content']))
            $flags['linkonly'] = 0;
        
        //we have to disable some functions
        if (($flags['revision'] && $REV) || $DATE_AT) {
            $flags['editbtn']  = 0;
        }

        return $flags;
    }

    /**
     * Returns the converted instructions of a give page/section
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author Michael Hamann <michael@content-space.de>
     */
    function _get_instructions($page, $sect, $mode, $lvl, $flags, $root_id = null, $included_pages = array()) {
        $key = ($sect) ? $page . '#' . $sect : $page;
        $this->includes[$key] = true; // legacy code for keeping compatibility with other plugins

        // keep compatibility with other plugins that don't know the $root_id parameter
        if (is_null($root_id)) {
            global $ID;
            $root_id = $ID;
        }
        $page_rev = '';
        if(in_array($mode,array('page','section'))) {
            $page_rev = $this->_get_revision($page,$flags);
        }
        if ($flags['linkonly']) {
            if (page_exists($page,$page_rev) || $flags['pageexists']  == 0) {
                $title = '';
                if ($flags['title'])
                    $title = p_get_first_heading($page);
                if($flags['parlink']) {
                    $ins = array(
                        array('p_open', array()),
                        array('internallink', array(':'.$key, $title)),
                        array('p_close', array()),
                    );
                } else {
                    $ins = array(array('internallink', array(':'.$key,$title)));
                }
            }else {
                $ins = array();
            }
        } else {
            if (page_exists($page,$page_rev)) {
                global $ID;
                $backupID = $ID;
                $ID = $page; // Change the global $ID as otherwise plugins like the discussion plugin will save data for the wrong page
                if($page_rev){
                    $ins = p_get_instructions(io_readWikiPage(wikiFN($page,$page_rev),$page,$page_rev));
                } else {
                    $ins = p_cached_instructions(wikiFN($page), false, $page);
                }
                
                $ID = $backupID;
            } else {
                $ins = array();
            }

            $this->_convert_instructions($ins, $lvl, $page, $sect, $flags, $root_id, $included_pages, $page_rev);
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
    function _convert_instructions(&$ins, $lvl, $page, $sect, $flags, $root_id, $included_pages = array(), $page_rev = '') {
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
        $endpos     = null; // end position of the raw wiki text

        for($i=0; $i<$num; $i++) {
            // adjust links with image titles
            if (strpos($ins[$i][0], 'link') !== false && isset($ins[$i][1][1]) && is_array($ins[$i][1][1]) && $ins[$i][1][1]['type'] == 'internalmedia') {
                // resolve relative ids, but without cleaning in order to preserve the name
                $media_id = resolve_id($ns, $ins[$i][1][1]['src']);
                // make sure that after resolving the link again it will be the same link
                if ($media_id{0} != ':') $media_id = ':'.$media_id;
                $ins[$i][1][1]['src'] = $media_id;
            }

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
                    break;
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
                    if ($ins[$i][0] == 'internallink' && !empty($included_pages)) {
                        // change links to included pages into local links
                        $link_id = $ins[$i][1][0];
                        $link_parts = explode('?', $link_id, 2);
                        // only adapt links without parameters
                        if (count($link_parts) === 1) {
                            $link_parts = explode('#', $link_id, 2);
                            $hash = '';
                            if (count($link_parts) === 2) {
                                list($link_id, $hash) = $link_parts;
                            }
                            $exists = false;
                            resolve_pageid($ns, $link_id, $exists);
                            if (array_key_exists($link_id, $included_pages)) {
                                if ($hash) {
                                    // hopefully the hash is also unique in the including page (otherwise this might be the wrong link target)
                                    $ins[$i][0] = 'locallink';
                                    $ins[$i][1][0] = $hash;
                                } else {
                                    // the include section ids are different from normal section ids (so they won't conflict) but this
                                    // also means that the normal locallink function can't be used
                                    $ins[$i][0] = 'plugin';
                                    $ins[$i][1] = array('include_locallink', array($included_pages[$link_id]['hid'], $ins[$i][1][1], $ins[$i][1][0]));
                                }
                            }
                        }
                    }
                    break;
                case 'locallink':
                    /* Convert local links to internal links if the page hasn't been fully included */
                    if ($included_pages == null || !array_key_exists($page, $included_pages)) {
                        $ins[$i][0] = 'internallink';
                        $ins[$i][1][0] = ':'.$page.'#'.$ins[$i][1][0];
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
                        case 'indexmenu_tag':           // skip indexmenu sort tag
                        case 'include_sorttag':         // skip include plugin sort tag
                            unset($ins[$i]);
                            break;
                        // adapt indentation level of nested includes
                        case 'include_include':
                            if (!$flags['inline'] && $flags['indent'])
                                $ins[$i][1][1][4] += $lvl;
                            break;
                        /*
                         * if there is already a closelastsecedit instruction (was added by one of the section
                         * functions), store its position but delete it as it can't be determined yet if it is needed,
                         * i.e. if there is a header which generates a section edit (depends on the levels, level
                         * adjustments, $no_header, ...)
                         */
                        case 'include_closelastsecedit':
                            $endpos = $ins[$i][1][1][0];
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
            array_push($ins, array('plugin', array('include_closelastsecedit', array($endpos))));
        }

        // add edit button
        if($flags['editbtn']) {
            $this->_editbtn($ins, $page, $sect, $sect_title, ($flags['redirect'] ? $root_id : false));
        }

        // add footer
        if($flags['footer']) {
            $ins[] = $this->_footer($page, $sect, $sect_title, $flags, $footer_lvl, $root_id, $page_rev);
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
        $include_secid = (isset($flags['include_secid']) ? $flags['include_secid'] : NULL);
        array_unshift($ins, array('plugin', array('include_wrap', array('open', $page, $flags['redirect'], $include_secid))));
        if (isset($flags['beforeeach']))
            array_unshift($ins, array('entity', array($flags['beforeeach'])));
        array_push($ins, array('plugin', array('include_wrap', array('close'))));
        if (isset($flags['aftereach']))
            array_push($ins, array('entity', array($flags['aftereach'])));

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
    function _footer($page, $sect, $sect_title, $flags, $footer_lvl, $root_id, $page_rev) {
        $footer = array();
        $footer[0] = 'plugin';
        $footer[1] = array('include_footer', array($page, $sect, $sect_title, $flags, $root_id, $footer_lvl, $page_rev));
        return $footer;
    }

    /**
     * Appends instruction item for an edit button
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function _editbtn(&$ins, $page, $sect, $sect_title, $root_id) {
        $title = ($sect) ? $sect_title : $page;
        $editbtn = array();
        $editbtn[0] = 'plugin';
        $editbtn[1] = array('include_editbtn', array($title));
        $ins[] = $editbtn;
    }

    /**
     * Convert instruction item for a permalink header
     * 
     * @author Michael Klier <chi@chimeric.de>
     */
    function _permalink(&$ins, $page, $sect, $flags) {
        $ins[0] = 'plugin';
        $ins[1] = array('include_header', array($ins[1][0], $ins[1][1], $ins[1][2], $page, $sect, $flags));
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
        $endpos = null; // end position in the input text, needed for section edit buttons

        $check = array(); // used for sectionID() in order to get the same ids as the xhtml renderer

        for($i=0; $i<$num; $i++) {
            if ($ins[$i][0] == 'header') { 

                // found the right header 
                if (sectionID($ins[$i][1][0], $check) == $sect) {
                    $offset = $i;
                    $lvl    = $ins[$i][1][1]; 
                } elseif ($offset && $lvl && ($ins[$i][1][1] <= $lvl)) {
                    $end = $i - $offset;
                    $endpos = $ins[$i][1][2]; // the position directly after the found section, needed for the section edit button
                    break;
                }
            }
        }
        $offset = $offset ? $offset : 0;
        $end = $end ? $end : ($num - 1);
        if(is_array($ins)) {
            $ins = array_slice($ins, $offset, $end);
            // store the end position in the include_closelastsecedit instruction so it can generate a matching button
            $ins[] = array('plugin', array('include_closelastsecedit', array($endpos)));
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
        $endpos = null; // end position in the input text
        for($i=0; $i<$num; $i++) {
            if($ins[$i][0] == 'section_close') {
                $first_sect = $i;
            }
            if ($ins[$i][0] == 'header') {
                /*
                 * Store the position of the last header that is encountered. As section_close/open-instruction are
                 * always (unless some plugin modifies this) around a header instruction this means that the last
                 * position that is stored here is exactly the position of the section_close/open at which the content
                 * is truncated.
                 */
                $endpos = $ins[$i][1][2];
            }
            // only truncate the content and add the read more link when there is really
            // more than that first section
            if(($first_sect) && ($ins[$i][0] == 'section_open')) {
                $ins = array_slice($ins, 0, $first_sect);
                $ins[] = array('plugin', array('include_readmore', array($page)));
                $ins[] = array('section_close', array());
                // store the end position in the include_closelastsecedit instruction so it can generate a matching button
                $ins[] = array('plugin', array('include_closelastsecedit', array($endpos)));
                return;
            }
        }
    }

    /**
     * Gives a list of pages for a given include statement
     *
     * @author Michael Hamann <michael@content-space.de>
     */
    function _get_included_pages($mode, $page, $sect, $parent_id, $flags) {
        global $conf;
        global $REV;
        global $DATE_AT;
        $pages = array();
        switch($mode) {
        case 'namespace':
            $page  = cleanID($page);
            $ns    = utf8_encodeFN(str_replace(':', '/', $page));
            // depth is absolute depth, not relative depth, but 0 has a special meaning.
            $depth = $flags['depth'] ? $flags['depth'] + substr_count($page, ':') + ($page ? 1 : 0) : 0;
            search($pagearrays, $conf['datadir'], 'search_allpages', array('depth' => $depth), $ns);
            if (is_array($pagearrays)) {
                foreach ($pagearrays as $pagearray) {
                    if (!isHiddenPage($pagearray['id'])) // skip hidden pages
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
            $page = $this->_apply_macro($page,$flags);
            resolve_pageid(getNS($parent_id), $page, $exists); // resolve shortcuts and clean ID
            if (auth_quickaclcheck($page) >= AUTH_READ)
                $pages[] = $page;
        }

        if (count($pages) > 1) {
            if ($flags['order'] === 'id') {
                if ($flags['rsort']) {
                    usort($pages, array($this, '_r_strnatcasecmp'));
                } else {
                    natcasesort($pages);
                }
            } else {
                $ordered_pages = array();
                foreach ($pages as $page) {
                    $key = '';
                    switch ($flags['order']) {
                        case 'title':
                            $key = p_get_first_heading($page);
                            break;
                        case 'created':
                            $key = p_get_metadata($page, 'date created', METADATA_DONT_RENDER);
                            break;
                        case 'modified':
                            $key = p_get_metadata($page, 'date modified', METADATA_DONT_RENDER);
                            break;
                        case 'indexmenu':
                            $key = p_get_metadata($page, 'indexmenu_n', METADATA_RENDER_USING_SIMPLE_CACHE);
                            if ($key === null)
                                $key = '';
                            break;
                        case 'custom':
                            $key = p_get_metadata($page, 'include_n', METADATA_RENDER_USING_SIMPLE_CACHE);
                            if ($key === null)
                                $key = '';
                            break;
                    }
                    $key .= '_'.$page;
                    $ordered_pages[$key] = $page;
                }
                if ($flags['rsort']) {
                    uksort($ordered_pages, array($this, '_r_strnatcasecmp'));
                } else {
                    uksort($ordered_pages, 'strnatcasecmp');
                }
                $pages = $ordered_pages;
            }
        }

        $result = array();
        foreach ($pages as $page) {
            $page_rev = $this->_get_revision($page,$flags);
            $exists = page_exists($page,$page_rev);
            $result[] = array('id' => $page, 'exists' => $exists, 'parent_id' => $parent_id);
        }
        return $result;
    }

    /**
     * String comparisons using a "natural order" algorithm in reverse order
     *
     * @link http://php.net/manual/en/function.strnatcmp.php
     * @param string $a First string
     * @param string $b Second string
     * @return int Similar to other string comparison functions, this one returns &lt; 0 if
     * str1 is greater than str2; &gt;
     * 0 if str1 is lesser than
     * str2, and 0 if they are equal.
     */
    function _r_strnatcasecmp($a, $b) {
        return strnatcasecmp($b, $a);
    }

    /**
     * This function generates the list of all included pages from a list of metadata
     * instructions.
     */
    function _get_included_pages_from_meta_instructions($instructions) {
        $pages = array();
        foreach ($instructions as $instruction) {
            $mode      = $instruction['mode'];
            $page      = $instruction['page'];
            $sect      = $instruction['sect'];
            $parent_id = $instruction['parent_id'];
            $flags     = $instruction['flags'];
            $pages = array_merge($pages, $this->_get_included_pages($mode, $page, $sect, $parent_id, $flags));
        }
        return $pages;
    }

    /**
     * Makes user or date dependent includes possible
     */
    function _apply_macro($id,$flags) {
        global $INFO;
        global $auth;
        
        // if we don't have an auth object, do nothing
        if (!$auth) return $id;

        $user     = $_SERVER['REMOTE_USER'];
        $group    = $INFO['userinfo']['grps'][0];

        if(($flags['revision'] && $REV) || $DATE_AT) { 
            $time_stamp = max($REV,$DATE_AT);
        } else {
            $time_stamp = time();
        }
        if(preg_match('/@DATE(\w+)@/',$id,$matches)) {
            switch($matches[1]) {
            case 'PMONTH':
                $time_stamp = strtotime("-1 month",$time_stamp);
                break;
            case 'NMONTH':
                $time_stamp = strtotime("+1 month",$time_stamp);
                break;
            case 'NWEEK':
                $time_stamp = strtotime("+1 week",$time_stamp);
                break;
            case 'PWEEK':
                $time_stamp = strtotime("-1 week",$time_stamp);
                break;
            case 'TOMORROW':
                $time_stamp = strtotime("+1 day",$time_stamp);
                break;
            case 'YESTERDAY':
                $time_stamp = strtotime("-1 day",$time_stamp);
                break;
            case 'NYEAR':
                $time_stamp = strtotime("+1 year",$time_stamp);
                break;
            case 'PYEAR':
                $time_stamp = strtotime("-1 year",$time_stamp);
                break;
            }
            $id = preg_replace('/@DATE(\w+)@/','', $id);
        }

        $replace = array(
                '@USER@'  => cleanID($user),
                '@NAME@'  => cleanID($INFO['userinfo']['name']),
                '@GROUP@' => cleanID($group),
                '@YEAR@'  => date('Y',$time_stamp),
                '@MONTH@' => date('m',$time_stamp),
                '@WEEK@' => date('W',$time_stamp),
                '@DAY@'   => date('d',$time_stamp),
                '@YEARPMONTH@' => date('Ym',strtotime("-1 month",$time_stamp)),
                '@PMONTH@' => date('m',strtotime("-1 month",$time_stamp)),
                '@NMONTH@' => date('m',strtotime("+1 month",$time_stamp)),
                '@YEARNMONTH@' => date('Ym',strtotime("+1 month",$time_stamp)),
                '@YEARPWEEK@' => date('YW',strtotime("-1 week",$time_stamp)),
                '@PWEEK@' => date('W',strtotime("-1 week",$time_stamp)),
                '@NWEEK@' => date('W',strtotime("+1 week",$time_stamp)),
                '@YEARNWEEK@' => date('YW',strtotime("+1 week",$time_stamp)),
                );
        return str_replace(array_keys($replace), array_values($replace), $id);
    }
    
    
    /**
     * returns the revsision of a page 
     * based on configuration($flags), $REV and $DATE_AT
     *
     * @param string $page page id
     * @param array  $flags configuration array see get_flags()
     * @return string revision ('' if current)
     **/
    function _get_revision($page,$flags) {
        global $DATE_AT;
        global $REV;
        $page_rev = '';
        if(($flags['revision'] && $REV) || $DATE_AT) {
            if (method_exists('PageChangeLog', 'getLastRevisionAt')) {
                $pagelog = new PageChangeLog($page);
            } else { 
                $pagelog = new helper_plugin_include_PageChangelog($page);
            }
            $page_rev = $pagelog->getLastRevisionAt($DATE_AT ? $DATE_AT : $REV);
        }
        return $page_rev; 
    }
}

/******************************************************************************
 * Following code is copied from inc/changelog.php from diff_navigation branch
 ******************************************************************************/

/**
 * Class ChangeLog
 * methods for handling of changelog of pages or media files
 */
abstract class helper_plugin_include_ChangeLog {

    /** @var string */
    protected $id;
    /** @var int */
    protected $chunk_size;
    /** @var array */
    protected $cache;

    /**
     * Constructor
     *
     * @param string $id         page id
     * @param int $chunk_size maximum block size read from file
     */
    public function __construct($id, $chunk_size = 8192) {
        global $cache_revinfo;

        $this->cache =& $cache_revinfo;
        if(!isset($this->cache[$id])) {
            $this->cache[$id] = array();
        }

        $this->id = $id;
        $this->setChunkSize($chunk_size);

    }

    /**
     * Set chunk size for file reading
     * Chunk size zero let read whole file at once
     *
     * @param int $chunk_size maximum block size read from file
     */
    public function setChunkSize($chunk_size) {
        if(!is_numeric($chunk_size)) $chunk_size = 0;

        $this->chunk_size = (int) max($chunk_size, 0);
    }

    /**
     * Returns path to changelog
     *
     * @return string path to file
     */
    abstract protected function getChangelogFilename();

    /**
     * Returns path to current page/media
     *
     * @return string path to file
     */
    abstract protected function getFilename();

    /**
     * Get the changelog information for a specific page id and revision (timestamp)
     *
     * Adjacent changelog lines are optimistically parsed and cached to speed up
     * consecutive calls to getRevisionInfo. For large changelog files, only the chunk
     * containing the requested changelog line is read.
     *
     * @param int $rev        revision timestamp
     * @return bool|array false or array with entries:
     *      - date:  unix timestamp
     *      - ip:    IPv4 address (127.0.0.1)
     *      - type:  log line type
     *      - id:    page id
     *      - user:  user name
     *      - sum:   edit summary (or action reason)
     *      - extra: extra data (varies by line type)
     *
     * @author Ben Coburn <btcoburn@silicodon.net>
     * @author Kate Arzamastseva <pshns@ukr.net>
     */
    public function getRevisionInfo($rev) {
        $rev = max($rev, 0);

        // check if it's already in the memory cache
        if(isset($this->cache[$this->id]) && isset($this->cache[$this->id][$rev])) {
            return $this->cache[$this->id][$rev];
        }

        //read lines from changelog
        list($fp, $lines) = $this->readloglines($rev);
        if($fp) {
            fclose($fp);
        }
        if(empty($lines)) return false;

        // parse and cache changelog lines
        foreach($lines as $value) {
            $tmp = parseChangelogLine($value);
            if($tmp !== false) {
                $this->cache[$this->id][$tmp['date']] = $tmp;
            }
        }
        if(!isset($this->cache[$this->id][$rev])) {
            return false;
        }
        return $this->cache[$this->id][$rev];
    }

    /**
     * Return a list of page revisions numbers
     *
     * Does not guarantee that the revision exists in the attic,
     * only that a line with the date exists in the changelog.
     * By default the current revision is skipped.
     *
     * The current revision is automatically skipped when the page exists.
     * See $INFO['meta']['last_change'] for the current revision.
     * A negative $first let read the current revision too.
     *
     * For efficiency, the log lines are parsed and cached for later
     * calls to getRevisionInfo. Large changelog files are read
     * backwards in chunks until the requested number of changelog
     * lines are recieved.
     *
     * @param int $first      skip the first n changelog lines
     * @param int $num        number of revisions to return
     * @return array with the revision timestamps
     *
     * @author Ben Coburn <btcoburn@silicodon.net>
     * @author Kate Arzamastseva <pshns@ukr.net>
     */
    public function getRevisions($first, $num) {
        $revs = array();
        $lines = array();
        $count = 0;

        $num = max($num, 0);
        if($num == 0) {
            return $revs;
        }

        if($first < 0) {
            $first = 0;
        } else if(@file_exists($this->getFilename())) {
            // skip current revision if the page exists
            $first = max($first + 1, 0);
        }

        $file = $this->getChangelogFilename();

        if(!@file_exists($file)) {
            return $revs;
        }
        if(filesize($file) < $this->chunk_size || $this->chunk_size == 0) {
            // read whole file
            $lines = file($file);
            if($lines === false) {
                return $revs;
            }
        } else {
            // read chunks backwards
            $fp = fopen($file, 'rb'); // "file pointer"
            if($fp === false) {
                return $revs;
            }
            fseek($fp, 0, SEEK_END);
            $tail = ftell($fp);

            // chunk backwards
            $finger = max($tail - $this->chunk_size, 0);
            while($count < $num + $first) {
                $nl = $this->getNewlinepointer($fp, $finger);

                // was the chunk big enough? if not, take another bite
                if($nl > 0 && $tail <= $nl) {
                    $finger = max($finger - $this->chunk_size, 0);
                    continue;
                } else {
                    $finger = $nl;
                }

                // read chunk
                $chunk = '';
                $read_size = max($tail - $finger, 0); // found chunk size
                $got = 0;
                while($got < $read_size && !feof($fp)) {
                    $tmp = @fread($fp, max($read_size - $got, 0)); //todo why not use chunk_size?
                    if($tmp === false) {
                        break;
                    } //error state
                    $got += strlen($tmp);
                    $chunk .= $tmp;
                }
                $tmp = explode("\n", $chunk);
                array_pop($tmp); // remove trailing newline

                // combine with previous chunk
                $count += count($tmp);
                $lines = array_merge($tmp, $lines);

                // next chunk
                if($finger == 0) {
                    break;
                } // already read all the lines
                else {
                    $tail = $finger;
                    $finger = max($tail - $this->chunk_size, 0);
                }
            }
            fclose($fp);
        }

        // skip parsing extra lines
        $num = max(min(count($lines) - $first, $num), 0);
        if     ($first > 0 && $num > 0)  { $lines = array_slice($lines, max(count($lines) - $first - $num, 0), $num); }
        else if($first > 0 && $num == 0) { $lines = array_slice($lines, 0, max(count($lines) - $first, 0)); }
        else if($first == 0 && $num > 0) { $lines = array_slice($lines, max(count($lines) - $num, 0)); }

        // handle lines in reverse order
        for($i = count($lines) - 1; $i >= 0; $i--) {
            $tmp = parseChangelogLine($lines[$i]);
            if($tmp !== false) {
                $this->cache[$this->id][$tmp['date']] = $tmp;
                $revs[] = $tmp['date'];
            }
        }

        return $revs;
    }

    /**
     * Get the nth revision left or right handside  for a specific page id and revision (timestamp)
     *
     * For large changelog files, only the chunk containing the
     * reference revision $rev is read and sometimes a next chunck.
     *
     * Adjacent changelog lines are optimistically parsed and cached to speed up
     * consecutive calls to getRevisionInfo.
     *
     * @param int $rev        revision timestamp used as startdate (doesn't need to be revisionnumber)
     * @param int $direction  give position of returned revision with respect to $rev; positive=next, negative=prev
     * @return bool|int
     *      timestamp of the requested revision
     *      otherwise false
     */
    public function getRelativeRevision($rev, $direction) {
        $rev = max($rev, 0);
        $direction = (int) $direction;

        //no direction given or last rev, so no follow-up
        if(!$direction || ($direction > 0 && $this->isCurrentRevision($rev))) {
            return false;
        }

        //get lines from changelog
        list($fp, $lines, $head, $tail, $eof) = $this->readloglines($rev);
        if(empty($lines)) return false;

        // look for revisions later/earlier then $rev, when founded count till the wanted revision is reached
        // also parse and cache changelog lines for getRevisionInfo().
        $revcounter = 0;
        $relativerev = false;
        $checkotherchunck = true; //always runs once
        while(!$relativerev && $checkotherchunck) {
            $tmp = array();
            //parse in normal or reverse order
            $count = count($lines);
            if($direction > 0) {
                $start = 0;
                $step = 1;
            } else {
                $start = $count - 1;
                $step = -1;
            }
            for($i = $start; $i >= 0 && $i < $count; $i = $i + $step) {
                $tmp = parseChangelogLine($lines[$i]);
                if($tmp !== false) {
                    $this->cache[$this->id][$tmp['date']] = $tmp;
                    //look for revs older/earlier then reference $rev and select $direction-th one
                    if(($direction > 0 && $tmp['date'] > $rev) || ($direction < 0 && $tmp['date'] < $rev)) {
                        $revcounter++;
                        if($revcounter == abs($direction)) {
                            $relativerev = $tmp['date'];
                        }
                    }
                }
            }

            //true when $rev is found, but not the wanted follow-up.
            $checkotherchunck = $fp
                && ($tmp['date'] == $rev || ($revcounter > 0 && !$relativerev))
                && !(($tail == $eof && $direction > 0) || ($head == 0 && $direction < 0));

            if($checkotherchunck) {
                //search bounds of chunck, rounded on new line, but smaller than $chunck_size
                if($direction > 0) {
                    $head = $tail;
                    $tail = $head + floor($this->chunk_size * (2 / 3));
                    $tail = $this->getNewlinepointer($fp, $tail);
                } else {
                    $tail = $head;
                    $head = max($tail - $this->chunk_size, 0);
                    while(true) {
                        $nl = $this->getNewlinepointer($fp, $head);
                        // was the chunk big enough? if not, take another bite
                        if($nl > 0 && $tail <= $nl) {
                            $head = max($head - $this->chunk_size, 0);
                        } else {
                            $head = $nl;
                            break;
                        }
                    }
                }

                //load next chunck
                $lines = $this->readChunk($fp, $head, $tail);
                if(empty($lines)) break;
            }
        }
        if($fp) {
            fclose($fp);
        }

        return $relativerev;
    }

    /**
     * Returns lines from changelog.
     * If file larger than $chuncksize, only chunck is read that could contain $rev.
     *
     * @param int $rev   revision timestamp
     * @return array(fp, array(changeloglines), $head, $tail, $eof)|bool
     *     returns false when not succeed. fp only defined for chuck reading, needs closing.
     */
    protected function readloglines($rev) {
        $file = $this->getChangelogFilename();

        if(!@file_exists($file)) {
            return false;
        }

        $fp = null;
        $head = 0;
        $tail = 0;
        $eof = 0;

        if(filesize($file) < $this->chunk_size || $this->chunk_size == 0) {
            // read whole file
            $lines = file($file);
            if($lines === false) {
                return false;
            }
        } else {
            // read by chunk
            $fp = fopen($file, 'rb'); // "file pointer"
            if($fp === false) {
                return false;
            }
            $head = 0;
            fseek($fp, 0, SEEK_END);
            $eof = ftell($fp);
            $tail = $eof;

            // find chunk
            while($tail - $head > $this->chunk_size) {
                $finger = $head + floor(($tail - $head) / 2.0);
                $finger = $this->getNewlinepointer($fp, $finger);
                $tmp = fgets($fp);
                if($finger == $head || $finger == $tail) {
                    break;
                }
                $tmp = parseChangelogLine($tmp);
                $finger_rev = $tmp['date'];

                if($finger_rev > $rev) {
                    $tail = $finger;
                } else {
                    $head = $finger;
                }
            }

            if($tail - $head < 1) {
                // cound not find chunk, assume requested rev is missing
                fclose($fp);
                return false;
            }

            $lines = $this->readChunk($fp, $head, $tail);
        }
        return array(
            $fp,
            $lines,
            $head,
            $tail,
            $eof
        );
    }

    /**
     * Read chunk and return array with lines of given chunck.
     * Has no check if $head and $tail are really at a new line
     *
     * @param $fp resource filepointer
     * @param $head int start point chunck
     * @param $tail int end point chunck
     * @return array lines read from chunck
     */
    protected function readChunk($fp, $head, $tail) {
        $chunk = '';
        $chunk_size = max($tail - $head, 0); // found chunk size
        $got = 0;
        fseek($fp, $head);
        while($got < $chunk_size && !feof($fp)) {
            $tmp = @fread($fp, max(min($this->chunk_size, $chunk_size - $got), 0));
            if($tmp === false) { //error state
                break;
            }
            $got += strlen($tmp);
            $chunk .= $tmp;
        }
        $lines = explode("\n", $chunk);
        array_pop($lines); // remove trailing newline
        return $lines;
    }

    /**
     * Set pointer to first new line after $finger and return its position
     *
     * @param $fp resource filepointer
     * @param $finger int a pointer
     * @return int pointer
     */
    protected function getNewlinepointer($fp, $finger) {
        fseek($fp, $finger);
        $nl = $finger;
        if($finger > 0) {
            fgets($fp); // slip the finger forward to a new line
            $nl = ftell($fp);
        }
        return $nl;
    }

    /**
     * Check whether given revision is the current page
     *
     * @param int $rev   timestamp of current page
     * @return bool true if $rev is current revision, otherwise false
     */
    public function isCurrentRevision($rev) {
        return $rev == @filemtime($this->getFilename());
    }
    
    /**
    * Return an existing revision for a specific date which is 
    * the current one or younger or equal then the date
    *
    * @param string $id 
    * @param number $date_at timestamp
    * @return string revision ('' for current)
    */
    function getLastRevisionAt($date_at){
        //requested date_at(timestamp) younger or equal then modified_time($this->id) => load current
        if($date_at >= @filemtime($this->getFilename())) { 
            return '';
        } else if ($rev = $this->getRelativeRevision($date_at+1, -1)) { //+1 to get also the requested date revision
            return $rev;
        } else {
            return false;
        }
    }
}

class helper_plugin_include_PageChangelog extends helper_plugin_include_ChangeLog {

    /**
     * Returns path to changelog
     *
     * @return string path to file
     */
    protected function getChangelogFilename() {
        return metaFN($this->id, '.changes');
    }

    /**
     * Returns path to current page/media
     *
     * @return string path to file
     */
    protected function getFilename() {
        return wikiFN($this->id);
    }
}
// vim:ts=4:sw=4:et:
