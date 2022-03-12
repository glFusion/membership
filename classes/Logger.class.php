<?php
/**
 * General Log class to handle logging functions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;
use Membership\Config;
use glFusion\Log\Log;


/**
 * Log messags to the system or plugin-specific log file.
 * @package membership
 */
class Logger
{
    /**
     * Write a log file entry to the specified file.
     *
     * @param   string  $logentry   Log text to be written
     * @param   string  $logfile    Log filename
     * @param   boolean $system     True if this is an automated system task
     */
    private static function write($logentry, $logfile, $system=false)
    {
        global $_CONF, $_USER, $LANG01;

        if ($logentry == '') {
            return;
        }

        // A little sanitizing
        $logentry = str_replace(
            array('<'.'?', '?'.'>'),
            array('(@', '@)'),
            $logentry
        );
        $timestamp = strftime( '%c' );
        if ($logfile == '') {
            $logfile = Config::PI_NAME . '.log';
        }
        $logfile = $_CONF['path_log'] . $logfile;

        // Can't open the log file?  Return an error
        if (!$file = fopen($logfile, 'a')) {
            Log::write('system', Log::ERROR, "Unable to open $logfile");
            return;
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
                $byuser = $LANG_MEMBERSHIP['system_task'];
            }
            $byuser .= '@' . $_SERVER['REMOTE_ADDR'];
        }

        // Get the user name if it's not anonymous
        if (isset($_USER['uid'])) {
            $byuser = $_USER['uid'] . '-'.
                COM_getDisplayName(
                    $_USER['uid'],
                    $_USER['username'], $_USER['fullname']
                );
        } else {
            $byuser = 'anon';
        }

        $byuser .= '@' . $_SERVER['REMOTE_ADDR'];

        // Write the log entry to the file
        fputs($file, "$timestamp ($byuser) - $logentry\n");
        fclose($file);
    }


    /**
     * Write an entry to the Audit log.
     *
     * @param   string  $msg        Message to log
     * @param   boolean $system     True if this is an automated system task
     */
    public static function Audit($msg, $system=false)
    {
        $logfile = Config::PI_NAME . '.log';
        self::write($msg, $logfile);
    }


    /**
     * Write an entry to the system log.
     * Just a wrapper for the glFusion error log writer.
     *
     * @param   string  $msg        Message to log
     */
    public static function System($msg)
    {
        Log::write('system', Log::ERROR, $msg);
    }


    /**
     * Write a debug log message.
     * Uses the System() function if debug logging is enabled.
     *
     * @param   string  $msg        Message to log
     */
    public static function Debug($msg)
    {
        if ((int)Config::get('log_level') <= 100) {
            self::System('DEBUG: ' . $msg);
        }
    }

}

