<?php

/**
 * Tests the editx support for adapting the syntax of the include plugin
 */
class plugin_include_pagemove_support_test extends DokuWikiTest {
    public function setup() {
        $this->pluginsEnabled[] = 'pagemove';
        $this->pluginsEnabled[] = 'include';
        parent::setup();
    }

    public function test_relative_include() {
        global $ID;
        /** @var $pagemove helper_plugin_pagemove */
        $pagemove = plugin_load('helper', 'pagemove');
        if (!$pagemove) return; // disable the test when pagemove is not installed
        saveWikiText('editx', '{{page>start#start}} %%{{page>start}}%% {{section>wiki:syntax#tables&nofooter}} {{page>:}} {{section>test:start#test}}', 'Testcase created');
        idx_addPage('editx');
        $ID = 'editx';
        $opts['ns']      = '';
        $opts['newname'] = 'editx';
        $opts['newns']   = 'test';
        $pagemove->move_page($opts);
        $this->assertEquals('{{page>:start#start}} %%{{page>start}}%% {{section>wiki:syntax#tables&nofooter}} {{page>:}} {{section>test:start#test}}',rawWiki('test:editx'));
    }

    public function test_rename() {
        global $ID;
        /** @var $pagemove helper_plugin_pagemove */
        $pagemove = plugin_load('helper', 'pagemove');
        if (!$pagemove) return; // disable the test when pagemove is not installed
        saveWikiText('editx', 'Page to rename', 'Testcase create');
        saveWikiText('links', '{{section>links#foo}} {{page>editx}} {{page>:eDitX&nofooter}} {{section>editx#test}} {{page>editx&nofooter}}', 'Testcase created');
        idx_addPage('editx');
        idx_addPage('links');

        $ID = 'editx';
        $opts['ns']      = '';
        $opts['newname'] = 'edit';
        $opts['newns']   = 'test';
        $pagemove->move_page($opts);
        $this->assertEquals('{{section>links#foo}} {{page>test:edit}} {{page>test:edit&nofooter}} {{section>test:edit#test}} {{page>test:edit&nofooter}}', rawWiki('links'));
    }
}