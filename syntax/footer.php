<?php
/**
 * Include plugin (footer component)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Michael Klier <chi@chimeric.de>
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_include_footer extends DokuWiki_Syntax_Plugin {

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
            $renderer->doc .= $this->html_footer($page, $sect, $sect_title, $flags, $footer_lvl, $renderer);
	        return true;
        }
        return false;
    }

    /**
     * Returns the meta line below the included page
     */
    function html_footer($page, $sect, $sect_title, $flags, $footer_lvl, &$renderer) {
        global $conf, $ID;

        if(!$flags['footer']) return '';

        $meta  = p_get_metadata($page);
        $xhtml = array();

        // permalink
        if ($flags['permalink']) {
            $class = (page_exists($page) ? 'wikilink1' : 'wikilink2');
            $url   = ($sect) ? wl($page) . '#' . $sect : wl($page);
            $name  = ($sect) ? $sect_title : $page;
            $title = ($sect) ? $page . '#' . $sect : $page;
            if (!$title) $title = str_replace('_', ' ', noNS($page));
            $link = array(
                    'url'    => $url,
                    'title'  => $title,
                    'name'   => $name,
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
        if (empty($sect) && $flags['comments'] && (!plugin_isdisabled('discussion')) && ($discussion =& plugin_load('helper', 'discussion'))) {
            $disc = $discussion->td($page);
            if ($disc) $xhtml[] = '<span class="comment">' . $disc . '</span>';
        }

        // linkbacks - let Linkback Plugin do the work for us
        if (empty($sect) && $flags['linkbacks'] && (!plugin_isdisabled('linkback')) && ($linkback =& plugin_load('helper', 'linkback'))) {
            $link = $linkback->td($page);
            if ($link) $xhtml[] = '<span class="linkback">' . $link . '</span>';
        }

        $xhtml = implode(DOKU_LF . DOKU_TAB . '&middot; ', $xhtml);

        // tags - let Tag Plugin do the work for us
        if (empty($sect) && $flags['tags'] && (!plugin_isdisabled('tag')) && ($tag =& plugin_load('helper', 'tag'))) {
            $tags = $tag->td($page);
            if($tags) {
                $xhtml .= '<div class="tags"><span>' . DOKU_LF
                              . DOKU_TAB . $tags . DOKU_LF
                              . DOKU_TAB . '</span></div>' . DOKU_LF;
            }
        }

        if (!$xhtml) $xhtml = '&nbsp;';
        $class = 'inclmeta';
        $class .= ' level' . $footer_lvl;
        return '<div class="' . $class . '">' . DOKU_LF . DOKU_TAB . $xhtml . DOKU_LF . '</div>' . DOKU_LF;
    }
}
// vim:ts=4:sw=4:et:
