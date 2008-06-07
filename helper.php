<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 * @author     Christopher Smith <chris@jalakai.co.uk>
 * @author     Gina Häußge, Michael Klier <dokuwiki@chimeric.de>
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
    var $editbtn   = 1;         // show edit button
    var $footer    = 1;         // show metaline below page
    var $noheader  = 0;         // omit header
    var $header    = array();   // included page / section header
    var $renderer  = NULL;      // DokuWiki renderer object

    var $INCLUDE_LIMIT = 12;

    // private variables
    var $_offset   = NULL;

    /**
     * Constructor loads some config settings
     */
    function helper_plugin_include() {
        $this->firstsec = $this->getConf('firstseconly');
        $this->editbtn  = $this->getConf('showeditbtn');
        $this->footer   = $this->getConf('showfooter');
        $this->noheader = 0;
        $this->header   = array();
    }

    function getInfo() {
        return array(
                'author' => 'Gina Häussge, Michael Klier, Esther Brunner',
                'email'  => 'dokuwiki@chimeric.de',
                'date'   => '2008-04-20',
                'name'   => 'Include Plugin (helper class)',
                'desc'   => 'Functions to include another page in a wiki page',
                'url'    => 'http://wiki.splitbrain.org/plugin:include',
                );
    }

    function getMethods() {
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
     * and the current user is allowed to read it
     */
    function setPage($page) {
        global $ID;

        $id     = $page['id'];
        $fullid = $id.'#'.$page['section'];

        if (!$id) return false;       // no page id given
        if ($id == $ID) return false; // page can't include itself

        // prevent include recursion
        if ($this->_in_filechain($id,$page['section']) || (count($this->pages) >= $this->INCLUDE_LIMIT)) return false;

        // we need to make sure 'perm', 'file' and 'exists' are set
        if (!isset($page['perm'])) $page['perm'] = auth_quickaclcheck($page['id']);
        if (!isset($page['file'])) $page['file'] = wikiFN($page['id']);
        if (!isset($page['exists'])) $page['exists'] = @file_exists($page['file']);

        // check permission
        if ($page['perm'] < AUTH_READ) return false;

        // add the page to the filechain
        $this->page = $page;
        return true;
    }

    function _push_page($id,$section) {
        global $ID;
        if (empty($this->pages)) array_push($this->pages, $ID.'#');
        array_push($this->pages, $id.'#'.$section);    
    }

    function _pop_page() {
        $page = array_pop($this->pages);
        if (count($this->pages=1)) $this->pages = array();

        return $page;    
    }

    function _in_filechain($id,$section) {     
        $pattern = $section ? "/^($id#$section|$id#)$/" : "/^$id#/"; 
        $match = preg_grep($pattern, $this->pages);

        return (!empty($match)); 
    } 

    /**
     * Sets the inclusion mode: 'page' or 'section'
     */
    function setMode($mode) {
        $this->mode = $mode;
    }

    /**
     * Sets the right indention for a given section level
     */
    function setLevel($level) {
        if ((is_numeric($level)) && ($level >= 0) && ($level <= 5)) {
            $this->clevel = $level;
            return true;
        }
        return false;
    }

    /**
     * Overrides standard values for showfooter and firstseconly settings
     */
    function setFlags($flags) {
        foreach ($flags as $flag) {
            switch ($flag) {
                case 'footer':
                    $this->footer = 1;
                    break;
                case 'nofooter':
                    $this->footer = 0;
                    break;
                case 'firstseconly':
                case 'firstsectiononly':
                    $this->firstsec = 1;
                    break;
                case 'fullpage':
                    $this->firstsec = 0;
                    break;
                case 'noheader':
                    $this->noheader = 1;
                    break;
                case 'editbtn':
                case 'editbutton':
                    $this->editbtn = 1;
                    break;
                case 'noeditbtn':
                case 'noeditbutton':
                    $this->editbtn = 0;
                    break;
            }
        }
    }

    /**
     * Builds the XHTML to embed the page to include
     */
    function renderXHTML(&$renderer, &$info) {
        global $ID;

        if (!$this->page['id']) return ''; // page must be set first
        if (!$this->page['exists'] && ($this->page['perm'] < AUTH_CREATE)) return '';

        $this->_push_page($this->page['id'],$this->page['section']);

        // prepare variables
        $rdoc  = $renderer->doc;
        $doc = '';
        $this->renderer =& $renderer;

        $page = $this->page;
        $clevel = $this->clevel;
        $mode = $this->mode;

        // exchange page ID for included one
        $backupID = $ID;               // store the current ID
        $ID       = $this->page['id']; // change ID to the included page

        // get instructions and render them on the fly
        $this->ins = p_cached_instructions($this->page['file']);

        // show only a given section?
        if ($this->page['section'] && $this->page['exists']) $this->_getSection();

        // convert relative links
        $this->_convertInstructions();

        $xhtml = p_render('xhtml', $this->ins, $info);
        $ID = $backupID;               // restore ID

        $this->mode = $mode;
        $this->clevel = $clevel;
        $this->page = $page;

        // render the included page
        $content = '<div class="entry-content">'.DOKU_LF.
            $this->_cleanXHTML($xhtml).DOKU_LF.
            '</div><!-- .entry-content -->'.DOKU_LF;

        // restore ID
        $ID = $backupID;

        // embed the included page
        $class = ($this->page['draft'] ? 'include draft' : 'include');

        $doc .= DOKU_LF.'<!-- including '.$this->page['id'].' // '.$this->page['file'].' -->'.DOKU_LF;
        $doc .= '<div class="'.$class.' hentry"'.$this->_showTagLogos().'>'.DOKU_LF;
        if (!$this->header && $this->clevel && ($this->mode == 'section'))
            $doc .= '<div class="level'.$this->clevel.'">'.DOKU_LF;

        if ((@file_exists(DOKU_PLUGIN.'editsections/action.php'))
                && (!plugin_isdisabled('editsections'))) { // for Edit Section Reorganizer Plugin
            $doc .= $this->_editButton().$content; 
        } else { 
            $doc .= $content.$this->_editButton();
        }

        // output meta line (if wanted) and remove page from filechain
        $doc .= $this->_footer($this->page);

        if (!$this->header && $this->clevel && ($this->mode == 'section'))
            $doc .= '</div>'.DOKU_LF; // class="level?"
        $doc .= '</div>'.DOKU_LF; // class="include hentry"
        $doc .= DOKU_LF.'<!-- /including '.$this->page['id'].' -->'.DOKU_LF;

        // reset defaults
        $this->helper_plugin_include();
        $this->_pop_page();

        // return XHTML
        $renderer->doc = $rdoc.$doc;
        return $doc;   
    }

    /* ---------- Private Methods ---------- */

    /** 
     * Get a section including its subsections 
     */ 
    function _getSection() { 
        foreach ($this->ins as $ins) { 
            if ($ins[0] == 'header') { 

                // found the right header 
                if (cleanID($ins[1][0]) == $this->page['section']) { 
                    $level = $ins[1][1]; 
                    $i[] = $ins; 

                    // next header of the same or higher level -> exit 
                } elseif ($ins[1][1] <= $level) {
                    $this->ins = $i;
                    return true; 
                } elseif (isset($level)) { 
                    $i[] = $ins; 
                } 

                // add instructions from our section 
            } elseif (isset($level)) { 
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
    function _convertInstructions() {
        global $ID;

        if (!$this->page['exists']) return false;

        // check if included page is in same namespace 
        $ns      = getNS($this->page['id']);
        $convert = (getNS($ID) == $ns ? false : true); 

        $n = count($this->ins);
        for ($i = 0; $i < $n; $i++) {
            $current = $this->ins[$i][0];

            // convert internal links and media from relative to absolute
            if ($convert && (substr($current, 0, 8) == 'internal')) { 
                $this->ins[$i][1][0] = $this->_convertInternalLink($this->ins[$i][1][0], $ns);

                // set header level to current section level + header level 
            } elseif ($current == 'header') {
                $this->_convertHeader($i);

                // the same for sections 
            } elseif (($current == 'section_open') && ($this->mode == 'section')) {
                $this->ins[$i][1][0] = $this->_convertSectionLevel($this->ins[$i][1][0]);

                // show only the first section? 
            } elseif ($this->firstsec && ($current == 'section_close')
                    && ($this->ins[$i-1][0] != 'section_open')) {
                $this->_readMore($i);
                return true;
            } 
        } 
        $this->_finishConvert();
        return true;
    }

    /**
     * Convert relative internal links and media
     *
     * @param    integer $i: counter for current instruction
     * @param    string  $ns: namespace of included page
     * @return   string  $link: converted, now absolute link
     */
    function _convertInternalLink($link, $ns) {

        // relative subnamespace 
        if ($link{0} == '.') {
            if ($link{1} == '.') return getNS($ns).':'.substr($link, 2); // parent namespace
            else return $ns.':'.substr($link, 1);                        // current namespace

            // relative link 
        } elseif (strpos($link, ':') === false) {
            return $ns.':'.$link;

            // absolute link - don't change
        } else {
            return $link;
        }
    }

    /**
     * Convert header level and add header to TOC
     *
     * @param    integer $i: counter for current instruction
     * @return   boolean true
     */
    function _convertHeader($i) {
        global $conf;

        $text = $this->ins[$i][1][0]; 
        $hid  = $this->renderer->_headerToLink($text, 'true');
        if (empty($this->header)) {
            $this->_offset = $this->clevel - $this->ins[$i][1][1] + 1;
            $level = $this->_convertSectionLevel(1);
            $this->header = array('hid' => $hid, 'title' => hsc($text), 'level' => $level);
            if ($this->noheader) {
                unset($this->ins[$i]);
                return true;
            }
        } else {
            $level = $this->_convertSectionLevel($this->ins[$i][1][1]);
        }
        if ($this->mode == 'section') $this->ins[$i][1][1] = $level;

        // add TOC item
        if (($level >= $conf['toptoclevel']) && ($level <= $conf['maxtoclevel'])) { 
            $this->renderer->toc[] = array( 
                    'hid'   => $hid, 
                    'title' => $text, 
                    'type'  => 'ul', 
                    'level' => $level - $conf['toptoclevel'] + 1 
                    );
        }
        return true;
    }

    /**
     * Convert the level of headers and sections
     *
     * @param    integer $in: current level
     * @return   integer $out: converted level
     */
    function _convertSectionLevel($in) {
        $out = $in + $this->_offset;
        if ($out >= 5) return 5;
        if ($out <= $this->clevel + 1) return $this->clevel + 1;
        return $out;
    }

    /**
     * Adds a read more... link at the bottom of the first section
     *
     * @param    integer $i: counter for current instruction
     * @return   boolean true
     */
    function _readMore($i) {
        $more = ((is_array($this->ins[$i+1])) && ($this->ins[$i+1][0] != 'document_end'));

        if ($this->ins[0][0] == 'document_start') $this->ins = array_slice($this->ins, 1, $i);
        else $this->ins = array_slice($this->ins, 0, $i);

        if ($more) {
            array_unshift($this->ins, array('document_start', array(), 0));
            $last = array_pop($this->ins);
            $this->ins[] = array('p_open', array(), $last[2]);
            $this->ins[] = array('internallink',array($this->page['id'], $this->getLang('readmore')),$last[2]);
            $this->ins[] = array('p_close', array(), $last[2]);
            $this->ins[] = $last;
            $this->ins[] = array('document_end', array(), $last[2]);
        } else {
            $this->_finishConvert();
        }
        return true;
    }

    /**
     * Adds 'document_start' and 'document_end' instructions if not already there
     */
    function _finishConvert() {
        if ($this->ins[0][0] != 'document_start')
            array_unshift($this->ins, array('document_start', array(), 0));
        $c = count($this->ins) - 1;
        if ($this->ins[$c][0] != 'document_end')
            $this->ins[] = array('document_end', array(), 0);
    }

    /** 
     * Remove TOC, section edit buttons and tags 
     */ 
    function _cleanXHTML($xhtml) {
        $replace = array( 
                '!<div class="toc">.*?(</div>\n</div>)!s'   => '', // remove toc 
                '#<!-- SECTION "(.*?)" \[(\d+-\d*)\] -->#e' => '', // remove section edit buttons 
                '!<div class="tags">.*?(</div>)!s'          => '', // remove category tags 
                );
        if ($this->clevel)
            $replace['#<div class="footnotes">#s'] = '<div class="footnotes level'.$this->clevel.'">';
        $xhtml  = preg_replace(array_keys($replace), array_values($replace), $xhtml); 
        return $xhtml; 
    }

    /**
     * Optionally display logo for the first tag found in the included page
     */
    function _showTagLogos() {
        if ((!$this->getConf('showtaglogos'))
                || (plugin_isdisabled('tag'))
                || (!$taghelper =& plugin_load('helper', 'tag')))
            return '';

        $subject = p_get_metadata($this->page['id'], 'subject');
        if (is_array($subject)) $tag = $subject[0];
        else list($tag, $rest) = explode(' ', $subject, 2);
        $title = str_replace('_', ' ', noNS($tag));
        resolve_pageid($taghelper->namespace, $tag, $exists); // resolve shortcuts

        $logosrc = mediaFN($logoID);
        $types = array('.png', '.jpg', '.gif'); // auto-detect filetype
        foreach ($types as $type) {
            if (!@file_exists($logosrc.$type)) continue;
            $logoID   = $tag.$type;
            $logosrc .= $type;
            list($w, $h, $t, $a) = getimagesize($logosrc);
            return ' style="min-height: '.$h.'px">'.
                '<img class="mediaright" src="'.ml($logoID).'" alt="'.$title.'"/';
        }
        return '';
    }

    /** 
     * Display an edit button for the included page 
     */ 
    function _editButton() {
        if ($this->page['exists']) { 
            if (($this->page['perm'] >= AUTH_EDIT) && (is_writable($this->page['file'])))
                $action = 'edit';
            else return '';
        } elseif ($this->page['perm'] >= AUTH_CREATE) { 
            $action = 'create';
        }
        if ($this->editbtn) {
            return '<div class="secedit">'.DOKU_LF.DOKU_TAB.
                html_btn($action, $this->page['id'], '', array('do' => 'edit'), 'post').DOKU_LF.
                '</div>'.DOKU_LF;
        } else {
            return '';
        }
    } 

    /**
     * Returns the meta line below the included page
     */
    function _footer($page) {
        global $conf, $ID;

        if (!$this->footer) return ''; // '<div class="inclmeta">&nbsp;</div>'.DOKU_LF;

        $id   = $page['id'];
        $meta = p_get_metadata($id);
        $ret  = array();

        // permalink
        if ($this->getConf('showlink')) {
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
        if ($this->getConf('showdate')) {
            $date = ($page['date'] ? $page['date'] : $meta['date']['created']);
            if ($date)
                $ret[] = '<abbr class="published" title="'.strftime('%Y-%m-%dT%H:%M:%SZ', $date).'">'.
                    strftime($conf['dformat'], $date).
                    '</abbr>';
        }

        // author
        if ($this->getConf('showuser')) {
            $author   = ($page['user'] ? $page['user'] : $meta['creator']);
            if ($author) {
                $userpage = cleanID($this->getConf('usernamespace').':'.$author);
                resolve_pageid(getNS($ID), $userpage, $exists);
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
                && ($discussion =& plugin_load('helper', 'discussion'))) {
            $disc = $discussion->td($id);
            if ($disc) $ret[] = '<span class="comment">'.$disc.'</span>';
        }

        // linkbacks - let Linkback Plugin do the work for us
        if (!$page['section'] && $this->getConf('showlinkbacks')
                && (!plugin_isdisabled('linkback'))
                && ($linkback =& plugin_load('helper', 'linkback'))) {
            $link = $linkback->td($id);
            if ($link) $ret[] = '<span class="linkback">'.$link.'</span>';
        }

        $ret = implode(DOKU_LF.DOKU_TAB.'&middot; ', $ret);

        // tags - let Tag Plugin do the work for us
        if (!$page['section'] && $this->getConf('showtags')
                && (!plugin_isdisabled('tag'))
                && ($tag =& plugin_load('helper', 'tag'))) {
            $page['tags'] = '<div class="tags"><span>'.DOKU_LF.
                DOKU_TAB.$tag->td($id).DOKU_LF.
                DOKU_TAB.'</span></div>'.DOKU_LF;
            $ret = $page['tags'].DOKU_TAB.$ret;
        }

        if (!$ret) $ret = '&nbsp;';
        $class = 'inclmeta';
        if ($this->header && $this->clevel && ($this->mode == 'section'))
            $class .= ' level'.$this->clevel;
        return '<div class="'.$class.'">'.DOKU_LF.DOKU_TAB.$ret.DOKU_LF.'</div>'.DOKU_LF;
    }

    /**
     * Builds the ODT to embed the page to include
     */
    function renderODT(&$renderer) {
        global $ID;

        if (!$this->page['id']) return ''; // page must be set first
        if (!$this->page['exists'] && ($this->page['perm'] < AUTH_CREATE)) return '';

        // prepare variable
        $this->renderer =& $renderer;

        // get instructions and render them on the fly
        $this->ins = p_cached_instructions($this->page['file']);

        // show only a given section?
        if ($this->page['section'] && $this->page['exists']) $this->_getSection();

        // convert relative links
        $this->_convertInstructions();

        // render the included page
        $backupID = $ID;               // store the current ID
        $ID       = $this->page['id']; // change ID to the included page
        // remove document_start and document_end to avoid zipping
        $this->ins = array_slice($this->ins, 1, -1);
        p_render('odt', $this->ins, $info);
        $ID = $backupID;               // restore ID
        // reset defaults
        $this->helper_plugin_include();
    }
}
//vim:ts=4:sw=4:et:enc=utf-8:
