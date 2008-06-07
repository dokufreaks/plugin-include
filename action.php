<?php
/**
 * Include Plugin:  Display a wiki page within another wiki page
 *
 * Action plugin component, for cache validity determination
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>  
 */
if(!defined('DOKU_INC')) die();  // no Dokuwiki, no go
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class action_plugin_include extends DokuWiki_Action_Plugin {
 
    var $supportedModes = array('xhtml');
 
    /**
     * return some info
     */
    function getInfo() {
      return array(
        'author' => 'Gina Häußge, Michael Klier, Christopher Smith',
        'email'  => 'dokuwiki@chimeric.de',
        'date'   => '2008-04-20',
        'name'   => 'Include Plugin',
        'desc'   => 'Improved cache handling for included pages',
        'url'    => 'http://wiki.splitbrain.org/plugin:include',
      );
    }
    
    /**
     * plugin should use this method to register its handlers with the dokuwiki's event controller
     */
    function register(&$controller) {
      $controller->register_hook('PARSER_CACHE_USE','BEFORE', $this, '_cache_prepare');
#      $controller->register_hook('PARSER_CACHE_USE','AFTER', $this, '_cache_result');    // debugging only
    }
 
    /**
     * prepare the cache object for default _useCache action
     */
    function _cache_prepare(&$event, $param) {
      $cache =& $event->data;
 
      // we're only interested in wiki pages and supported render modes
      if (!isset($cache->page)) return;
      if (!isset($cache->mode) || !in_array($cache->mode, $this->supportedModes)) return;
 
      $key = '';
      $depends = array();    
      $expire = $this->_inclusion_check($cache->page, $key, $depends);
 
#      global $debug;
#      $debug[] = compact('key','expire','depends','cache');
 
      // empty $key implies no includes, so nothing to do
      if (empty($key)) return;
 
      // mark the cache as being modified by the include plugin
      $cache->include = true;
 
      // set new cache key & cache name - now also dependent on included page ids and their ACL_READ status
      $cache->key .= $key;
      $cache->cache = getCacheName($cache->key, $cache->ext);
 
      // inclusion check was able to determine the cache must be invalid
      if ($expire) {
        $event->preventDefault();
        $event->stopPropagation();
        $event->result = false;
        return;
      }
 
      // update depends['files'] array to include all included files
      $cache->depends['files'] = !empty($cache->depends['files']) ? array_merge($cache->depends['files'], $depends) : $depends;
    }
 
    /**
     * carry out included page checks:
     * - to establish proper cache name, its dependent on the read status of included pages
     * - to establish file dependencies, the included raw wiki pages
     *
     * @param   string    $id         wiki page name
     * @param   string    $key        (reference) cache key
     * @param   array     $depends    array of include file dependencies
     *
     * @return  bool                  expire the cache
     */
    function _inclusion_check($id, &$key, &$depends) {
      $hasPart = p_get_metadata($id, 'relation haspart');
      if (empty($hasPart)) return false;
 
      $expire = false;
      foreach ($hasPart as $page => $exists) {
        // ensure its a wiki page
        if (strpos($page,'/') ||  cleanID($page) != $page) continue;
 
        // recursive includes aren't allowed and there is no need to do the same page twice
        $file = wikiFN($page);
        if (in_array($file, $depends)) continue;
 
        // file existence state is different from state recorded in metadata
        if (@file_exists($file) != $exists) {
 
          if (($acl = $this->_acl_read_check($page)) != 'NONE') { $expire = true;  }
 
        } else if ($exists) {
 
          // carry out an inclusion check on the included page, that will update $key & $depends
          if ($this->_inclusion_check($page, $key, $depends)) { $expire = true; }
          if (($acl = $this->_acl_read_check($page)) != 'NONE') { $depends[] = $file;  }          
 
        } else {
          $acl = 'NONE';
        }
        
        // add this page and acl status to the key
        $key .= '#'.$page.'|'.$acl;
      }
      
      return $expire;
    }
 
    function _acl_read_check($id) {
      return (AUTH_READ <= auth_quickaclcheck($id)) ? 'READ' : 'NONE';
    }
 
    function _cache_result(&$event, $param) {
      $cache =& $event->data;
      if (empty($cache->include)) return;
 
#      global $debug;
#      $debug['cache_result'][] = $event->result ? 'true' : 'false';
    }
 
}
//vim:ts=4:sw=4:et:enc=utf-8: 
