<?php
/**
 * Include plugin (editbtn header component)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Michael Klier <chi@chimeric.de>
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_include_editbtn extends DokuWiki_Syntax_Plugin {

    function getInfo() {
        return array (
            'author' => 'Gina Häußge, Michael Klier',
            'email' => 'dokuwiki@chimeric.de',
            'date' => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
            'name' => 'Include Plugin (permalink header component)',
            'desc' => 'Provides a header instruction which renders a permalink to the included page',
            'url' => 'http://dokuwiki.org/plugin:include',
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
     * Renders an include edit button
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function render($mode, &$renderer, $data) {
        global $lang;
        list($page, $sect, $sect_title, $redirect_id) = $data;
        if ($mode == 'xhtml') {
            $title = ($sect) ? $sect_title : $page;
            $params = array('do' => 'edit', 
                             'redirect_id' => $redirect_id, 
                             'id' => $page);
            $xhtml .= '<div class="secedit">' . DOKU_LF;
            $xhtml .= '<form class="button btn_incledit" method="post" action="' . DOKU_SCRIPT . '"><div class="no">' . DOKU_LF;
            foreach($params as $key => $val) {
                $xhtml .= '<input type="hidden" name="'.$key.'" ';
                $xhtml .= 'value="'.htmlspecialchars($val).'" />';
            }
            $xhtml .= '<input type="submit" value="'.htmlspecialchars($lang['btn_secedit']).' (' . $page . ')" class="button" title="'.$title.'"/>' . DOKU_LF;
            $xhtml .= '</div></form>' . DOKU_LF;
            $xhtml .= '</div>' . DOKU_LF;
            $renderer->doc .= $xhtml;
            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et:enc=utf-8:
