<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * Include plugin (editbtn header component)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Michael Hamann <michael@content-space.de>
 */
class syntax_plugin_include_readmore extends SyntaxPlugin
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
     * Renders the readmore link for the included page.
     *
     * @inheritdoc
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        [$page] = $data;

        if ($mode == 'xhtml') {
            $renderer->doc .= '<p class="include_readmore">';
        } else {
            $renderer->p_open();
        }

        $renderer->internallink($page, $this->getLang('readmore'));

        if ($mode == 'xhtml') {
            $renderer->doc .= '</p>';
        } else {
            $renderer->p_close();
        }

        return true;
    }
}
