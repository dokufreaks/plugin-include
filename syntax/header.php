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
            'date' => '2008-06-28',
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
    function render($mode, &$renderer, $indata) {
        global $ID;
        list($text, $level) = $indata;
        
        if ($mode == 'xhtml') {
	        $hid = $renderer->_headerToLink($text,true);
	
	        //only add items within configured levels
	        $renderer->toc_additem($hid, $text, $level);
	
	        // write the header
	        $renderer->doc .= DOKU_LF.'<h'.$level.'><a name="'.$hid.'" id="'.$hid.'" href="'.wl($ID).'">';
	        $renderer->doc .= $renderer->_xmlEntities($text);
	        $renderer->doc .= "</a></h$level>".DOKU_LF;
	        
	        return true;
        }

        // unsupported $mode
        return false;
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
