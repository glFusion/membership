//  Misc utility JS functions for the Membership plugin

// Find all elements sharing a "rel" tag
function MEM_getElementsByRel(node,searchRel,tag)
{
    var relElements = new Array();
    var els = node.getElementsByTagName(tag); // use "*" for all elements
    //var elsLen = els.length;
    //var pattern = new RegExp("\\b"+searchClass+"\\b");
    for (i = 0; i < els.length; i++) {
        if (els[i].hasAttribute("rel") && els[i].attributes.getNamedItem("rel").value == searchRel) {
            relElements.push(els[i]);
        }
    }
    return relElements;
}

// Highlight all elements sharing a "rel" tag.  Used to highlight members
// in member listings to quickly see where their accounts are linked.
function MEM_highlight(id, state)
{
    var cls = "rel_" + id;

    var el = MEM_getElementsByRel(document,cls,'span');
    if (state == 1) {
        var newclass = 'member_highlight';
    } else {
        var newclass = 'member_normal';
    }
    for (i = 0; i < el.length; i++) {
        el[i].className = newclass;
    }
}

var MEMB_toggle = function(cbox, id, type, component) {
    oldval = cbox.checked ? 0 : 1;
    var dataS = {
        "action" : "toggle",
        "id": id,
        "type": type,
        "oldval": oldval,
        "component": component,
    };
    data = $.param(dataS);
    $.ajax({
        type: "POST",
        dataType: "json",
        url: site_admin_url + "/plugins/membership/ajax.php",
        data: data,
        success: function(result) {
            cbox.checked = result.newval == 1 ? true : false;
            try {
                $.UIkit.notify("<i class='uk-icon-check'></i>&nbsp;" + result.statusMessage, {timeout: 1000,pos:'top-center'});
            }
            catch(err) {
                alert(result.statusMessage);
            }
        }
    });
    return false;
};

