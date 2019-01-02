<?php
/**
 * Class to handle user information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2009-2018 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.4.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;

/**
 * User Information class.
 * @package    membership
 */
class User
{
    /** Internal properties accessed via `__set()` and `__get()`.
    * @var array */
    private $properties;


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
            $uid = $_USER['uid'];
        }
        $this->Read($uid);
    }


    /**
     * Set a local property to the supplied value.
     * Only properties available as options in the switch block are set.
     *
     * @param   string  $key    Name of property to set.
     * @param   mixed   $value  Value to set in property.
     */
    public function __set($key, $value)
    {
        switch ($key) {
        case 'uid':
        case 'level':
        case 'terms_accept':
            $this->properties[$key] = (int)$value;
            break;
        default:
            $this->properties[$key] = trim($value);
            break;
        }
    }


    /**
     * Return the data contained in the specified property.
     * Checks to ensure that the property exists, returns NULL if not.
     *
     * @param   string  $key    Name of property to retrieve.
     * @return  mixed           Content of requested property.
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        } else {
            return NULL;
        }
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
        if (!array_key_exists($uid, $users)) {
            $users[$uid] = new self($uid);
        }
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
        foreach ($info as $key=>$value) {
            $this->$key = $value;
        }
        if ($this->fullname == '') $this->fullname = $this->username;
    }


    /**
     * Reads user information from the database and calls setVars() to set up the variables.
     * Not called if this object is used to represent the current user.
     *
     * @param   integer $uid    User ID to read
     */
    public function Read($uid)
    {
        global $_TABLES;

        $uid = (int)$uid;
        $cache_key = 'uid_' . $uid;
        //$A = Cache::get($cache_key);
        $A = NULL;  // temp until user caching works
        if ($A === NULL) {
            $sql = "SELECT * from {$_TABLES['users']} u
                LEFT JOIN {$_TABLES['membership_users']} m
                ON m.uid = u.uid
                WHERE u.uid=$uid";
            $A = DB_fetchArray(DB_query($sql), false);
            //Cache::set($cache_key, $A, 'users');
        }
        if (!empty($A)) {
            $this->setVars($A);
        }
    }

}   // class User

?>
