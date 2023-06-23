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

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_include_include extends DokuWiki_Syntax_Plugin {

    /** @var $helper helper_plugin_include */
    var $helper = null;

    /**
     * Get syntax plugin type.
     *
     * @return string The plugin type.
     */
    function getType() { return 'substition'; }

    /**
     * Get sort order of syntax plugin.
     *
     * @return int The sort order.
     */
    function getSort() { return 303; }

    /**
     * Get paragraph type.
     *
     * @return string The paragraph type.
     */
    function getPType() { return 'block'; }

    /**
     * Connect patterns/modes
     *
     * @param $mode mixed The current mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern("{{page>.+?}}", $mode, 'plugin_include_include');
        $this->Lexer->addSpecialPattern("{{section>.+?}}", $mode, 'plugin_include_include');
        $this->Lexer->addSpecialPattern("{{namespace>.+?}}", $mode, 'plugin_include_include');
        $this->Lexer->addSpecialPattern("{{tagtopic>.+?}}", $mode, 'plugin_include_include');
    }

    /**
     * Handle syntax matches
     *
     * @param string       $match   The current match
     * @param int          $state   The match state
     * @param int          $pos     The position of the match
     * @param Doku_Handler $handler The hanlder object
     * @return array The instructions of the plugin
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {

        $match = substr($match, 2, -2); // strip markup
        list($match, $flags) = array_pad(explode('&', $match, 2), 2, '');

        // break the pattern up into its parts
        list($mode, $page, $sect) = array_pad(preg_split('/>|#/u', $match, 3), 3, null);
        $check = false;
        if (isset($sect)) $sect = sectionID($sect, $check);
        $level = NULL;
        return array($mode, $page, $sect, explode('&', $flags), $level, $pos);
    }

    /**
     * Renders the included page(s)
     *
     * @author Michael Hamann <michael@content-space.de>
     */
    function render($format, Doku_Renderer $renderer, $data) {
        if (!$this->helper)
            $this->helper = plugin_load('helper', 'include');

        list($mode, $page, $sect, $flags, $level, $pos) = $data;

        $pages = $this->helper->_get_included_pages($mode, $page, $sect, $this->helper->get_page_stack_parent_id(), $flags);

        $this->helper->render(
            $format, $renderer,
            $pages,
            $mode, $page, $sect, $flags, $level, $pos
        );
    }

}
// vim:ts=4:sw=4:et:
