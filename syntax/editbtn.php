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
    /** @inheritdoc */
    public function getType()
    {
        return 'formatting';
    }

    /** @inheritdoc */
    public function getSort()
    {
        return 50;
    }

    /** @inheritdoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // this is a syntax plugin that doesn't offer any syntax, so there's nothing to handle by the parser
    }

    /**
     * Renders an include edit button
     *
     * @inheritdoc
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        [$title, $hid] = $data;
        if ($mode != 'xhtml') return false;

        $renderer->startSectionEdit(0, ['target' => 'plugin_include_editbtn', 'name' => $title, 'hid' => $hid]);
        $renderer->finishSectionEdit();
        return true;
    }
}
