<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class plugin_class_include extends DokuWiki_Plugin {

  var $page      = array();   // associative array with data about the page to include
  var $ins       = array();   // instructions array
  var $doc       = '';        // the final output XHTML string
  var $type      = 'section'; // inclusion mode: 'page' or 'section'
  var $clevel    = 0;         // current section level
  var $firstsec  = 0;         // show first section only
  var $hasheader = 0;         // included page has header

  /**
   * Plugin needs to tell its name. Important for settings and localized strings!
   */
  function getPluginName(){
    $path = realpath(dirname(__FILE__).'/../');
    return substr(strrchr($path, '/'), 1);
  }
  
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-11-24',
      'name'   => 'Include Class',
      'desc'   => 'Functions to include another page in a wiki page',
      'url'    => 'http://www.wikidesign/en/plugin/blog/start',
    );
  }
  
  /**
   * Builds the XHTML to embed the page to include
   */
  function _include(&$renderer){ 
    global $filechain;
    if (!isset($filechain)) $filechain = array();
    
    $this->doc = '';
    
    // add the page to the filechain
    array_push($filechain, $his->page['id'].'#'.$this->page['section']);
    
    $this->firstsec = $this->getConf('firstseconly');
     
    // get instructions and render them on the fly
    $this->page['file'] = wikiFN($this->page['id']);
    $this->ins = p_cached_instructions($this->page['file']);
    
    if (!empty($this->ins)){
    
      // show only a given section?
      if ($this->page['section']) $this->_getSection();
            
      // convert relative links
      $this->_convertInstructions($renderer);
      
      // insert a read more link if only first section is shown
      if ($this->firstsec) $this->_readMore();
      
      // render the included page
      $content = $this->_cleanXHTML(p_render('xhtml', $this->ins, $info));
      
      // embed the included page
      $this->doc .= '<div class="include"'.$this->_showTagLogos().'>';
      if (!$this->hasheader && $this->clevel && ($this->type == 'section'))
        $this->doc .= '<div class="level'.$this->clevel.'">';
      $this->doc .= $content.$this->_editButton();
      if (!$this->hasheader && $this->clevel && ($this->type == 'section'))
        $this->doc .= '</div>';
      $this->doc .= '</div>';
      $this->doc .= $this->_metaLine($renderer);
    }
    
    // remove the page from the filechain again
    array_pop($filechain);
    
    return $this->doc;
  }
  
  /**
   * Checks if the page to include is already included (prevent recursion)
   *
   * @param   $id        page to check
   * @param   $section   section title if only a section is included
   */
  function _inFilechain(){
    global $ID;
    global $filechain;
    if (!isset($filechain)) $filechain = array();
    
    if ($this->page['id'] == $ID) return true; // page can't include itself
    
    $id = preg_quote($this->page['id'], '/');
    $section = preg_quote($this->page['section'], '/');
    
    $pattern = ($section ? "/^($id#$section|$id#)$/" : "/^$id#/");
    $match = preg_grep($pattern, $filechain);
    return (!empty($match));
  }
       
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
  function _convertInstructions(&$renderer){ 
    global $ID; 
    global $conf; 
  
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
        $level = $this->ins[$i][1][1] + $this->clevel; 
        if ($level > 5) $level = 5; 
        $this->ins[$i][1][1] = $level; 
  
        // add TOC items 
        if (($level >= $conf['toptoclevel']) && ($level <= $conf['maxtoclevel'])){ 
          $text = $this->ins[$i][1][0]; 
          $hid  = $renderer->_headerToLink($text, 'true'); 
          $renderer->toc[] = array( 
            'hid'   => $hid, 
            'title' => $text, 
            'type'  => 'ul', 
            'level' => $level - $conf['toptoclevel'] + 1 
          );
          
          $this->hasheader = true;
        } 
  
      // the same for sections 
      } elseif ($this->ins[$i][0] == 'section_open'){ 
        $level = $this->ins[$i][1][0] + $this->clevel; 
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
    if ($this->ins[0][0] == 'document_start') $this->ins = array_slice($this->ins, 1, -1);
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
    $ret = ''; 
    if (!isset($this->page['perm']))
      $this->page['perm'] = auth_quickaclcheck($this->page['id']);
    if (@file_exists($this->page['file'])){ 
      if (($this->page['perm'] >= AUTH_EDIT) && (is_writable($this->page['file'])))
        $ret = '<div class="secedit">'.
          html_btn('edit', $this->page['id'], '', array('do' => 'edit'), 'post').
          '</div>'; 
    } elseif ($this->page['perm'] >= AUTH_CREATE){ 
      $ret = '<div class="secedit">'.
        html_btn('create', $this->page['id'], '', array('do' => 'edit'), 'post').
        '</div>'; 
    } 
    return $ret;
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
  function _metaLine(&$renderer){
    global $conf;
    
    $id   = $this->page['id'];
    $meta = p_get_metadata($id);
    $ret  = array();
        
    // permalink
    if ($this->getConf('showlink')){
      $title = ($this->page['title'] ? $this->page['title'] : $meta['title']);
      if (!$title) $title = str_replace('_', ' ', noNS($this->page['id']));
      $ret[] = $renderer->internallink($this->page['id'], $title, '', true);
    }
    
    // author
    if ($this->getConf('showuser')){
      $author   = ($this->page['user'] ? $this->page['user'] : $meta['creator']);
      if ($author){
        $userpage = cleanID($this->getConf('user_namespace').':'.$author);
        $ret[]    = $renderer->internallink($userpage, $author, '', true);
      }
    }
    
    // date
    if ($this->getConf('showdate')){
      $date  = ($this->page['date'] ? $this->page['date'] : $meta['date']['created']);
      $ret[] = date($conf['dformat'], $date);
    }
           
    // comments
    $cfile = metaFN($id, '.comments');
    if (@file_exists($cfile) && !$this->page['section']){
      $comments = unserialize(io_readFile($cfile, false));
      if ($comments['status']){
        $discuss = $id.'#'.cleanID($this->getLang('discussion'));
        $noc     = $comments['number'];
        if ($noc == 0) $comment = '0 '.$this->getLang('comments');
        elseif ($noc == 1) $comment = '1 '.$this->getLang('comment');
        else $comment = $noc.' '.$this->getLang('comments');
        $ret[] = $renderer->internallink($discuss, $comment, '', true);
      }
    }
    
    $ret = $this->page['tags'].implode(' &middot; ', $ret);
    if (!$ret) $ret = '&nbsp;';
    return '<div class="inclmeta">'.$ret.'</div>';
  }
  
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :
