<?php

/**
 * Tests the editx support for adapting the syntax of the include plugin
 */
class plugin_include_editx_support_test extends DokuWikiTest {
    public function setup() {
        $this->pluginsEnabled[] = 'editx';
        $this->pluginsEnabled[] = 'include';
        parent::setup();
    }

    public function test_relative_include() {
        /** @var $editx action_plugin_editx */
        $editx = plugin_load('action', 'editx');
        if (!$editx) return; // disable the test when editx is not installed
        saveWikiText('editx', '{{page>start#start}} %%{{page>start}}%% {{section>wiki:syntax#tables&nofooter}} {{page>:}} {{section>test:start#test}}', 'Testcase created');
        $opts['confirm'] = true;
        $opts['oldpage'] = 'editx';
        $opts['newpage'] = 'test:editx';
        $editx->_rename_page($opts);
        $this->assertEquals('{{page>:start#start}} %%{{page>start}}%% {{section>wiki:syntax#tables&nofooter}} {{page>:}} {{section>start#test}}',rawWiki('test:editx'));
    }

    public function test_rename() {
        /** @var $editx action_plugin_editx */
        $editx = plugin_load('action', 'editx');
        if (!$editx) return; // disable the test when editx is not installed
        saveWikiText('editx', 'Page to rename', 'Testcase create');
        saveWikiText('links', '{{section>links#foo}} {{page>editx}} {{page>:eDitX&nofooter}} {{section>editx#test}} {{page>editx&nofooter}}', 'Testcase created');
        $references = array_keys(p_get_metadata('links', 'relation references', METADATA_RENDER_UNLIMITED));
        idx_get_indexer()->addMetaKeys('links', 'relation_references', $references);

        $opts['confirm'] = true;
        $opts['oldpage'] = 'editx';
        $opts['newpage'] = 'test:edit';
        $editx->_rename_page($opts);
        $this->assertEquals('{{section>links#foo}} {{page>test:edit}} {{page>test:edit&nofooter}} {{section>test:edit#test}} {{page>test:edit&nofooter}}', rawWiki('links'));
    }
}