<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC.'lib/plugins/');

class helper_plugin_include extends DokuWiki_Plugin { // DokuWiki_Helper_Plugin

  var $pages     = array();   // filechain of included pages
  var $page      = array();   // associative array with data about the page to include
  var $ins       = array();   // instructions array
  var $doc       = '';        // the final output XHTML string
  var $mode      = 'section'; // inclusion mode: 'page' or 'section'
  var $clevel    = 0;         // current section level
  var $firstsec  = 0;         // show first section only
  var $footer    = 1;         // show metaline below page
  var $header    = array();   // included page / section header
  var $renderer  = NULL;      // DokuWiki renderer object
  
  /**
   * Constructor loads some config settings
   */
  function helper_plugin_include(){
    $this->firstsec = $this->getConf('firstseconly');
    $this->footer = $this->getConf('showfooter');
  }
  
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2007-01-12',
      'name'   => 'Include Plugin (helper class)',
      'desc'   => 'Functions to include another page in a wiki page',
      'url'    => 'http://www.wikidesign/en/plugin/include/start',
    );
  }
  
  function getMethods(){
    $result = array();
    $result[] = array(
      'name'   => 'setPage',
      'desc'   => 'sets the page to include',
      'params' => array("page attributes, 'id' required, 'section' for filtering" => 'array'),
      'return' => array('success' => 'boolean'),
    );
    $result[] = array(
      'name'   => 'setMode',
      'desc'   => 'sets inclusion mode: should indention be merged?',
      'params' => array("'page' (original) or 'section' (merged indention)" => 'string'),
    );
    $result[] = array(
      'name'   => 'setLevel',
      'desc'   => 'sets the indention for the current section level',
      'params' => array('level: 0 to 5' => 'integer'),
      'return' => array('success' => 'boolean'),
    );
    $result[] = array(
      'name'   => 'setFlags',
      'desc'   => 'overrides standard values for showfooter and firstseconly settings',
      'params' => array('flags' => 'array'),
    );
    $result[] = array(
      'name'   => 'renderXHTML',
      'desc'   => 'renders the XHTML output of the included page',
      'params' => array('DokuWiki renderer' => 'object'),
      'return' => array('XHTML' => 'string'),
    );
    return $result;
  }
  
  /**
   * Sets the page to include if it is not already included (prevent recursion)
   */
  function setPage($page){
    global $ID;
    
    $id     = $page['id'];
    $fullid = $id.'#'.$page['section'];
    
    if (!$id) return false;       // no page id given
    if ($id == $ID) return false; // page can't include itself
    
    // prevent include recursion
    if ((isset($this->pages[$id.'#'])) || (isset($this->pages[$fullid]))) return false;
    
    // add the page to the filechain
    $this->pages[$fullid] = $page;
    $this->page =& $this->pages[$fullid];
    return true;
  }
  
  /**
   * Sets the inclusion mode
   */
  function setMode($mode){
    $this->mode = $mode;
  }
  
  /**
   * Sets the right indention for a given section level
   */
  function setLevel($level){
    if ((is_numeric($level)) && ($level >= 0) && ($level <= 5)){
      $this->clevel = $level;
      return true;
    }
    return false;
  }
  
  /**
   * Overrides standard values for showfooter and firstseconly settings
   */
  function setFlags($flags){
    foreach ($flags as $flag){
      switch ($flag){
      case 'footer':
        $this->footer = 1;
        break;
      case 'nofooter':
        $this->footer = 0;
        break;
      case 'firstseconly':
        $this->firstsec = 1;
        break;
      case 'fullpage':
        $this->firstsec = 0;
        break;
      }
    }
  }
  
  /**
   * Builds the XHTML to embed the page to include
   */
  function renderXHTML(&$renderer){
    if (!$this->page['id']) return ''; // page must be set first
    if (!$this->page['exists'] && ($this->page['perm'] < AUTH_CREATE)) return '';
    
    // prepare variables
    $this->doc      = '';
    $this->renderer =& $renderer;
     
    // get instructions and render them on the fly
    $this->page['file'] = wikiFN($this->page['id']);
    $this->ins = p_cached_instructions($this->page['file']);
        
    // show only a given section?
    if ($this->page['section'] && $this->page['exists']) $this->_getSection();
          
    // convert relative links
    $this->_convertInstructions();
    
    // insert a read more link if only first section is shown
    if ($this->firstsec) $this->_readMore();
    
    // render the included page
    if ($this->header) $content = '<h'.$this->header['level'].' class="entry-title">'.
      '<a name="'.$this->header['hid'].'" id="'.$this->header['hid'].'">'.
      $this->header['title'].'</a></h'.$this->header['level'].'>'.DOKU_LF;
    else $content = '';
    $content .= '<div class="entry-content">'.DOKU_LF.
      $this->_cleanXHTML(p_render('xhtml', $this->ins, $info)).DOKU_LF.
      '</div>'.DOKU_LF;
    
    // embed the included page
    $class = ($this->page['draft'] ? 'include draft' : 'include');
    $renderer->doc .= '<div class="'.$class.' hentry"'.$this->_showTagLogos().'>'.DOKU_LF;
    if (!$this->header && $this->clevel && ($this->mode == 'section'))
      $renderer->doc .= '<div class="level'.$this->clevel.'">'.DOKU_LF;
    if ((@file_exists(DOKU_PLUGIN.'editsections/action.php'))
      && (!plugin_isdisabled('editsections'))){ // for Edit Section Reorganizer Plugin
      $renderer->doc .= $this->_editButton().$content; 
    } else { 
      $renderer->doc .= $content.$this->_editButton();
    }
    
    if (!$this->header && $this->clevel && ($this->mode == 'section'))
      $renderer->doc .= '</div>'.DOKU_LF; // class="level?"
    $renderer->doc .= '</div>'.DOKU_LF; // class="include hentry"
    
    // output meta line (if wanted) and remove page from filechain
    $renderer->doc .= $this->_footer(array_pop($this->pages));
    $this->helper_plugin_include();
    
    return $this->doc;    
  }
  
/* ---------- Private Methods ---------- */
         
  /** 
   * Get a section including its subsections 
   */ 
  function _getSection(){ 
    foreach ($this->ins as $ins){ 
      if ($ins[0] == 'header'){ 
  
        // found the right header 
        if (cleanID($ins[1][0]) == $this->page['section']){ 
          $level = $ins[1][1]; 
          $i[] = $ins; 
  
        // next header of the same or higher level -> exit 
        } elseif ($ins[1][1] <= $level){
          $this->ins = $i;
          return true; 
        } elseif (isset($level)){ 
          $i[] = $ins; 
        } 
  
      // add instructions from our section 
      } elseif (isset($level)){ 
        $i[] = $ins; 
      } 
    } 
    $this->ins = $i;
    return true; 
  } 
  
  /** 
   * Corrects relative internal links and media and 
   * converts headers of included pages to subheaders of the current page 
   */
  function _convertInstructions(){ 
    global $ID; 
    global $conf;
    
    $this->header = array();
    $offset = $this->clevel;
    
    if (!$this->page['exists']) return false;
  
    // check if included page is in same namespace 
    $inclNS = getNS($this->page['id']);
    if (getNS($ID) == $inclNS) $convert = false; 
    else $convert = true; 
  
    $n = count($this->ins);
    for ($i = 0; $i < $n; $i++){ 
  
      // convert internal links and media from relative to absolute 
      if ($convert && (substr($this->ins[$i][0], 0, 8) == 'internal')){ 
  
        // relative subnamespace 
        if ($this->ins[$i][1][0]{0} == '.'){
          // parent namespace
          if ($this->ins[$i][1][0]{1} == '.')
            $ithis->ns[$i][1][0] = getNS($inclNS).':'.substr($this->ins[$i][1][0], 2);
          // current namespace
          else
            $this->ins[$i][1][0] = $inclNS.':'.substr($this->ins[$i][1][0], 1);
  
        // relative link 
        } elseif (strpos($this->ins[$i][1][0], ':') === false){
          $this->ins[$i][1][0] = $inclNS.':'.$this->ins[$i][1][0];
        }
  
      // set header level to current section level + header level 
      } elseif ($this->ins[$i][0] == 'header'){
        if (empty($this->header)){
          $offset = $this->clevel - $this->ins[$i][1][1] + 1;
          $text   = $this->ins[$i][1][0]; 
          $hid    = $this->renderer->_headerToLink($text, 'true');
          $level  = $this->clevel + 1;
          $this->header = array(
            'hid'   => $hid,
            'title' => hsc($text),
            'level' => $level
          );
          unset($this->ins[$i]);
        } else {
          $level = $this->ins[$i][1][1] + $offset;
          if ($level > 5) $level = 5; 
          $this->ins[$i][1][1] = $level;
        }
          
        // add TOC items
        if (($level >= $conf['toptoclevel']) && ($level <= $conf['maxtoclevel'])){ 
          $this->renderer->toc[] = array( 
            'hid'   => $hid, 
            'title' => $text, 
            'type'  => 'ul', 
            'level' => $level - $conf['toptoclevel'] + 1 
          );
        }        

      // the same for sections 
      } elseif ($this->ins[$i][0] == 'section_open'){
        $level = $this->ins[$i][1][0] + $offset; 
        if ($level > 5) $level = 5; 
        $this->ins[$i][1][0] = $level;
      
      // show only the first section? 
      } elseif ($this->firstsec && ($this->ins[$i][0] == 'section_close')
        && ($this->ins[$i-1][0] != 'section_open')){
        if ($this->ins[0][0] == 'document_start'){
          $this->ins = array_slice($this->ins, 1, $i);
          return true;
        } else {
          $this->ins = array_slice($this->ins, 0, $i);
          return true;
        }
      } 
    } 
    if ($this->ins[0][0] != 'document_start'){
      array_unshift($this->ins, array('document_start', array(), 0));
      $this->ins[] = array('document_end', array(), 0);
    }
    return true;
  } 
  
  /** 
   * Remove TOC, section edit buttons and tags 
   */ 
  function _cleanXHTML($xhtml){
    preg_match('!<div class="tags">.*?</div>!s', $xhtml, $match);
    $this->page['tags'] = $match[0];
    $replace = array( 
      '!<div class="toc">.*?(</div>\n</div>)!s'   => '', // remove toc 
      '#<!-- SECTION "(.*?)" \[(\d+-\d*)\] -->#e' => '', // remove section edit buttons 
      '!<div class="tags">.*?(</div>)!s'          => '', // remove category tags 
    );
    $xhtml  = preg_replace(array_keys($replace), array_values($replace), $xhtml); 
    return $xhtml; 
  }
  
  /**
   * Optionally display logo for the first tag found in the included page
   */
  function _showTagLogos(){
    if (!$this->getConf('showtaglogos')) return '';
    
    preg_match_all('/<a [^>]*title="(.*?)" rel="tag"[^>]*>([^<]*)</', $this->page['tags'], $tag);
    $logoID  = getNS($tag[1][0]).':'.$tag[2][0];
    $logosrc = mediaFN($logoID);
    $types = array('.png', '.jpg', '.gif'); // auto-detect filetype
    foreach ($types as $type){
      if (!@file_exists($logosrc.$type)) continue;
      $logoID  .= $type;
      $logosrc .= $type;
      list($w, $h, $t, $a) = getimagesize($logosrc);
      return ' style="min-height: '.$h.'px">'.
        '<img class="mediaright" src="'.ml($logoID).'" alt="'.$tag[2][0].'"/';
    }
    return '';
  }
  
  /** 
   * Display an edit button for the included page 
   */ 
  function _editButton(){ 
    if (!isset($this->page['perm']))
      $this->page['perm'] = auth_quickaclcheck($this->page['id']);
    if (@file_exists($this->page['file'])){ 
      if (($this->page['perm'] >= AUTH_EDIT) && (is_writable($this->page['file'])))
        $action = 'edit';
      else return '';
    } elseif ($this->page['perm'] >= AUTH_CREATE){ 
      $action = 'create';
    }
    return '<div class="secedit">'.DOKU_LF.DOKU_TAB.
      html_btn($action, $this->page['id'], '', array('do' => 'edit'), 'post').DOKU_LF.
      '</div>'.DOKU_LF; 
  } 
  
  /**
   * Adds a read more... link at the bottom of the first section
   */
  function _readMore(){
    $last    = $this->ins[count($this->ins) - 1];
    if ($last[0] == 'section_close') $this->ins = array_slice($this->ins, 0, -1);
    $this->ins[] = array('p_open', array(), $last[2]);
    $this->ins[] = array('internallink', array($this->page['id'], $this->getLang('readmore')), $last[2]);
    $this->ins[] = array('p_close', array(), $last[2]);
    if ($last[0] == 'section_close') $this->ins[] = $last;
  }
  
  /**
   * Returns the meta line below the included page
   */
  function _footer($page){
    global $conf;
    
    if (!$this->footer) return ''; // '<div class="inclmeta">&nbsp;</div>'.DOKU_LF;
    
    $id   = $page['id'];
    $meta = p_get_metadata($id);
    $ret  = array();
        
    // permalink
    if ($this->getConf('showlink')){
      $title = ($page['title'] ? $page['title'] : $meta['title']);
      if (!$title) $title = str_replace('_', ' ', noNS($id));
      $class = ($page['exists'] ? 'wikilink1' : 'wikilink2');
      $link = array(
        'url'    => wl($id),
        'title'  => $id,
        'name'   => hsc($title),
        'target' => $conf['target']['wiki'],
        'class'  => $class.' permalink',
        'more'   => 'rel="bookmark"',
      );
      $ret[] = $this->renderer->_formatLink($link);
    }
    
    // date
    if ($this->getConf('showdate')){
      $date = ($page['date'] ? $page['date'] : $meta['date']['created']);
      if ($date)
        $ret[] = '<abbr class="published" title="'.gmdate('Y-m-d\TH:i:s\Z', $date).'">'.
        date($conf['dformat'], $date).
        '</abbr>';
    }
    
    // author
    if ($this->getConf('showuser')){
      $author   = ($page['user'] ? $page['user'] : $meta['creator']);
      if ($author){
        $userpage = cleanID($this->getConf('usernamespace').':'.$author);
        resolve_pageid(getNS($ID), $id, $exists);
        $class = ($exists ? 'wikilink1' : 'wikilink2');
        $link = array(
          'url'    => wl($userpage),
          'title'  => $userpage,
          'name'   => hsc($author),
          'target' => $conf['target']['wiki'],
          'class'  => $class.' url fn',
          'pre'    => '<span class="vcard author">',
          'suf'    => '</span>',
        );
        $ret[]    = $this->renderer->_formatLink($link);
      }
    }
           
    // comments - let Discussion Plugin do the work for us
    if (!$page['section'] && $this->getConf('showcomments')
      && (!plugin_isdisabled('discussion'))
      && ($discussion =& plugin_load('helper', 'discussion'))){
      $disc = $discussion->td($id);
      if ($disc) $ret[] = '<span class="comment">'.$disc.'</span>';
    }
    
    // linkbacks - let Linkback Plugin do the work for us
    if (!$page['section'] && $this->getConf('showlinkbacks')
      && (!plugin_isdisabled('linkback'))
      && ($linkback =& plugin_load('helper', 'linkback'))){
      $link = $linkback->td($id);
      if ($link) $ret[] = '<span class="linkback">'.$link.'</span>';
    }
    
    $ret = implode(' &middot; ', $ret);
    
    // tags
    if (($this->getConf('showtags')) && ($page['tags'])){
      $ret = $this->page['tags'].$ret;
    }
    
    if (!$ret) $ret = '&nbsp;';
    return '<div class="inclmeta">'.DOKU_LF.$ret.DOKU_LF.'</div>'.DOKU_LF;
  }
    
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :
