<?php
/**
 * Class to handle storang and displaying messages.
 * Saves messages in the database to display to the specified user
 * at a later time.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership\Notifiers;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class to handle messages stored for later display.
 * @package membership
 */
class Popup
{
    const UNIQUE = 1;
    const OVERWRITE = 2;

    /** Target user ID.
     * @var integer */
    private $uid = 1;

    /** Message level, default "info".
     * @var integer */
    private $level = 1;

    /** Plugin-supplied code.
     * @var string */
    private $pi_code = '';

    /** Message title.
     * @var string */
    private $title = '';

    /** Flag for the message to persist or disappear.
     * @var boolean */
    private $persist = 0;

    /** Message text.
     * @var string */
    private $message = '';

    /** Expiration date.
     * @var string */
    private $expires = NULL;

    /** Session ID, set for anonymous users.
     * @var string */
    private $sess_id = '';

    /** Flag indicating only one copy of the message should be stored.
     * @var integer */
    private $unique = 0;

    /**
     * Set default values.
     */
    public function __construct()
    {
        global $LANG_MEMBERSHIP;
        $this->title = $LANG_MEMBERSHIP['system_message'];
        $this->withUid(1);
    }


    /**
     * Check if a specific message exists.
     * Looks for the user ID, session ID and plugin code.
     *
     * @return  string      Message text, empty if not found
     */
    public function exists() : string
    {
        global $_TABLES;

        $criteria = array('uid' => $this->uid);
        if (!empty($this->sess_id)) {
            $criteria['sess_id'] = $this->sess_id;
        }
        if (!empty($this->pi_code)) {
            $criteria['pi_code'] = $this->pi_code;
        }
        $db->getItem(
            $_TABLES['membership_messages'],
            'message',
            $criteria
        );
    }


    /**
     * Store a message in the database that can be retrieved later.
     * This provides a more flexible method for showing popup messages
     * than the numbered-message method.
     *
     * @param   array|string    $args   Message to be displayed, or argument array
     * @param   string  $title      Optional title
     * @param   boolean $persist    True if the message should persist onscreen
     * @param   string  $expires    SQL-formatted expiration datetime
     * @param   string  $pi_code    Name of plugin storing the message
     * @param   integer $uid        ID of the user to view the message
     * @param   boolean $use_sess_id    True to use session ID to retrieve
     */
    public function store() : bool
    {
        global $_USER, $_TABLES;

        if (empty($this->message)) {
            return false;
        }

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        if ($this->unique) {
            $msg_id = (int)$db->getItem(
                $_TABLES['membership_messages'],
                'msg_id',
                array(
                    'uid' => $this->uid,
                    'pi_code' => $this->pi_code,
                )
            );
            if ($msg_id > 0) {
                if ($this->unique) {
                    // Do nothing since this message is already set
                    return true;
                } else {
                    // Update the existing message
                    $qb->update($_TABLES['membership_messages'])
                       ->where('msg_id = :msg_id')
                       ->setParameter('msg_d', $msg_id, Database::INTEGER)
                       ->set('sess_id', ':sess_id')
                       ->set('title', ':title')
                       ->set('message', ':message')
                       ->set('persist', ':persist')
                       ->set('expires', ':expires')
                       ->set('level', ':level');
                }
            }
        } else {
            // Just insert a new, possibly duplicate, message
            $qb->insert($_TABLES['membership_messages'])
               ->setValue('uid', ':uid')
               ->setValue('pi_code', ':pi_code')
               ->setValue('sess_id', ':sess_id')
               ->setValue('title', ':title')
               ->setValue('message', ':message')
               ->setValue('persist', ':persist')
               ->setValue('expires', ':expires')
               ->setValue('level', ':level');
        }
        $qb->setParameter('uid', $this->uid, Database::INTEGER)
           ->setParameter('pi_code', $this->pi_code, Database::STRING)
           ->setParameter('sess_id', $this->sess_id, Database::STRING)
           ->setParameter('title', $this->title, Database::STRING)
           ->setParameter('message', $this->message, Database::STRING)
           ->setParameter('persist', $this->persist, Database::STRING)
           ->setParameter('expires', $this->expires, Database::STRING)
           ->setParameter('level', $this->level, Database::STRING);
        try {
            $stmt = $qb->execute();
            return true;
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Display all messagse for a user.
    *
     * @param   boolean $persist    Keep the message box open? False = fade out
     * @return  string      HTML for message box
     */
    public static function showAll($persist = false)
    {
        global $LANG_MEMBERSHIP;
        $retval = '';

        self::expire();

        $msgs = self::getAll();
        if (empty($msgs)) {
            return '';
        }

        // Include a zero element in case level is undefined
        $levels = array('info', 'success', 'info', 'warning', 'error');
        $persist = false;

        if (count($msgs) == 1) {
            $message = $msgs[0]['message'];
            $title = $msgs[0]['title'];
            $level = $msgs[0]['level'];
            if ($msgs[0]['persist']) $persist = true;
        } else {
            $message = '';
            $title = '';
            $level = 1;     // Start at the "best" level
            foreach ($msgs as $msg) {
                $message .= '<li class="lglmessage">' .
                    $msg['message'] .
                    '</li>';
                // If any message requests "persist", then all persist
                if ($msg['persist']) $persist = true;
                // Set to the highest (worst) error level
                if ($msg['level'] > $level) $level = $msg['level'];
                // First title found in a message gets used instead of default
                if (empty($title) && !empty($msg['title'])) $title = $msg['title'];
            }
            $message = '<ul class="lglmessage">' . $message . '</ul>';
        }
        self::deleteUser();
        // Revert to the system message title if no other title found
        if (empty($title)) $title = $LANG_MEMBERSHIP['system_message'];
        $leveltxt = isset($levels[$level]) ? $levels[$level] : 'info';
        if ($persist) {
            $T = new \Template(Config::get('pi_path') . 'templates');
            $T->set_file('msg', 'sysmessage.thtml');
            $T->set_var(array(
                'leveltxt' => $leveltxt,
                'message' => $message,
            ) );
            $T->parse('output', 'msg');
            return $T->finish($T->get_var('output'));
        } else {
            return COM_showMessageText($message, $title, $persist, $leveltxt);
        }
    }


    /**
     * Retrieve all messages for display.
     * Gets all messages from the DB where the user ID matches for
     * non-anonymous users, OR the session ID matches. This allows a message
     * caused by an anonymous action to be displayed to the user after login.
     *
     * @return  array   Array of messages, title=>message
     */
    public static function getAll() : array
    {
        global $_TABLES, $_USER;

        $messages = array();
        $params = array();
        $values = array();
        $types = array();

        $uid = (int)$_USER['uid'];
        if ($uid > 1) {
            $params[] = 'uid = ?';
            $values[] = $uid;
            $types[] = Database::INTEGER;
        }
        // Get the session ID for messages to anon users. If a message was
        // stored before the user was logged in this will allow them to see it.
        if (!empty($sess_id)) {
            $params[] = 'sess_id = ?';
            $values[] = $sess_id;
            $types[] = Database::STRING;
        }
        $params = implode(' OR ' , $params);
        if (empty($params)) {
            return $messages;
        }

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT title, message, persist, level
                FROM {$_TABLES['membership_messages']}
                WHERE $params
                ORDER BY dt DESC",
                $values,
                $types
            )->fetchAll(Database::ASSOCIATIVE);
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            return $data;
        } else {
            return array();
        }
        return $messages;
    }


    /**
     * Delete expired messages.
     */
    public static function expire() : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $db->conn->executeUpdate(
                "DELETE FROM {$_TABLES['membership_messages']}
                WHERE expires < NOW()"
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Delete a single message.
     * Called by plugins to remove a message placed earlier. At least one of
     * $uid or $pi_code must be present
     *
     * @param   integer $uid    User ID, required, can be zero to ignore
     * @param   string  $pi_code    Optional plugin code value.
     */
    public static function deleteOne(int $uid, ?string $pi_code = NULL) : void
    {
        global $_TABLES;

        $params = array();
        $types = array();
        if ($uid > 0) {
            $params['uid'] = $uid;
            $types[] = Database::INTEGER;
        }
        if (!empty($pi_code)) {
            $params['pi_code'] = $pi_code;
            $types[] = Database::STRING;
        }
        if (empty($params)) {
            return; // this function only deletes specific messages
        }

        $db = Database::getInstance();
        try {
            $db->conn->delete($_TABLES['membership_messages'], $params, $types);
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Delete all messages for the current user.
     * Checks for messages where the session ID matches the current session,
     * or the user ID matches for logged-in users.
     */
    public static function deleteUser() : void
    {
        global $_TABLES, $_USER;

        // delete messages for the user or session that have not expired.
        $uid = (int)$_USER['uid'];
        $params = array('sess_id = ?');
        $values = array(session_id());
        $types = array(Database::STRING);

        if ($uid > 1) {
            $params[] = 'uid = ?';
            $values[] = $uid;
            $types[] = Database::INTEGER;
        }

        $db = Database::getInstance();
        $query = '(' . implode(' OR ', $params) . ')';
        try {
            $db->conn->executeUpdate(
                "DELETE FROM {$_TABLES['membership_messages']} WHERE $query",
                $values,
                $types
            );
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Set the message level (info, error, etc).
     * Several options can be supplied for the level values.
     *
     * @param   string  $level  Message level.
     * @return  object  $this
     */
    public function withLevel($level)
    {
        switch ($level) {
        case 'error':
        case 'err':
        case false:
        case 'alert':
        case 4:
            $this->level = 4;
            break;
        case 'warn':
        case 'warning':
        case 3:
            $this->level = 3;
            break;
        case 'info':
        case 2:
        default:
            $this->level = 2;
            break;
        case 'success':
        case 1:
            $this->level = 1;
            break;
        }
        return $this;
    }


    /**
     * Set the ID of the user who should view the message.
     *
     * @param   integer $uid    User ID
     * @return  object  $this
     */
    public function withUid($uid)
    {
        global $_USER;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $this->uid = (int)$uid;
        $this->withSessId($this->uid < 2);
        return $this;
    }


    /**
     * Set the plugin code.
     * This may be the plugin name or other optional ID.
     *
     * @param   string  $pi_code    Plugin-supplied code
     * @return  object  $this
     */
    public function withPiCode($pi_code)
    {
        $this->pi_code = $pi_code;
        return $this;
    }


    /**
     * Set the flag to determine if the message stays on-screen.
     *
     * @param   boolean $persist    True to persist, False to disappear
     * @return  object  $this
     */
    public function withPersists($persist)
    {
        $this->persist = $persist ? 1 : 0;
        return $this;
    }


    /**
     * Set the message text to display.
     *
     * @param   string  $msg    Message text
     * @return  object  $this
     */
    public function withMessage($msg)
    {
        $this->message = $msg;
        return $this;
    }


    /**
     * Set the message title.
     *
     * @param   string  $title  Title to be displayed
     * @return  object  $this
     */
    public function withTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * Set the expiration date.
     *
     * @param   string  $exp    Expiration Date, YYYY-MM-DD
     * @return  object  $this
     */
    public function withExpires($exp)
    {
        $this->expires = $exp;
        return $this;
    }


    /**
     * Use the session ID, used for anonymous users.
     *
     * @param   boolean $flag   True to use the session ID
     * @return  object  $this
     */
    public function withSessId($flag)
    {
        $this->sess_id = $flag ? session_id() : '';
        return $this;
    }

    public function withUnique(bool $flag) : self
    {
        if ($flag) {
            $this->unique |= self::UNIQUE;
        } else {
            $this->unique -= self::UNIQUE;
        }
        return $this;
    }

    public function withOverwrite(bool $flag) : self
    {
        if ($flag) {
            $this->unique |= self::OVERWRITE;
        } else {
            $this->unique -= self::OVERWRITE;
        }
        return $this;
    }


    /**
     * Get the expiration date to be saved in the database.
     * The default is 4 hours from now for anonymous users.
     *
     * @return  string      Date string to save in the database
     */
    public function getExpiresDB()
    {
        if (empty($this->expires)) {
            if ($this->uid < 2) {
                // Anonymous messages expire in 4 hours
                $expires = 'DATE_ADD(NOW(), INTERVAL 4 HOUR)';
            } else {
                // Member messages exist until viewed
                $expires = "'2037-12-31'";
            }
        } else {
            $expires = "'" . $this->expires . "'";
        }
        return $expires;
    }

}

