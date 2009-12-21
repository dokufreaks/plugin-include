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
 
    var $supportedModes = array('xhtml', 'i');
    var $helper = null;

    function action_plugin_include() {
        $this->helper = plugin_load('helper', 'include');
    }
 
    /**
     * return some info
     */
    function getInfo() {
      return array(
        'author' => 'Gina Häußge, Michael Klier, Christopher Smith',
        'email'  => 'dokuwiki@chimeric.de',
        'date'   => @file_get_contents(DOKU_PLUGIN . 'blog/VERSION'),
        'name'   => 'Include Plugin',
        'desc'   => 'Improved cache handling for included pages and redirect-handling',
        'url'    => 'http://dokuwiki.org/plugin:include',
      );
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
    }

    /**
     * Supplies the current section level to the include syntax plugin
     *
     * @author Michael Klier <chi@chimeric.de>
     */
    function handle_parser(&$event, $param) {
        global $ID;

        // check for stored toplevel ID in helper plugin
        // if it's missing lets see if we have to do anything at all
        if(!isset($this->helper->toplevel_id)) {
            $ins =& $event->data->calls;
            $num = count($ins);
            for($i=0; $i<$num; $i++) {
                if(($ins[$i][0] == 'plugin') && ($ins[$i][1][0] == 'include_include')) {
                    if(!isset($this->helper->toplevel_id)) $this->helper->toplevel_id = $ID;
                    $this->helper->parse_instructions($ID, $ins);
                }
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
        $event->data['id'] = cleanID($_REQUEST['redirect_id']);
        $event->data['title'] = '';
      }
    }

    /**
     * prepare the cache object for default _useCache action
     */
    function _cache_prepare(&$event, $param) {
        global $ID;
        global $INFO;
        global $conf;

        $cache =& $event->data;

        // we're only interested in instructions of the current page
        // without the ID check we'd get the cache objects for included pages as well
        if(!isset($cache->page) && ($cache->page != $ID)) return;
        if(!isset($cache->mode) || !in_array($cache->mode, $this->supportedModes)) return;

        if(!empty($INFO['userinfo'])) {
            $include_key = $INFO['userinfo']['name'] . '|' . implode('|', $INFO['userinfo']['grps']);
        } else {
            $include_key = '@ALL';
        }

        $depends = p_get_metadata($ID, 'plugin_include');
        if(is_array($depends)) {
            $pages = array();
            if(!isset($depends['keys'][$include_key])) {
                $cache->depends['purge'] = true; // include key not set - request purge 
            } else {
                $pages = $depends['pages'];
            }
        } else {
            // nothing to do for us
            return;
        }

        // add plugin VERSION file to depends for nicer upgrades
        $cache->depends['files'][] = dirname(__FILE__) . '/VERSION';

        $key = ''; 
        foreach($pages as $page) {
            $page = $this->helper->_apply_macro($page);
            if(strpos($page,'/') || cleanID($page) != $page) {
                continue;
            } else {
                $file = wikiFN($page);
                if(!in_array($cache->depends['files'], array($file)) && @file_exists($file)) {
                    $cache->depends['files'][] = $file;
                    $key .= '#' . $page . '|ACL' . auth_quickaclcheck($page);
                }
            }
        }

        // empty $key implies no includes, so nothing to do
        if(empty($key)) return;

        // mark the cache as being modified by the include plugin
        $cache->include = true;

        // set new cache key & cache name
        // now also dependent on included page ids and their ACL_READ status
        $cache->key .= $key;
        $cache->cache = getCacheName($cache->key, $cache->ext);
    }
 
}
//vim:ts=4:sw=4:et:enc=utf-8: 
