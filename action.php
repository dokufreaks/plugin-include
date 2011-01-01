<?php
/**
 * Include Plugin:  Display a wiki page within another wiki page
 *
 * Action plugin component, for cache validity determination
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>  
 * @author     Michael Klier <chi@chimeric.de>
 */
if(!defined('DOKU_INC')) die();  // no Dokuwiki, no go
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class action_plugin_include extends DokuWiki_Action_Plugin {
 
    var $supportedModes = array('xhtml', 'metadata');
    var $helper = null;

    function action_plugin_include() {
        $this->helper = plugin_load('helper', 'include');
    }
 
    /**
     * plugin should use this method to register its handlers with the dokuwiki's event controller
     */
    function register(&$controller) {
      $controller->register_hook('PARSER_CACHE_USE','BEFORE', $this, '_cache_prepare');
      $controller->register_hook('HTML_EDITFORM_OUTPUT', 'BEFORE', $this, 'handle_form');
      $controller->register_hook('HTML_CONFLICTFORM_OUTPUT', 'BEFORE', $this, 'handle_form');
      $controller->register_hook('HTML_DRAFTFORM_OUTPUT', 'BEFORE', $this, 'handle_form');
      $controller->register_hook('ACTION_SHOW_REDIRECT', 'BEFORE', $this, 'handle_redirect');
      $controller->register_hook('PARSER_HANDLER_DONE', 'BEFORE', $this, 'handle_parser');
      $controller->register_hook('PARSER_METADATA_RENDER', 'AFTER', $this, 'handle_metadata');
      $controller->register_hook('HTML_SECEDIT_BUTTON', 'BEFORE', $this, 'handle_secedit_button');
    }

    /**
     * Used for debugging purposes only
     */
    function handle_metadata(&$event, $param) {
        global $conf;
        if($conf['allowdebug']) {
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
    function handle_parser(&$event, $param) {
        global $ID;

        $level = 0;
        $ins =& $event->data->calls;
        $num = count($ins);
        for($i=0; $i<$num; $i++) {
            switch($ins[$i][0]) {
            case 'plugin':
                switch($ins[$i][1][0]) {
                case 'include_include':
                    $ins[$i][1][1][] = $level;
                    break;
                    /* FIXME: this doesn't work anymore that way with the new structure
                    // some plugins already close open sections
                    // so we need to make sure we don't close them twice
                case 'box':
                    $this->helper->sec_close = false;
                    break;
                     */
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
    function handle_form(&$event, $param) {
      if (array_key_exists('redirect_id', $_REQUEST)) {
        $event->data->addHidden('redirect_id', cleanID($_REQUEST['redirect_id']));
      }
    }

    /**
     * Modify the data for the redirect when there is a redirect_id set
     */
    function handle_redirect(&$event, $param) {
      if (array_key_exists('redirect_id', $_REQUEST)) {
        // Render metadata when this is an older DokuWiki version where
        // metadata is not automatically re-rendered as the page has probably
        // been changed but is not directly displayed
        $versionData = getVersionData();
        if ($versionData['date'] < '2010-11-23') {
            p_set_metadata($event->data['id'], array(), true);
        }
        $event->data['id'] = cleanID($_REQUEST['redirect_id']);
        $event->data['title'] = '';
      }
    }

    /**
     * prepare the cache object for default _useCache action
     */
    function _cache_prepare(&$event, $param) {
        global $conf;

        $cache =& $event->data;

        if(!isset($cache->page)) return;
        if(!isset($cache->mode) || !in_array($cache->mode, $this->supportedModes)) return;

        $depends = p_get_metadata($cache->page, 'plugin_include');
        
        if($conf['allowdebug']) {
            dbglog('---- PLUGIN INCLUDE CACHE DEPENDS START ----');
            dbglog($depends);
            dbglog('---- PLUGIN INCLUDE CACHE DEPENDS END ----');
        }

        if (!is_array($depends)) return; // nothing to do for us

        if (!is_array($depends['pages']) ||
            !is_array($depends['instructions']) ||
            $depends['pages'] != $this->helper->_get_included_pages_from_meta_instructions($depends['instructions']) ||
            // the include_content url parameter may change the behavior for included pages
            $depends['include_content'] != isset($_REQUEST['include_content'])) {

            $cache->depends['purge'] = true; // included pages changed or old metadata - request purge.
            if($conf['allowdebug']) {
                dbglog('---- PLUGIN INCLUDE: REQUESTING CACHE PURGE ----');
                dbglog('---- PLUGIN INCLUDE CACHE PAGES FROM META START ----');
                dbglog($depends['pages']);
                dbglog('---- PLUGIN INCLUDE CACHE PAGES FROM META END ----');
                dbglog('---- PLUGIN INCLUDE CACHE PAGES FROM META_INSTRUCTIONS START ----');
                dbglog($this->helper->_get_included_pages_from_meta_instructions($depends['instructions']));
                dbglog('---- PLUGIN INCLUDE CACHE PAGES FROM META_INSTRUCTIONS END ----');

            }
        } else {
            // add plugin.info.txt to depends for nicer upgrades
            $cache->depends['files'][] = dirname(__FILE__) . '/plugin.info.txt';
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
    function handle_secedit_button(&$event, $params) {
        // stack of included pages in the form ('id' => page, 'rev' => modification time, 'writable' => bool)
        static $page_stack = array();

        global $ID;

        $data = $event->data;

        if ($data['target'] == 'plugin_include_start' || $data['target'] == 'plugin_include_start_noredirect') {
            // handle the "section edits" added by the include plugin
            $fn = wikiFN($data['name']);
            array_unshift($page_stack, array(
                'id' => $data['name'],
                'rev' => @filemtime($fn),
                'writable' => (is_writable($fn) && auth_quickaclcheck($data['name']) >= AUTH_EDIT),
                'redirect' => ($data['target'] == 'plugin_include_start'),
            ));
        } elseif ($data['target'] == 'plugin_include_end') {
            array_shift($page_stack);
        } elseif (!empty($page_stack)) {
            if ($page_stack[0]['writable']) {
                $name = $data['name'];
                unset($data['name']);

                $secid = $data['secid'];
                unset($data['secid']);

                if ($page_stack[0]['redirect'])
                    $data['redirect_id'] = $ID;

                $event->result = "<div class='secedit editbutton_" . $data['target'] .
                    " editbutton_" . $secid . "'>" .
                    html_btn('secedit', $page_stack[0]['id'], '',
                        array_merge(array('do'  => 'edit',
                        'rev' => $page_stack[0]['rev'],
                        'summary' => '['.$name.'] '), $data),
                        'post', $name) . '</div>';
            } else {
                $event->result = '';
            }
        } else {
            return; // return so the event won't be stopped
        }

        $event->preventDefault();
        $event->stopPropagation();
    }
}
// vim:ts=4:sw=4:et:
