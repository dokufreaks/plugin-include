/**
 * Javascript functionality for the include plugin
 */

/**
 * Highlight the included section when hovering over the appropriate include edit button
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Michael Klier <chi@chimeric.de>
 * @author Michael Hamann <michael@content-space.de>
 */
addInitEvent(function(){
    var btns = getElementsByClass('btn_incledit',document,'form');
    for(var i=0; i<btns.length; i++){
        addEvent(btns[i],'mouseover',function(e){
            var container_div = this;
            while (container_div != document && !container_div.className.match(/\bplugin_include_content\b/)) {
                container_div = container_div.parentNode;
            }

            if (container_div != document) {
                container_div.className += ' section_highlight';
            }
        });

        addEvent(btns[i],'mouseout',function(e){
            var secs = getElementsByClass('section_highlight',document,'div');
            for(var j=0; j<secs.length; j++){
                secs[j].className = secs[j].className.replace(/ section_highlight/,'');
            }
        });
    }
});

// vim:ts=4:sw=4:et:
