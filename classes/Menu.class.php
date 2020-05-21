<?php
/**
 * Class to provide headers and menus for the Membership plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;

/**
 * Class to provide page headers and menus.
 * @package membership
 */
class Menu
{
    /**
     * Create the administrator menu.
     *
     * @param   string  $mode   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function Admin($mode='')
    {
        global $_CONF, $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP, $LANG01;

        USES_lib_admin();

        $hlp_txt = MEMB_getVar($LANG_MEMBERSHIP, 'adm_' . $mode);
        $plan_active = false;
        $members_active = false;
        $trans_active = false;
        $pos_active = false;
        $import_active = false;
        $stats_active = false;
        switch($mode) {
        case 'listplans':
            $plan_active = true;
            break;
        case 'positions':
            $pos_active = true;
            break;
        case 'listmembers':
            $members_active = true;
            break;
        case 'listtrans':
            $trans_active = true;
            break;
        case 'stats':
            $stats_active = true;
            break;
        case 'importform':
            $import_active = true;
            break;
        }

        $menu_arr = array(
            array(
                'url' => MEMBERSHIP_ADMIN_URL . '/index.php?listplans=x',
                'text' => $LANG_MEMBERSHIP['list_plans'],
                'active' => $plan_active,
            ),
            array(
                'url' => MEMBERSHIP_ADMIN_URL . '/index.php?listmembers',
                'text' => $LANG_MEMBERSHIP['list_members'],
                'active' => $members_active,
            ),
            array(
                'url' => MEMBERSHIP_ADMIN_URL . '/index.php?listtrans',
                'text' => $LANG_MEMBERSHIP['transactions'],
                'active' => $trans_active,
            ),
            array(
                'url' => MEMBERSHIP_ADMIN_URL . '/index.php?stats',
                'text' => $LANG_MEMBERSHIP['member_stats'],
                'active' => $stats_active,
            ),
            array(
                'url' => MEMBERSHIP_ADMIN_URL . '/index.php?positions',
                'text' => $LANG_MEMBERSHIP['positions'],
                'active' => $pos_active,
            ),
            array(
                'url' => MEMBERSHIP_ADMIN_URL . '/index.php?importform',
                'text' => $LANG_MEMBERSHIP['import'],
                'active' => $import_active,
            ),
            array(
                'url' => $_CONF['site_admin_url'],
                'text' => $LANG01[53],
            ),
        );
        return ADMIN_createMenu($menu_arr, $hlp_txt, plugin_geticon_membership());
    }


    /**
     * Display the site header, with or without blocks according to configuration.
     *
     * @param   string  $title  Title to put in header
     * @param   string  $meta   Optional header code
     * @return  string          HTML for site header, from COM_siteHeader()
     */
    public static function siteHeader($title='', $meta='')
    {
        global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        $retval = '';

        $title = $LANG_MEMBERSHIP['block_title'];
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
     * Display the site footer, with or without blocks as configured.
     *
     * @return  string      HTML for site footer, from COM_siteFooter()
     */
    public static function siteFooter()
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

}

?>


