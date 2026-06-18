<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * Include plugin (editbtn header component)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Michael Klier <chi@chimeric.de>
 */

class syntax_plugin_include_editbtn extends SyntaxPlugin
{
    public function getType()
    {
        return 'formatting';
    }

    public function getSort()
    {
        return 50;
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // this is a syntax plugin that doesn't offer any syntax, so there's nothing to handle by the parser
    }

    /**
     * Renders an include edit button
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        [$title, $hid] = $data;
        if ($mode == 'xhtml') {
            if (defined('SEC_EDIT_PATTERN')) { // for DokuWiki Greebo and more recent versions
                $renderer->startSectionEdit(0, ['target' => 'plugin_include_editbtn', 'name' => $title, 'hid' => $hid]);
            } else {
                $renderer->startSectionEdit(0, 'plugin_include_editbtn', $title);
            }

            $renderer->finishSectionEdit();
            return true;
        }
        return false;
    }
}
// vim:ts=4:sw=4:et:
