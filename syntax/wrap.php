<?php
/**
 * Include plugin (wrapper component)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Michael Klier <chi@chimeric.de>
 * @author  Michael Hamann <michael@content-space.de>
 */

if (!defined('DOKU_INC'))
    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
require_once (DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_include_wrap extends DokuWiki_Syntax_Plugin {

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
     * Wraps the included page in a div and writes section edits for the action component
     * so it can detect where an included page starts/ends.
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author Michael Hamann <michael@content-space.de>
     */
    function render($mode, &$renderer, $data) {
        if ($mode == 'xhtml') {
            switch($data[0]) {
                case 'open':
                    if ($data[2]) { // $data[2] = $flags['redirect']
                        $renderer->startSectionEdit(0, 'plugin_include_start', $data[1]);
                    } else {
                        $renderer->startSectionEdit(0, 'plugin_include_start_noredirect', $data[1]);
                    }
                    $renderer->finishSectionEdit();
                    // Start a new section with type != section so headers in the included page
                    // won't print section edit buttons of the parent page
                    $renderer->startSectionEdit(0, 'plugin_include_end', $data[1]);
                    $renderer->doc .= '<div class="plugin_include_content plugin_include__' . $data[1] . '">' . DOKU_LF;
                    break;
                case 'close':
                    $renderer->finishSectionEdit();
                    $renderer->doc .= '</div>' . DOKU_LF;
                    break;
            }
            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et:
