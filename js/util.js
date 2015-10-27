//  $Id: util.js 109 2014-08-15 22:38:32Z root $
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

var MEMB_xmlHttp;
function MEMB_toggle(ckbox, id, type, component, base_url)
{
  if (window.XMLHttpRequest) {
    MEMB_xmlHttp=new XMLHttpRequest()
  } else if (window.ActiveXObject) {
    MEMB_xmlHttp=new ActiveXObject("Microsoft.XMLHTTP")
  }
  if (MEMB_xmlHttp==null) {
    alert ("Browser does not support HTTP Request")
    return
  }

  // value is reversed since we send the oldvalue to ajax
  var oldval = ckbox.checked == true ? 0 : 1;
  var url=base_url + "/ajax.php?action=toggle";
  url=url+"&id="+id;
  url=url+"&type="+type;
  url=url+"&component="+component;
  url=url+"&oldval="+oldval;
  url=url+"&sid="+Math.random();
  MEMB_xmlHttp.onreadystatechange=MEMBstateChanged;
  MEMB_xmlHttp.open("GET",url,true);
  MEMB_xmlHttp.send(null);
}

function MEMBstateChanged()
{
  var newstate;

  if (MEMB_xmlHttp.readyState==4 || MEMB_xmlHttp.readyState=="complete") {
    jsonObj = JSON.parse(MEMB_xmlHttp.responseText)

    // Set the span ID of the updated checkbox
    var spanid = jsonObj.component + "_" + jsonObj.id;
    if (jsonObj.newval == 1) {
        document.getElementById(spanid).checked = true;
    } else {
        document.getElementById(spanid).checked = false;
    }
/*
    xmlDoc=MEMB_xmlHttp.responseXML;
    id = xmlDoc.getElementsByTagName("id")[0].childNodes[0].nodeValue;
    //imgurl = xmlDoc.getElementsByTagName("imgurl")[0].childNodes[0].nodeValue;
    baseurl = xmlDoc.getElementsByTagName("baseurl")[0].childNodes[0].nodeValue;
    type = xmlDoc.getElementsByTagName("type")[0].childNodes[0].nodeValue;
    component = xmlDoc.getElementsByTagName("component")[0].childNodes[0].nodeValue;
    if (xmlDoc.getElementsByTagName("newval")[0].childNodes[0].nodeValue == 1) {
        newval = 1;
        document.getElementById("tog"+type+id).checked = true;
    } else {
        newval = 0;
        document.getElementById("tog"+type+id).checked = false;
    }*/
  }
}

