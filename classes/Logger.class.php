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
     * Write a log file entry to the specified file.
     *
     * @param   string  $logentry   Log text to be written
     * @param   string  $logfile    Log filename
     * @param   boolean $system     True if this is an automated system task
     */
    private static function write($logentry, $logfile, $system=false)
    {
        global $_CONF, $_USER, $LANG01, $_CONF_MEMBERSHIP;

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
            $logfile = $_CONF_MEMBERSHIP['pi_name'] . '.log';
        }
        $logfile = $_CONF['path_log'] . $logfile;

        // Can't open the log file?  Return an error
        if (!$file = fopen($logfile, 'a')) {
            COM_errorLog("Unable to open $logfile");
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
        global $_CONF_MEMBERSHIP;

        $logfile = $_CONF_MEMBERSHIP['pi_name'] . '.log';
        self::write($msg, $logfile);
    }


    /**
     * Write an entry to the system log.
     * Just a wrapper for COM_errorLog().
     *
     * @param   string  $msg        Message to log
     */
    public static function System($msg)
    {
        COM_errorLog($msg);
    }


    /**
     * Write a debug log message.
     * Uses the System() function if debug logging is enabled.
     *
     * @param   string  $msg        Message to log
     */
    public static function Debug($msg)
    {
        global $_CONF_MEMBERSHIP;

        if (isset($_CONF_MEMBERSHIP['log_level']) && (int)$_CONF_MEMBERSHIP['log_level'] <= 100) {
            self::System('DEBUG: ' . $msg);
        }
    }

}

?>
