<?php
require_once(DOKU_INC.'_test/lib/unittest.php');
class include_plugin_group_test extends Doku_GroupTest {
    function __construct() {
        parent::__construct('include_grouptest');
        $dir = realpath(dirname(__FILE__)).'/';
        $this->addTestFile($dir . 'nested_include.test.php');
    }
}
