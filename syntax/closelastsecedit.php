<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * Include plugin (close last section edit)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Michael Hamann <michael@content-space.de>
 */
class syntax_plugin_include_closelastsecedit extends SyntaxPlugin
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
     * Finishes the last open section edit
     *
     * @inheritdoc
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode != 'xhtml') return false;

        /** @var Doku_Renderer_xhtml $renderer */
        [$endpos] = $data;
        $renderer->finishSectionEdit($endpos);
        return true;
    }
}
