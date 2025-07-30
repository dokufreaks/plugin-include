<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * Include plugin sort order tag, idea and parts of the code copied from the indexmenu plugin.
 *
 * @license     GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author      Samuele Tognini <samuele@netsons.org>
 * @author      Michael Hamann <michael@content-space.de>
 */
class syntax_plugin_include_sorttag extends SyntaxPlugin
{
    /** @inheritdoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritdoc */
    public function getPType()
    {
        return 'block';
    }

    /** @inheritdoc */
    public function getSort()
    {
        return 139;
    }

    /** @inheritdoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('{{include_n>.+?}}', $mode, 'plugin_include_sorttag');
    }

    /** @inheritdoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $match = substr($match, 12, -2);
        return [$match];
    }

    /** @inheritdoc */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode === 'metadata') {
            /** @var Doku_Renderer_metadata $renderer */
            $renderer->meta['include_n'] = $data[0];
        }
    }
}
