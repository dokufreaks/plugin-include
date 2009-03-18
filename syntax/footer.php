<?php
/**
 * Include plugin (permalink header component)
 *
 * Provides a header instruction which renders a permalink to the included page
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <osd@foosel.net>
 * @author  Michael Klier <chi@chimeric.de>
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_include_footer extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return array (
            'author' => 'Gina Häußge, Michael Klier',
            'email' => 'dokuwiki@chimeric.de',
            'date' => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
            'name' => 'Include Plugin (permalink header component)',
            'desc' => 'Provides a header instruction which renders a permalink to the included page',
            'url' => 'http://wiki.splitbrain.org/plugin:include',
        );
    }

    function getType() {
        return 'formatting';
    }
    
    function getSort() {
        return 300;
    }

    function handle($match, $state, $pos, &$handler) {
        // this is a syntax plugin that doesn't offer any syntax, so there's nothing to handle by the parser
    }

    /**
     * Renders a permalink header.
     * 
     * Code heavily copied from the header renderer from inc/parser/xhtml.php, just
     * added an href parameter to the anchor tag linking to the wikilink.
     */
    function render($mode, &$renderer, $data) {

        list($page, $sect, $sect_title, $flags, $redirect_id, $footer_lvl) = $data;
        
        if ($mode == 'xhtml') {
            $renderer->doc .= $this->html_editButton($page, $flags, $redirect_id);
            $renderer->doc .= $this->html_footer($page, $sect, $sect_title, $flags, $footer_lvl, $renderer);
	        return true;
        }
        return false;
    }

    /** 
     * Display an edit button for the included page 
     */ 
    function html_editButton($page, $flags, $redirect_id) {
        global $lang;

        if($flags['editbtn']) return '';

        $xhtml = '';
        if(auth_quickaclcheck($page) >= AUTH_EDIT) {
            $params = array('do' => 'edit');
            if($flags['redirect']) $params['redirect_id'] = $redirect_id;
            $xhtml = '<div class="secedit">' . DOKU_LF
                   .  DOKU_TAB . html_btn('secedit', $page, '', $params, 'post') . DOKU_LF
                   . '</div>' . DOKU_LF;
            return $xhtml;
        }
    } 

    /**
     * Returns the meta line below the included page
     */
    function html_footer($page, $sect, $sect_title, $flags, $footer_lvl, &$renderer) {
        global $conf, $ID;

        if(!$flags['footer']) return '';

        preg_match_all('|<div class="level(\d)">|i', $renderer->doc, $matches, PREG_SET_ORDER);
        $lvl = $matches[count($matches)-1][1];
        if($lvl <= 0) $lvl =1;

        $meta  = p_get_metadata($page);
        $xhtml = array();

        // permalink
        if ($flags['link']) {
            $class = (page_exists($page) ? 'wikilink1' : 'wikilink2');
            if(!empty($sect)) $page = $page . '#' . $sect;
            $title = $sect_title;
            if (!$title) $title = str_replace('_', ' ', noNS($page));
            $link = array(
                    'url'    => wl($page),
                    'title'  => $page,
                    'name'   => hsc($title),
                    'target' => $conf['target']['wiki'],
                    'class'  => $class . ' permalink',
                    'more'   => 'rel="bookmark"',
                    );
            $xhtml[] = $renderer->_formatLink($link);
        }

        // date
        if ($flags['date']) {
            $date = $meta['date']['created'];
            if ($date) {
                $xhtml[] = '<abbr class="published" title="'.strftime('%Y-%m-%dT%H:%M:%SZ', $date).'">'
                       . strftime($conf['dformat'], $date)
                       . '</abbr>';
            }
        }

        // author
        if ($flags['user']) {
            $author   = $meta['creator'];
            if ($author) {
                $userpage = cleanID($this->getConf('usernamespace').':'.$author);
                resolve_pageid(getNS($ID), $userpage, $exists);
                $class = ($exists ? 'wikilink1' : 'wikilink2');
                $link = array(
                        'url'    => wl($userpage),
                        'title'  => $userpage,
                        'name'   => hsc($author),
                        'target' => $conf['target']['wiki'],
                        'class'  => $class.' url fn',
                        'pre'    => '<span class="vcard author">',
                        'suf'    => '</span>',
                        );
                $xhtml[]    = $renderer->_formatLink($link);
            }
        }

        // comments - let Discussion Plugin do the work for us
        if (empty($sec) && $flags['comments'] && (!plugin_isdisabled('discussion')) && ($discussion =& plugin_load('helper', 'discussion'))) {
            $disc = $discussion->td($page);
            if ($disc) $xhtml[] = '<span class="comment">' . $disc . '</span>';
        }

        // linkbacks - let Linkback Plugin do the work for us
        if (empty($sect) && $flags['linkbacks'] && (!plugin_isdisabled('linkback')) && ($linkback =& plugin_load('helper', 'linkback'))) {
            $link = $linkback->td($id);
            if ($link) $xhtml[] = '<span class="linkback">' . $link . '</span>';
        }

        $xhtml = implode(DOKU_LF . DOKU_TAB . '&middot; ', $xhtml);

        // tags - let Tag Plugin do the work for us
        if (empty($sect) && $flags['showtags'] && (!plugin_isdisabled('tag')) && ($tag =& plugin_load('helper', 'tag'))) {
            $page['tags'] = '<div class="tags"><span>' . DOKU_LF
                          . DOKU_TAB . $tag->td($id) . DOKU_LF
                          . DOKU_TAB . '</span></div>' . DOKU_LF;
            $xhtml = $page['tags'] . DOKU_TAB . $xhtml;
        }

        if (!$xhtml) $xhtml = '&nbsp;';
        $class = 'inclmeta';
        $class .= ' level' . $footer_lvl;
        return '<div class="' . $class . '">' . DOKU_LF . DOKU_TAB . $xhtml . DOKU_LF . '</div>' . DOKU_LF;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
