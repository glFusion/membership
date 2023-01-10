<?php
/**
 * Class to handle user information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * User Information class.
 * @package    membership
 */
class User
{
    /** User ID.
     * @var integer */
    private $uid = 0;

    /** Timestamp when the terms & conditions were accepted.
     * @var integer */
    private $terms_accept = 0;


    /**
     * Constructor.
     * Creates a user object for the requested user ID, or
     * for the current user if no $uid specified.
     *
     * @param   integer $uid    Optional user ID
     */
    public function __construct($uid=0)
    {
        global $_USER;

        if ($uid == 0) {
            $uid = (int)$_USER['uid'];
        }
        $this->Read($uid);
    }


    /**
     * Get an instance of a user record.
     *
     * @since   v1.4.1
     * @param   integer $uid    User ID
     * @return  object          User object
     */
    public static function getInstance($uid=0)
    {
        global $_USERS;
        static $users = array();

        if ($uid == 0) $uid = $_USERS['uid'];
        $uid = (int)$uid;
        //if (!array_key_exists($uid, $users)) {
            $users[$uid] = new self($uid);
        //}
        return $users[$uid];
    }


    /**
     * Sets the user variables from the $info array.
     * Could be from a database record or form variables.
     *
     * @param   array $info Array containing user info fields
     */
    public function setVars($info)
    {
        global $_CONF;

        foreach ($info as $key=>$value) {
            if ($key == 'passwd') {
                continue;
            }
            $this->$key = $value;
        }
        if ($this->fullname == '') $this->fullname = $this->username;
        if (isset($info['language']) && !empty($info['language'])) {
            $this->language = $info['language'];
        } else {
            $this->language = $_CONF['language'];
        }
    }


    /**
     * Reads user information from the database and calls setVars() to set up the variables.
     * Not called if this object is used to represent the current user.
     *
     * @param   integer $uid    User ID to read
     */
    public function Read(int $uid) : void
    {
        global $_TABLES;

        $uid = (int)$uid;
        //$cache_key = 'uid_' . $uid;
        //$A = Cache::get($cache_key);
        $A = NULL;  // temp until user caching works
        if ($A === NULL) {
            $db = Database::getInstance();
            try {
                $A = $db->conn->executeQuery(
                    "SELECT u.*, m.terms_accept
                    FROM {$_TABLES['users']} u
                    LEFT JOIN {$_TABLES['membership_users']} m
                    ON m.uid = u.uid
                    WHERE u.uid = ?",
                    array($uid),
                    array(Database::INTEGER)
                )->fetchAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = array();
            }
            //Cache::set($cache_key, $A, 'users');
        }
        if (!empty($A)) {
            $this->setVars($A);
        }
    }


    /**
     * Check if the terms acceptance is current (within 1 year).
     *
     * @return  integer     1 if accepted, 0 if not
     */
    public function currentTermsAccepted()
    {
        return $this->terms_accept >= (time() - 31536000) ? 1 : 0;
    }


    /**
     * Get the timestamp when the terms were accepted.
     *
     * @return  integer     Unix timestamp value
     */
    public function getTermsAccepted()
    {
        return (int)$this->terms_accept;
    }


    /**
     * Get the name of the current language, minus the character set.
     * Same as COM_getLanguageName() but works on the current user language.
     * Strips the character set from `$_CONF['language']`.
     *
     * @return  string  Language name, e.g. "english"
     */
    public static function getLanguageName($language)
    {
        global $_CONF;

        $retval = '';

        $charset = '_' . strtolower(COM_getCharset());
        if (substr($language, -strlen($charset)) == $charset) {
            $retval = substr($language, 0, -strlen($charset));
        } else {
            $retval = $language;
        }
        return $retval;
    }


    /**
     * Parse a fullname string into component parts.
     *
     * @param   string  $name       Full name to parse
     * @return  mixed       Array of parts, or specified format
     */
    public static function parseName(string $name, ?string $format=NULL)
    {
        $args = array(1 => $name);
        if ($format !== NULL) {
            $args[2] = $format;
        }
        $retval = PLG_callFunctionForOnePlugin(
            'plugin_parseName_lglib',
            $args,
        );
        if (empty($retval)) {
            $p = explode(' ', $name);
            $parts = array();
            $parts['fname'] = $p[0];
            $parts['lname'] = isset($p[1]) ? $p[1] : '';
            if ($format == 'LCF') {
                if (!empty($parts['lname'])) {
                    $retval = $parts['lname'] . ', ' . $parts['fname'];
                } else {
                    $retval = $parts['fname'];
                }
            } else {
                $retval = $parts;
            }
        }
        return $retval;
    }

}
