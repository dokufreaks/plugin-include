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
      'date'   => '2007-08-10', 
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
   
  function render($format, &$renderer, $data){
    global $ID;
 
    list($type, $raw_id, $section, $flags) = $data; 
 
    $id = $this->_applyMacro($raw_id);
    $nocache = ($id != $raw_id);
    resolve_pageid(getNS($ID), $id, $exists); // resolve shortcuts
    
    $include =& plugin_load('helper', 'include');
    $include->setMode($type);
    $include->setFlags($flags);

    //  initiate inclusion of external content for those renderer formats which require it
    //  - currently only 'xhtml'
    if (in_array($format, array('xhtml'))) {

      if ($nocache) $renderer->info['cache'] = false;                 // prevent caching
      if (AUTH_READ > auth_quickaclcheck($id)) return true;             // check for permission 
 
      if (!$include->setPage(compact('type','id','section','exists'))) return false;
      

      $ok = $this->_include($include, $format, $renderer, $type, $id, $section, $flags, $nocache);

    } else if (in_array($format, array('odt'))) {
      if ($nocache) $renderer->info['cache'] = false;

      // current section level
      $clevel = 0;
      preg_match_all('|<text:h text:style-name="Heading_20_\d" text:outline-level="(\d)">|i', $renderer->doc, $matches, PREG_SET_ORDER);
      $n = count($matches) - 1;
      if ($n > -1) $clevel = $matches[$n][1];
      $include->setLevel($clevel);
      
      // include the page now
      $include->renderODT($renderer);
      
      return true;

    } else {
      // carry out renderering for all other formats
      $ok = $this->_no_include($include, $format, $renderer, $type, $id, $section, $flags, $nocache);

#global $debug;
#$debug[] = compact('id','raw_id','flg_macro','format');
    }
       
    return false;  
  }

/* ---------- Util Functions ---------- */
    
  /**
   * render process for renderer formats which do include external content
   *
   * @param  $include   obj     include helper plugin
   * @param  $format    string  renderer format
   * @param  $renderer  obj     renderer
   * @param  $type      string  include type ('page' or 'section')
   * @param  $id        string  fully resolved wiki page id of included page
   * @param  $section   string  fragment identifier for page fragment to be included
   * @param  $flg_macro bool    true if $id was modified by macro substitution
   */
  function _include(&$include, $format, &$renderer, $type, $id, $section, $flags, $flg_macro) {
 
    $file    = wikiFN($id); 
     
    if ($format == 'xhtml') { 
      
     // current section level 
      $matches = array(); 
      preg_match_all('|<div class="level(\d)">|i', $renderer->doc, $matches, PREG_SET_ORDER); 
      $n = count($matches)-1; 
      $clevel = ($n > -1)  ? $clevel = $matches[$n][1] : 0; 
      $include->setLevel($clevel);

      // close current section
      if ($clevel && ($type == 'section')) $renderer->doc .= '</div>';

      // include the page
      $include->renderXHTML($renderer,$info);

      // propagate any cache prevention from included pages into this page
      if ($info['cache'] == false) $renderer->info['cache'] = false;

      // resume current section
      if ($clevel && ($type == 'section')) $renderer->doc .= '<div class="level'.$clevel.'">';
 
      return true; 

    } else {  // other / unsupported format
    }
 
    return false;  
  } 
    
  /**
   * render process for renderer formats which don't include external content
   *
   * @param  $include   obj     include helper plugin
   * @param  $format    string  renderer format
   * @param  $renderer  obj     renderer
   * @param  $type      string  include type ('page' or 'section')
   * @param  $id        string  fully resolved wiki page id of included page
   * @param  $section   string  fragment identifier for page fragment to be included
   * @param  $flg_macro bool    true if $id was modified by macro substitution
   */
  function _no_include(&$include, $format, &$renderer, $type, $id, $section, $flags, $flg_macro) {

    switch ($format) {
      case 'metadata' :
        if (!$flg_macro) {
          $renderer->meta['relation']['haspart'][$id] = @file_exists(wikiFN($id));
        }
        return true;
        
      default :  // unknown / unsupported renderer format
        return false;
    }
    
    return false;
  }

  /**
   * Makes user or date dependent includes possible
   */
  function _applyMacro($id){
    global $INFO, $auth;
    
    $user     = $_SERVER['REMOTE_USER'];
    $userdata = $auth->getUserData($user);
    $group    = $userdata['grps'][0];
 
    $replace = array( 
      '@USER@'  => cleanID($user), 
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
