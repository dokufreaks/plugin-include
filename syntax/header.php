<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * Include plugin (permalink header component)
 *
 * Provides a header instruction which renders a permalink to the included page
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Gina Haeussge <osd@foosel.net>
 * @author  Michael Klier <chi@chimeric.de>
 */
class syntax_plugin_include_header extends SyntaxPlugin
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
     * Renders a permalink header.
     *
     * Code heavily copied from the header renderer from inc/parser/xhtml.php, just
     * added an href parameter to the anchor tag linking to the wikilink.
     * @inheritdoc
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        global $conf;

        [$headline, $lvl, $pos, $page, $sect, $flags] = $data;

        if ($mode != 'xhtml') {
            // just output a standard header for all non-xhtml modes
            $renderer->header($headline, $lvl, $pos);
            return true;
        }

        /** @var Doku_Renderer_xhtml $renderer */
        $hid = $renderer->_headerToLink($headline, true);
        $renderer->toc_additem($hid, $headline, $lvl);
        $url = ($sect) ? wl($page) . '#' . $sect : wl($page);
        $renderer->doc .= '<h' . $lvl;
        $classes = [];
        if ($flags['taglogos']) {
            $tag = $this->getFirsttag($page);
            if ($tag) {
                $classes[] = 'include_firsttag__' . $tag;
            }
        }
        // the include header instruction is always at the beginning of the first section edit inside the include
        // wrap so there is no need to close a previous section edit.
        if ($lvl <= $conf['maxseclevel']) {
            $classes[] = $renderer->startSectionEdit($pos, ['target' => 'section', 'name' => $headline, 'hid' => $hid]);
        }
        if ($classes) {
            $renderer->doc .= ' class="' . implode(' ', $classes) . '"';
        }
        $headline = $renderer->_xmlEntities($headline);
        $renderer->doc .= ' id="' . $hid . '"><a href="' . $url . '" title="' . $headline . '">';
        $renderer->doc .= $headline;
        $renderer->doc .= '</a></h' . $lvl . '>';
        return true;
    }

    /**
     * Optionally add a CSS class for the first tag
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    protected function getFirsttag($page)
    {
        if (plugin_isdisabled('tag') || (!plugin_load('helper', 'tag'))) {
            return false;
        }
        $subject = p_get_metadata($page, 'subject');
        if (is_array($subject)) {
            $tag = $subject[0];
        } else {
            [$tag, $rest] = explode(' ', $subject, 2);
        }
        if ($tag) {
            return $tag;
        } else {
            return false;
        }
    }
}
