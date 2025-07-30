<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * Include plugin (footer component)
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Michael Klier <chi@chimeric.de>
 */
class syntax_plugin_include_footer extends SyntaxPlugin
{
    /** @inheritdoc */
    public function getType()
    {
        return 'formatting';
    }

    /** @inheritdoc */
    public function getSort()
    {
        return 300;
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
     *
     * @inheritdoc
     */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode != 'xhtml') return false;
        /** @var Doku_Renderer_xhtml $renderer */

        [$page, $sect, $sect_title, $flags, $redirect_id, $footer_lvl] = $data;

        $renderer->doc .= $this->htmlFooter($page, $sect, $sect_title, $flags, $footer_lvl, $renderer);
        return true;
    }

    /**
     * Returns the meta line below the included page
     * @param $renderer Doku_Renderer_xhtml The (xhtml) renderer
     * @return string The HTML code of the footer
     */
    protected function htmlFooter($page, $sect, $sect_title, $flags, $footer_lvl, $renderer)
    {
        global $conf;

        if (!$flags['footer']) return '';

        $meta = p_get_metadata($page);
        $exists = page_exists($page);
        $xhtml = [];

        // permalink
        if ($flags['permalink']) {
            $class = ($exists ? 'wikilink1' : 'wikilink2');
            $url = ($sect) ? wl($page) . '#' . $sect : wl($page);
            $name = ($sect) ? $sect_title : $page;
            $title = ($sect) ? $page . '#' . $sect : $page;
            if (!$title) $title = str_replace('_', ' ', noNS($page));
            $link = [
                'url' => $url,
                'title' => $title,
                'name' => $name,
                'target' => $conf['target']['wiki'],
                'class' => $class . ' permalink',
                'more' => 'rel="bookmark"'
            ];
            $xhtml[] = $renderer->_formatLink($link);
        }

        // date
        if ($flags['date'] && $exists) {
            $date = $meta['date']['created'];
            if ($date) {
                $xhtml[] = '<abbr class="published" title="' . dformat($date, '%Y-%m-%dT%H:%M:%SZ') . '">'
                    . dformat($date)
                    . '</abbr>';
            }
        }

        // modified date
        if ($flags['mdate'] && $exists) {
            $mdate = $meta['date']['modified'];
            if ($mdate) {
                $xhtml[] = '<abbr class="published" title="' . dformat($mdate, '%Y-%m-%dT%H:%M:%SZ') . '">'
                    . dformat($mdate)
                    . '</abbr>';
            }
        }

        // author
        if ($flags['user'] && $exists) {
            $author = $meta['user'];
            if ($author) {
                $xhtml[] = '<span class="vcard author">' . userlink($author) . '</span>';
            }
        }

        // comments - let Discussion Plugin do the work for us
        if (
            empty($sect) &&
            $flags['comments'] &&
            (!plugin_isdisabled('discussion')) &&
            ($discussion = plugin_load('helper', 'discussion'))
        ) {
            $disc = $discussion->td($page);
            if ($disc) $xhtml[] = '<span class="comment">' . $disc . '</span>';
        }

        // linkbacks - let Linkback Plugin do the work for us
        if (
            empty($sect) &&
            $flags['linkbacks'] &&
            (!plugin_isdisabled('linkback')) &&
            ($linkback = plugin_load('helper', 'linkback'))
        ) {
            $link = $linkback->td($page);
            if ($link) $xhtml[] = '<span class="linkback">' . $link . '</span>';
        }

        $xhtml = implode(DOKU_LF . DOKU_TAB . '&middot; ', $xhtml);

        // tags - let Tag Plugin do the work for us
        if (
            empty($sect) &&
            $flags['tags'] &&
            (!plugin_isdisabled('tag')) &&
            ($tag = plugin_load('helper', 'tag'))
        ) {
            $tags = $tag->td($page);
            if ($tags) {
                $xhtml .= '<div class="tags"><span>' . DOKU_LF
                    . DOKU_TAB . $tags . DOKU_LF
                    . DOKU_TAB . '</span></div>' . DOKU_LF;
            }
        }

        if (!$xhtml) $xhtml = '&nbsp;';
        $class = 'inclmeta';
        $class .= ' level' . $footer_lvl;
        return '<div class="' . $class . '">' . DOKU_LF . DOKU_TAB . $xhtml . DOKU_LF . '</div>' . DOKU_LF;
    }
}
