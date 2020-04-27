<?php
/**
 * General Log class to handle logging functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;


/**
 * Log messags to the system or plugin-specific log file.
 * @package membership
 */
class Logger
{

    /**
     * Log a debugging message to the system log.
     *
     * @param   string  $msg    Message to be logged
     */
    public static function debug($msg)
    {
        global $_CONF_MEMBERSHIP;
        if ($_CONF_MEMBERSHIP['debug']) {
            COM_errorLog('MEMBERSHIP DEBUG: ' . $msg);
        }
    }


    /**
     * Log an audit message to the plugin's log file.
     *
     * @param   string  $msg    Message to be logged
     */
    public static function audit($msg)
    {
        global $_CONF, $_CONF_MEMBERSHIP, $_USER, $LANG01, $LANG_MEMBERSHIP;

        if ($msg == '') {
            return '';
        }

        // A little sanitizing
        $msg = str_replace(
            array('<?', '?>'),
            array('(@', '@)'),
            $msg
        );

        $timestamp = $_CONF['_now']->format('c');
        $logfile = $_CONF['path_log'] . $_CONF_MEMBERSHIP['pi_name'] . '.log';

        // Can't open the log file?  Return an error
        if (!$file = fopen($logfile, 'a+')) {
            return $LANG01[33] . $logfile . ' (' . $timestamp . ')<br />' . LB;
        }

        if ($system == false) {
            // Get the user name if it's not anonymous
            if (isset($_USER['uid'])) {
                $byuser = $_USER['uid'] . '-'.
                    COM_getDisplayName(
                        $_USER['uid'],
                        $_USER['username'],
                        $_USER['fullname']
                    );
            } else {
                $byuser = 'anon';
            }
            $byuser .= '@' . $_SERVER['REMOTE_ADDR'];
        } else {
            $byuser = $LANG_MEMBERSHIP['system_task'];
        }

        // Write the log entry to the file
        fputs($file, "$timestamp ($byuser) - $msg\n");
    }

}

