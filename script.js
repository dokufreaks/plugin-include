/**
 * Javascript functionality for the include plugin
 */

/**
 * Highlight the included section when hovering over the appropriate include edit button
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Michael Klier <chi@chimeric.de>
 */
addInitEvent(function(){
    var btns = getElementsByClass('btn_incledit',document,'form');
    for(var i=0; i<btns.length; i++){
        addEvent(btns[i],'mouseover',function(e){
            var tgt = e.target;
            if(tgt.form) tgt = tgt.form;
            id = 'plugin_include__' + tgt.id.value;
            var divs = getElementsByClass('plugin_include_content');
            for(var j=0; j<divs.length; j++) {
                if(divs[j].id == id) {
                    divs[j].className += ' section_highlight';
                }
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

// vim:ts:4:sw:4:et:enc=utf-8:
