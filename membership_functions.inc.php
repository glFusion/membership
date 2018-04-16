<?php
/**
*   Plugin-specific functions for the Membership plugin
*   Load by calling USES_membership_functions()
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2017 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Membership;

/**
*   Show the site header, with or without left blocks according to config.
*
*   @see    COM_siteHeader()
*   @param  string  $subject    Text for page title (ad title, etc)
*   @param  string  $meta       Other meta info
*   @return string              HTML for site header
*/
function siteHeader($subject='', $meta='')
{
    global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

    $retval = '';

    $title = $LANG_MEMBERSHIP['block_title'];
    if ($subject != '')
        $title = $subject . ' : ' . $title;

    switch($_CONF_MEMBERSHIP['displayblocks']) {
    case 2:     // right only
    case 0:     // none
        $retval .= COM_siteHeader('none', $title, $meta);
        break;

    case 1:     // left only
    case 3:     // both
    default :
        $retval .= COM_siteHeader('menu', $title, $meta);
        break;
    }

    return $retval;

}


/**
*   Show the site footer, with or without right blocks according to config.
*
*   @see    COM_siteFooter()
*   @return string              HTML for site footer
*/
function siteFooter()
{
    global $_CONF_MEMBERSHIP;

    $retval = '';

    switch($_CONF_MEMBERSHIP['displayblocks']) {
    case 2 : // right only
    case 3 : // left and right
        $retval .= COM_siteFooter(true);
        break;

    case 0: // none
    case 1: // left only
    default :
        $retval .= COM_siteFooter();
        break;
    }

    return $retval;

}

?>
