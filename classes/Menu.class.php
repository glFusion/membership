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
        global $_CONF, $LANG_MEMBERSHIP, $LANG01;

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

        $admin_url = Config::get('admin_url');
        $menu_arr = array(
            array(
                'url' => $admin_url . '/index.php?listplans=x',
                'text' => $LANG_MEMBERSHIP['list_plans'],
                'active' => $plan_active,
            ),
            array(
                'url' => $admin_url . '/index.php?listmembers',
                'text' => $LANG_MEMBERSHIP['list_members'],
                'active' => $members_active,
            ),
            array(
                'url' => $admin_url . '/index.php?listtrans',
                'text' => $LANG_MEMBERSHIP['transactions'],
                'active' => $trans_active,
            ),
            array(
                'url' => $admin_url . '/index.php?stats',
                'text' => $LANG_MEMBERSHIP['member_stats'],
                'active' => $stats_active,
            ),
            array(
                'url' => $admin_url . '/index.php?positions',
                'text' => $LANG_MEMBERSHIP['positions'],
                'active' => $pos_active,
            ),
            array(
                'url' => $admin_url . '/index.php?importform',
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
     * Create the administrator sub-menu for the Position options.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function adminPositions($view='')
    {
        global $LANG_MEMBERSHIP;

        $menu_arr = array(
            array(
                'url'  => MEMBERSHIP_ADMIN_URL . '/index.php?positions',
                'text' => $LANG_MEMBERSHIP['positions'],
                'active' => $view == 'positions' ? true : false,
            ),
            array(
                'url'  => MEMBERSHIP_ADMIN_URL . '/index.php?posgroups',
                'text' => $LANG_MEMBERSHIP['posgroups'],
                'active' => $view == 'posgroups' ? true : false,
            ),
        );
        return self::_makeSubMenu($menu_arr);
    }


    /**
     * Create a submenu using a standard template.
     *
     * @param   array   $menu_arr   Array of menu items
     * @return  string      HTML for the submenu
     */
    private static function _makeSubMenu($menu_arr)
    {
        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('menu', 'submenu.thtml');
        $T->set_block('menu', 'menuItems', 'items');
        $hlp = '';
        foreach ($menu_arr as $mnu) {
            if ($mnu['active'] && isset($mnu['help'])) {
                $hlp = $mnu['help'];
            }
            $url = COM_createLink($mnu['text'], $mnu['url']);
            $T->set_var(array(
                'active'    => $mnu['active'],
                'url'       => $url,
            ) );
            $T->parse('items', 'menuItems', true);
        }
        $T->set_var('help', $hlp);
        $T->parse('output', 'menu');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
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
        global $LANG_MEMBERSHIP;

        $retval = '';

        $title = $LANG_MEMBERSHIP['block_title'];
        switch(Config::get('displayblocks')) {
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
        $retval = '';

        switch(Config::get('displayblocks')) {
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


