<?php
/**
 * Include Plugin parameter
 */

 /**
  * All DokuWiki plugins to extend the parser/rendering mechanism
  * need to inherit from this class
  */
class syntax_plugin_include_placeholder extends DokuWiki_Syntax_Plugin {

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
    function getSort() { return 300; }

    /**
     * Connect patterns/modes
     *
     * @param $mode mixed The current mode
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern("{{{.+?}}}", $mode, 'plugin_include_placeholder');
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
    function handle($match, $state, $pos, Doku_handler $handler) {
        $name = substr($match, 3, -3);  // strip markup
        return array($name, $pos);
    }

    /**
     * Render template field as bold, italic text.
     */
    function render($format, Doku_Renderer $renderer, $data) {
        if ($format !== 'xhtml') return false;
        $renderer->doc .= "<i><b>{{{".$data[0]."}}}</b></i>";
        return true;
    }

}
