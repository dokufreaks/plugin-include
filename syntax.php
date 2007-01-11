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
 
  function getInfo(){ 
    return array( 
      'author' => 'Esther Brunner', 
      'email'  => 'wikidesign@gmail.com', 
      'date'   => '2007-01-11', 
      'name'   => 'Include Plugin', 
      'desc'   => 'Displays a wiki page (or a section thereof) within another', 
      'url'    => 'http://www.wikidesign.ch/en/plugin/include/start', 
    ); 
  } 
  
  function getType(){ return 'substition'; }
  function getSort(){ return 303; }
  function getPType(){ return 'block'; }
  
  function connectTo($mode){  
    $this->Lexer->addSpecialPattern("{{page>.+?}}", $mode, 'plugin_include');  
    $this->Lexer->addSpecialPattern("{{section>.+?}}", $mode, 'plugin_include'); 
  } 
 
  function handle($match, $state, $pos, &$handler){
  
    $match = substr($match, 2, -2); // strip markup
    list($match, $flags) = explode('&', $match, 2);
 
    // break the pattern up into its constituent parts 
    list($include, $id, $section) = preg_split('/>|#/u', $match, 3); 
    return array($include, $id, cleanID($section), explode('&', $flags)); 
  }     
 
  function render($mode, &$renderer, $data){
    global $ID;
 
    list($type, $id, $section, $flags) = $data; 
 
    $id = $this->_applyMacro($id); 
    resolve_pageid(getNS($ID), $id, $exists); // resolve shortcuts
    
    // check permission
    $perm = auth_quickaclcheck($id);
    if ($perm < AUTH_READ) return false;
    
    // load the include class
    $include =& plugin_load('helper', 'include');
    
    $include->setMode($type);
    $include->setFlags($flags);
    $ok = $include->setPage(array(
      'id'      => $id,
      'section' => $section,
      'perm'    => $perm,
      'exists'  => $exists,
    ));
    if (!$ok) return false; // prevent recursion
    
    if ($mode == 'xhtml'){
          
      // prevent caching to ensure the included page is always fresh 
      $renderer->info['cache'] = FALSE; 
    
      // current section level
      $clevel = 0;
      preg_match_all('|<div class="level(\d)">|i', $renderer->doc, $matches, PREG_SET_ORDER);
      $n = count($matches) - 1;
      if ($n > -1) $clevel = $matches[$n][1];
      $include->setLevel($clevel);
      
      // close current section
      if ($clevel && ($type == 'section'))
        $renderer->doc .= '</div>';
      
      // include the page now
      $include->renderXHTML($renderer);
      
      // resume current section
      if ($clevel && ($type == 'section'))
        $renderer->doc .= '<div class="level'.$clevel.'">';
      
      return true;
       
    // for metadata renderer
    } elseif ($mode == 'metadata'){
      $renderer->meta['relation']['haspart'][$id] = $exists;
      $include->pages = array(); // clear filechain - important!

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