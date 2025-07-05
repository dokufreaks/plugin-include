<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;
use dokuwiki\Form\Form;
use dokuwiki\Logger;

/**
 * Include Plugin:  Display a wiki page within another wiki page
 *
 * Action plugin component, for cache validity determination
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>
 * @author     Michael Klier <chi@chimeric.de>
 */
class action_plugin_include extends ActionPlugin
{
    /* @var helper_plugin_include $helper */
    public $helper;

    /**
     * Constructor
     *
     * Initializes the helper
     */
    public function __construct()
    {
        $this->helper = plugin_load('helper', 'include');
    }

    /** @inheritdoc */
    public function register(EventHandler $controller)
    {
        /* @var Doku_event_handler $controller */
        $controller->register_hook('INDEXER_PAGE_ADD', 'BEFORE', $this, 'handleIndexer');
        $controller->register_hook('INDEXER_VERSION_GET', 'BEFORE', $this, 'handleIndexerVersion');
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handleCachePrepare');
        $controller->register_hook('FORM_EDIT_OUTPUT', 'BEFORE', $this, 'handleForm');
        $controller->register_hook('FORM_CONFLICT_OUTPUT', 'BEFORE', $this, 'handleForm');
        $controller->register_hook('FORM_DRAFT_OUTPUT', 'BEFORE', $this, 'handleForm');
        $controller->register_hook('ACTION_SHOW_REDIRECT', 'BEFORE', $this, 'handleRedirect');
        $controller->register_hook('PARSER_HANDLER_DONE', 'BEFORE', $this, 'handleParser');
        $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, 'handleMetadata');
        $controller->register_hook('HTML_SECEDIT_BUTTON', 'BEFORE', $this, 'handleSeceditButton');
        $controller->register_hook('PLUGIN_MOVE_HANDLERS_REGISTER', 'BEFORE', $this, 'handleMoveRegister');
    }

    /**
     * Add a version string to the index so it is rebuilt
     * whenever the handler is updated or the safeindex setting is changed
     */
    public function handleIndexerVersion(Event $event, $param)
    {
        $event->data['plugin_include'] = '0.1.safeindex=' . $this->getConf('safeindex');
    }

    /**
     * Handles the INDEXER_PAGE_ADD event
     *
     * prevents indexing of metadata from included pages that aren't public if enabled
     *
     * @param Event $event the event object
     * @param array $params optional parameters (unused)
     */
    public function handleIndexer(Event $event, $params)
    {
        global $USERINFO;

        // check if the feature is enabled at all
        if (!$this->getConf('safeindex')) return;

        // is there a user logged in at all? If not everything is fine already
        if (is_null($USERINFO) && !isset($_SERVER['REMOTE_USER'])) return;

        // get the include metadata in order to see which pages were included
        $inclmeta = p_get_metadata($event->data['page'], 'plugin_include', METADATA_RENDER_UNLIMITED);
        $all_public = true; // are all included pages public?
        // check if the current metadata indicates that non-public pages were included
        if ($inclmeta !== null && isset($inclmeta['pages'])) {
            foreach ($inclmeta['pages'] as $page) {
                if (auth_aclcheck($page['id'], '', []) < AUTH_READ) { // is $page public?
                    $all_public = false;
                    break;
                }
            }
        }

        if (!$all_public) { // there were non-public pages included - action required!
            // backup the user information
            $userinfo_backup = $USERINFO;
            $remote_user = $_SERVER['REMOTE_USER'];
            // unset user information - temporary logoff!
            $USERINFO = null;
            unset($_SERVER['REMOTE_USER']);

            // metadata is only rendered once for a page in one request - thus we need to render manually.
            $meta = p_read_metadata($event->data['page']); // load the original metdata
            $meta = p_render_metadata($event->data['page'], $meta); // render the metadata
            p_save_metadata($event->data['page'], $meta); // save the metadata so other event handlers get it, too

            $meta = $meta['current']; // we are only interested in current metadata.

            // check if the tag plugin handler has already been called before the include plugin
            $tag_called = isset($event->data['metadata']['subject']);

            // Reset the metadata in the renderer.
            // This removes data from all other event handlers, but we need to be on the safe side here.
            $event->data['metadata'] = ['title' => $meta['title']];

            // restore the relation references metadata
            if (isset($meta['relation']['references'])) {
                $event->data['metadata']['relation_references'] = array_keys($meta['relation']['references']);
            } else {
                $event->data['metadata']['relation_references'] = [];
            }

            // restore the tag metadata if the tag plugin handler has been called before the include plugin handler.
            if ($tag_called) {
                $tag_helper = $this->loadHelper('tag', false);
                if ($tag_helper) {
                    if (isset($meta['subject'])) {
                        $event->data['metadata']['subject'] = $tag_helper->_cleanTagList($meta['subject']);
                    } else {
                        $event->data['metadata']['subject'] = [];
                    }
                }
            }

            // restore user information
            $USERINFO = $userinfo_backup;
            $_SERVER['REMOTE_USER'] = $remote_user;
        }
    }

    /**
     * Used for debugging purposes only
     */
    public function handleMetadata(Event $event, $param)
    {
        global $conf;
        if ($conf['allowdebug'] && $this->getConf('debugoutput')) {
            dbglog('---- PLUGIN INCLUDE META DATA START ----');
            dbglog($event->data);
            dbglog('---- PLUGIN INCLUDE META DATA END ----');
        }
    }

    /**
     * Supplies the current section level to the include syntax plugin
     *
     * @author Michael Klier <chi@chimeric.de>
     * @author Michael Hamann <michael@content-space.de>
     */
    public function handleParser(Event $event, $param)
    {
        global $ID;

        $level = 0;
        $ins =& $event->data->calls;
        $num = count($ins);
        for ($i = 0; $i < $num; $i++) {
            switch ($ins[$i][0]) {
                case 'plugin':
                    if ($ins[$i][1][0] === 'include_include') {
                        $ins[$i][1][1][4] = $level;
                    }
                    break;
                case 'section_open':
                    $level = $ins[$i][1][0];
                    break;
            }
        }
    }

    /**
     * Add a hidden input to the form to preserve the redirect_id
     */
    public function handleForm(Event $event, $param)
    {
        if (!array_key_exists('redirect_id', $_REQUEST)) return;

        if (is_a($event->data, Form::class)) {
            $event->data->setHiddenField('redirect_id', cleanID($_REQUEST['redirect_id']));
        } else {
            // todo remove when old FORM events are no longer supported
            $event->data->addHidden('redirect_id', cleanID($_REQUEST['redirect_id']));
        }
    }

    /**
     * Modify the data for the redirect when there is a redirect_id set
     */
    public function handleRedirect(Event $event, $param)
    {
        if (array_key_exists('redirect_id', $_REQUEST)) {
            // Render metadata when this is an older DokuWiki version where
            // metadata is not automatically re-rendered as the page has probably
            // been changed but is not directly displayed
            $versionData = getVersionData();
            if ($versionData['date'] < '2010-11-23') {
                p_set_metadata($event->data['id'], [], true);
            }
            $event->data['id'] = cleanID($_REQUEST['redirect_id']);
            $event->data['title'] = '';
        }
    }

    /**
     * prepare the cache object for default _useCache action
     */
    public function handleCachePrepare(Event $event, $param)
    {
        /* @var cache_renderer $cache */
        $cache =& $event->data;

        if (!isset($cache->page)) return;
        if (!isset($cache->mode) || $cache->mode == 'i') return;

        $depends = p_get_metadata($cache->page, 'plugin_include');

        if ($this->getConf('debugoutput')) {
            Logger::debug('include plugin: cache depends for ' . $cache->page, $depends);
        }

        if (!is_array($depends)) return; // nothing to do for us

        if (
            !is_array($depends['pages']) ||
            !is_array($depends['instructions']) ||
            $depends['pages'] != $this->helper->getIncludedPagesFromMetaInstructions($depends['instructions']) ||
            // the include_content url parameter may change the behavior for included pages
            $depends['include_content'] != isset($_REQUEST['include_content'])
        ) {
            $cache->depends['purge'] = true; // included pages changed or old metadata - request purge.
            if ($this->getConf('debugoutput')) {
                Logger::debug('include plugin: cache purge for ' . $cache->page, [
                    'meta-pages' => $depends['pages'],
                    'inst-pages' => $this->helper->getIncludedPagesFromMetaInstructions($depends['instructions']),
                ]);
            }
        } else {
            // add plugin.info.txt to depends for nicer upgrades
            $cache->depends['files'][] = __DIR__ . '/plugin.info.txt';
            foreach ($depends['pages'] as $page) {
                if (!$page['exists']) continue;
                $file = wikiFN($page['id']);
                if (!in_array($file, $cache->depends['files'])) {
                    $cache->depends['files'][] = $file;
                }
            }
        }
    }

    /**
     * Handle special section edit buttons for the include plugin to get the current page
     * and replace normal section edit buttons when the current page is different from the
     * global $ID.
     */
    public function handleSeceditButton(Event $event, $params)
    {
        // stack of included pages in the form ('id' => page, 'rev' => modification time, 'writable' => bool)
        static $page_stack = [];

        global $ID, $lang;

        $data = $event->data;

        if ($data['target'] == 'plugin_include_start' || $data['target'] == 'plugin_include_start_noredirect') {
            // handle the "section edits" added by the include plugin
            $fn = wikiFN($data['name']);
            $perm = auth_quickaclcheck($data['name']);
            array_unshift(
                $page_stack,
                [
                    'id' => $data['name'],
                    'rev' => @filemtime($fn),
                    'writable' =>
                        (page_exists($data['name'])
                            ? (is_writable($fn) && $perm >= AUTH_EDIT)
                            : $perm >= AUTH_CREATE),
                    'redirect' => ($data['target'] == 'plugin_include_start')]
            );
        } elseif ($data['target'] == 'plugin_include_end') {
            array_shift($page_stack);
        } elseif ($data['target'] == 'plugin_include_editbtn') {
            if ($page_stack[0]['writable']) {
                $params = ['do' => 'edit', 'id' => $page_stack[0]['id']];
                if ($page_stack[0]['redirect']) {
                    $params['redirect_id'] = $ID;
                    $params['hid'] = $data['hid'];
                }
                $event->result = '<div class="secedit">' . DOKU_LF .
                    html_btn(
                        'incledit',
                        $page_stack[0]['id'],
                        '',
                        $params,
                        'post',
                        $data['name'],
                        $lang['btn_secedit'] . ' (' . $page_stack[0]['id'] . ')'
                    ) .
                    '</div>' . DOKU_LF;
            }
        } elseif (!empty($page_stack)) {
            // Special handling for the edittable plugin
            if ($data['target'] == 'table' && !plugin_isdisabled('edittable')) {
                /* @var action_plugin_edittable_editor $edittable */
                $edittable = plugin_load('action', 'edittable_editor');
                if (is_null($edittable))
                    $edittable = plugin_load('action', 'edittable');
                $data['name'] = $edittable->getLang('secedit_name');
            }

            if ($page_stack[0]['writable'] && isset($data['name']) && $data['name'] !== '') {
                $name = $data['name'];
                unset($data['name']);

                $secid = $data['secid'];
                unset($data['secid']);

                if ($page_stack[0]['redirect'])
                    $data['redirect_id'] = $ID;

                $event->result = "<div class='secedit editbutton_" . $data['target'] .
                    " editbutton_" . $secid . "'>" .
                    html_btn(
                        'secedit',
                        $page_stack[0]['id'],
                        '',
                        array_merge(
                            ['do' => 'edit', 'rev' => $page_stack[0]['rev'], 'summary' => '[' . $name . '] '],
                            $data
                        ),
                        'post',
                        $name
                    ) . '</div>';
            } else {
                $event->result = '';
            }
        } else {
            return; // return so the event won't be stopped
        }

        $event->preventDefault();
        $event->stopPropagation();
    }

    public function handleMoveRegister(Event $event, $params)
    {
        $event->data['handlers']['include_include'] = [$this, 'rewriteInclude'];
    }

    public function rewriteInclude($match, $pos, $state, $plugin, helper_plugin_move_handler $handler)
    {
        $syntax = substr($match, 2, -2); // strip markup
        $replacers = explode('|', $syntax);
        $syntax = array_shift($replacers);
        [$syntax, $flags] = array_pad(explode('&', $syntax, 2), 2, "");

        // break the pattern up into its parts
        [$mode, $page, $sect] = array_pad(preg_split('/>|#/u', $syntax, 3), 3, "");

        $newpage = $handler->resolveMoves($page, 'page');
        $newpage = $handler->relativeLink($page, $newpage, 'page');

        if ($newpage == $page) {
            return $match;
        } else {
            $result = '{{' . $mode . '>' . $newpage;
            if ($sect) $result .= '#' . $sect;
            if ($flags) $result .= '&' . $flags;
            if ($replacers) $result .= '|' . $replacers;
            $result .= '}}';
            return $result;
        }
    }
}
