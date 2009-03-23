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

class syntax_plugin_include_header extends DokuWiki_Syntax_Plugin {

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
        return 50;
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
        list($headline, $lvl, $page, $sect) = $data;
        $hid = $renderer->_headerToLink($headline);
        if ($mode == 'xhtml') {
            $renderer->toc_additem($hid, $headline, $lvl);
            $url = ($sect) ? wl($page) . '#' . $sect : wl($page);
            $renderer->doc .= DOKU_LF.'<h' . $lvl . '><a name="' . $hid . '" id="' . $hid . '" href="' . $url . '">';
            $renderer->doc .= $renderer->_xmlEntities($headline);
            $renderer->doc .= '</a></h' . $lvl . '>' . DOKU_LF;
            return true;
        } elseif($mode == 'metadata') {
            $renderer->toc_additem($hid, $headline, $lvl);
            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
