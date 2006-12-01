<?php 
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
 */ 
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/'); 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/'); 
require_once(DOKU_PLUGIN.'syntax.php'); 
  
/** 
 * All DokuWiki plugins to extend the parser/rendering mechanism 
 * need to inherit from this class 
 */ 
class syntax_plugin_include extends DokuWiki_Syntax_Plugin { 
 
  /** 
   * return some info 
   */ 
  function getInfo(){ 
    return array( 
      'author' => 'Esther Brunner', 
      'email'  => 'wikidesign@gmail.com', 
      'date'   => '2006-12-01', 
      'name'   => 'Include Plugin', 
      'desc'   => 'Displays a wiki page (or a section thereof) within another', 
      'url'    => 'http://www.wikidesign.ch/en/plugin/include/start', 
    ); 
  } 
 
  function getType(){ return 'substition'; } 
  function getSort(){ return 303; } 
  function getPType(){ return 'block'; } 
  function connectTo($mode) {  
    $this->Lexer->addSpecialPattern("{{page>.+?}}",$mode,'plugin_include');  
    $this->Lexer->addSpecialPattern("{{section>.+?}}",$mode,'plugin_include'); 
  } 
 
  /** 
   * Handle the match 
   */ 
  function handle($match, $state, $pos, &$handler){ 
 
      // break the pattern up into its constituent parts 
      list($include, $id, $section) = preg_split('/>|#/u',substr($match,2,-2),3); 
      return array($include, $id, cleanID($section)); 
  }     
 
  /** 
   * Create output 
   */ 
  function render($mode, &$renderer, $data) {
    global $ID;
 
    list($type, $id, $section) = $data; 
 
    $id = $this->_applyMacro($id); 
    resolve_pageid(getNS($ID), $id, $exists); // resolve shortcuts
    
    // load the include class
    require_once(DOKU_PLUGIN.'include/inc/include.php');
    $include = new plugin_class_include;
    
    $include->type = $type;
    $include->page = array('id' => $id, 'section' => $section, 'exists' => $exists);
    if ($include->_inFilechain()) return false; // prevent recursion
    
    if ($mode == 'xhtml'){
 
      // check for permission 
      $include->page['perm'] = auth_quickaclcheck($id); 
      if ($include->page['perm'] < AUTH_READ) return true; 
         
      // prevent caching to ensure the included page is always fresh 
      $renderer->info['cache'] = FALSE; 
    
      // current section level
      $clevel = 0;
      preg_match_all('|<div class="level(\d)">|i', $renderer->doc, $matches, PREG_SET_ORDER);
      $n = count($matches)-1;
      if ($n > -1) $clevel = $matches[$n][1];
      $include->clevel = $clevel
      
      // close current section
      if ($clevel && ($type == 'section')) $renderer->doc .= '</div>';
      
      // include the page now
      $renderer->doc .= $include->_include($renderer);
      
      // resume current section
      if ($clevel && ($type == 'section'))
        $renderer->doc .= '<div class="level'.$clevel.'">';
      
      return $ok;
       
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      $renderer->meta['relation']['haspart'][$id] = $exists;

      return true;
    }
 
    return false;  
  }

/* ---------- Util Functions ---------- */
    
  /**
   * Makes user or date dependent includes possible
   */
  function _applyMacro($id){
    global $INFO;
    
    list($group, $rest) = explode(',', $INFO['username']['grps']);
 
    $replace = array( 
      '@USER@'  => cleanID($_SERVER['REMOTE_USER']), 
      '@NAME@'  => cleanID($INFO['userinfo']['name']),
      '@GROUP@' => cleanID($group),
      '@YEAR@'  => date('Y'), 
      '@MONTH@' => date('m'), 
      '@DAY@'   => date('d'), 
    ); 
    return str_replace(array_keys($replace), array_values($replace), $id); 
  }
          
}

//Setup VIM: ex: et ts=4 enc=utf-8 :