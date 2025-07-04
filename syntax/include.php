<?php

use dokuwiki\Extension\SyntaxPlugin;

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
class syntax_plugin_include_include extends SyntaxPlugin
{
    /** @var $helper helper_plugin_include */
    public $helper;

    /** @inheritdoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritdoc */
    public function getSort()
    {
        return 303;
    }

    /** @inheritdoc */
    public function getPType()
    {
        return 'block';
    }

    /** @inheritdoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern("{{page>.+?}}", $mode, 'plugin_include_include');
        $this->Lexer->addSpecialPattern("{{section>.+?}}", $mode, 'plugin_include_include');
        $this->Lexer->addSpecialPattern("{{namespace>.+?}}", $mode, 'plugin_include_include');
        $this->Lexer->addSpecialPattern("{{tagtopic>.+?}}", $mode, 'plugin_include_include');
    }

    /** @inheritdoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {

        $match = substr($match, 2, -2); // strip markup
        [$match, $flags] = array_pad(explode('&', $match, 2), 2, '');

        // break the pattern up into its parts
        [$mode, $page, $sect] = array_pad(preg_split('/>|#/u', $match, 3), 3, null);
        $check = false;
        if (isset($sect)) $sect = sectionID($sect, $check);
        $level = null;
        return [$mode, $page, $sect, explode('&', $flags), $level, $pos];
    }

    /** @inheritdoc */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        global $ID;

        // static stack that records all ancestors of the child pages
        static $page_stack = [];

        // when there is no id just assume the global $ID is the current id
        if (empty($page_stack)) $page_stack[] = $ID;

        $parent_id = $page_stack[count($page_stack) - 1];
        $root_id = $page_stack[0];

        [$mode, $page, $sect, $flags, $level, $pos] = $data;

        if (!$this->helper) {
            $this->helper = plugin_load('helper', 'include');
        }
        $flags = $this->helper->get_flags($flags);

        $pages = $this->helper->getIncludedPages($mode, $page, $sect, $parent_id, $flags);

        if ($format == 'metadata') {
            /** @var Doku_Renderer_metadata $renderer */

            // remove old persistent metadata of previous versions of the include plugin
            if (isset($renderer->persistent['plugin_include'])) {
                unset($renderer->persistent['plugin_include']);
                unset($renderer->meta['plugin_include']);
            }

            $renderer->meta['plugin_include']['instructions'][] = [
                'mode' => $mode,
                'page' => $page,
                'sect' => $sect,
                'parent_id' => $parent_id,
                'flags' => $flags
            ];
            if (!isset($renderer->meta['plugin_include']['pages'])) {
                $renderer->meta['plugin_include']['pages'] = []; // add an array for array_merge
            }
            $renderer->meta['plugin_include']['pages'] = array_merge(
                $renderer->meta['plugin_include']['pages'],
                $pages
            );
            $renderer->meta['plugin_include']['include_content'] = isset($_REQUEST['include_content']);
        }

        $secids = [];
        if ($format == 'xhtml' || $format == 'odt') {
            $secids = p_get_metadata($ID, 'plugin_include secids');
        }

        foreach ($pages as $page) {
            extract($page);
            $id = $page['id'];
            $exists = $page['exists'];

            if (in_array($id, $page_stack)) continue;
            $page_stack[] = $id;

            // add references for backlink
            if ($format == 'metadata') {
                $renderer->meta['relation']['references'][$id] = $exists;
                $renderer->meta['relation']['haspart'][$id] = $exists;
                if (
                    !$sect &&
                    !$flags['firstsec'] &&
                    !$flags['linkonly'] &&
                    !isset($renderer->meta['plugin_include']['secids'][$id])
                ) {
                    $renderer->meta['plugin_include']['secids'][$id] = [
                        'hid' => 'plugin_include__' . str_replace(':', '__', $id),
                        'pos' => $pos
                    ];
                }
            }

            if (isset($secids[$id]) && $pos === $secids[$id]['pos']) {
                $flags['include_secid'] = $secids[$id]['hid'];
            } else {
                unset($flags['include_secid']);
            }

            $instructions = $this->helper->getInstructions(
                $id,
                $sect,
                $mode,
                $level,
                $flags,
                $root_id,
                $secids
            );

            if (!$flags['editbtn']) {
                global $conf;
                $maxseclevel_org = $conf['maxseclevel'];
                $conf['maxseclevel'] = 0;
            }
            $renderer->nest($instructions);
            if (isset($maxseclevel_org)) {
                $conf['maxseclevel'] = $maxseclevel_org;
                unset($maxseclevel_org);
            }

            array_pop($page_stack);
        }

        // When all includes have been handled remove the current id
        // in order to allow the rendering of other pages
        if (count($page_stack) == 1) array_pop($page_stack);

        return true;
    }
}
