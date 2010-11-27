<?php
require_once(DOKU_INC.'_test/lib/unittest.php');
require_once(DOKU_INC.'inc/init.php');
class plugin_include_nested_test extends Doku_UnitTestCase {
    private $ids = array(
        'test:plugin_include:nested:start',
        'test:plugin_include:nested:second',
        'test:plugin_include:nested:third'
    );

    public function tearDown() {
        foreach($this->ids as $id) {
            saveWikiText($id,'','deleted in tearDown()');
            @unlink(metaFN($id, '.meta'));
        }
    }

    public function setUp() {
        saveWikiText('test:plugin_include:nested:start', 
            '====== Main Test Page ======'.DOKU_LF.DOKU_LF
            .'Main Content'.DOKU_LF.DOKU_LF
            .'{{page>second}}'.DOKU_LF,
            'setup for test');
        saveWikiText('test:plugin_include:nested:second', 
            '====== Second Test Page ======'.DOKU_LF.DOKU_LF
            .'Second Content'.DOKU_LF.DOKU_LF
            .'{{page>third}}'.DOKU_LF,
            'setup for test');
        saveWikiText('test:plugin_include:nested:third', 
            '====== Third Test Page ======'.DOKU_LF.DOKU_LF
            .'Third Content'.DOKU_LF.DOKU_LF
            .'{{page>third}}'.DOKU_LF,
            'setup for test');
    }

    public function testOuterToInner() {
        $mainHTML = p_wiki_xhtml('test:plugin_include:nested:start');
        $secondHTML = p_wiki_xhtml('test:plugin_include:nested:second');
        $thirdHTML = p_wiki_xhtml('test:plugin_include:nested:third');
        $this->_validateContent($mainHTML, $secondHTML, $thirdHTML);
    }

    public function testInnerToOuter() {
        $thirdHTML = p_wiki_xhtml('test:plugin_include:nested:third');
        $secondHTML = p_wiki_xhtml('test:plugin_include:nested:second');
        $mainHTML = p_wiki_xhtml('test:plugin_include:nested:start');
        $this->_validateContent($mainHTML, $secondHTML, $thirdHTML);
    }

    private function _validateContent($mainHTML, $secondHTML, $thirdHTML) {
        $this->assertTrue(strpos($mainHTML, 'Main Content') !== false, 'Main content contains "Main Content"');
        $this->assertTrue($this->_matchHeader('1', 'Main Test Page', $mainHTML), 'Main page header is h1');
        $this->assertTrue(strpos($mainHTML, 'Second Content') !== false, 'Main content contains "Second Content"');
        $this->assertTrue($this->_matchHeader('2', 'Second Test Page', $mainHTML), 'Second page header on main page is h2');
        $this->assertTrue(strpos($mainHTML, 'Third Content') !== false, 'Main content contains "Third Content"');
        $this->assertTrue($this->_matchHeader('3', 'Third Test Page', $mainHTML), 'Third page header on main page is h3');
        $this->assertTrue(strpos($secondHTML, 'Second Content') !== false, 'Second content contains "Second Content"');
        $this->assertTrue($this->_matchHeader('1', 'Second Test Page', $secondHTML), 'Second page header on second page is h1');
        $this->assertTrue(strpos($secondHTML, 'Third Content') !== false, 'Second content contains "Third Content"');
        $this->assertTrue($this->_matchHeader('2', 'Third Test Page', $secondHTML), 'Third page header on second page is h2');
        $this->assertTrue(strpos($thirdHTML, 'Third Content') !== false, 'Third content contains "Third Content"');
        $this->assertTrue($this->_matchHeader('1', 'Third Test Page', $thirdHTML), 'Third page header on third page is h1');
    }

    private function _matchHeader($level, $text, $html) {
        return preg_match('/<h'.$level.'[^>]*><a[^>]*>'.$text.'/', $html) > 0;
    }
}

