<?php
/**
 * Class to handle integration with other plugins
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;


/**
 * Plugin integration class.
 * @package    membership
 */
class Integration
{
    protected static $pi_name = '';


    /**
     * Determine if the plugin is installed and integration is enabled.
     *
     * @return  integer     Integration setting if enabled, 0 otherwise.
     */
    public static function isEnabled() : int
    {
        global $_PLUGIN_INFO;

        static $enabled = array();
        if (!isset($enabled[static::$pi_name])) {
            if (!isset($_PLUGIN_INFO[static::$pi_name])) {
                $enabled[static::$pi_name] = 0;
            } else {
                $enabled[static::$pi_name]= Config::get('enable_' . static::$pi_name);
            }
        }
        return $enabled[static::$pi_name];
    }

}
