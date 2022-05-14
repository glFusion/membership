<?php
/**
 * Class to handle position groups/types such as "Offficer".
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.3.0
 * @since       v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;
use glFusion\Log\Log;
use glfusion\Database\Database;


/**
 * Class to handle board and committee positions.
 * @package membership
 */
class PosGroup
{
    /** DB Record ID.
     * @var integer */
    private $pg_id = 0;

    /** Short name to show in admin lists.
     * @var string */
    private $pg_tag = '';

    /** Descriptive name to show as the page title.
     * @var string */
    private $pg_title = '';

    /** Value to set the order of display.
     * @var integer */
    private $pg_orderby = 0;


    /**
     * Constructor. Set the group name to list.
     *
     * @param   string  $grpname    Group name to list
     */
    public function __construct(?int $pg_id=NULL)
    {
        if (is_integer($pg_id) && $pg_id > 0) {
            $this->Read($pg_id);
        }
    }


    /**
     * Read a record from the database by ID.
     *
     * @param   integer $pg_id  Record ID to retrieve
     * @return  object  $this
     */
    public function Read(int $pg_id) : self
    {
        global $_TABLES;

        if ($pg_id > 0) {
            $db = Database::getInstance();
            try {
                $data = $db->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['membership_posgroups']}
                    WHERE pg_id = ?",
                    array($pg_id),
                    array(Database::INTEGER)
                )->fetch(Database::ASSOCIATIVE);
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
                $data = NULL;
            }
            if (is_array($data)) {
                $this->setVars($data);
            }
        }
        return $this;
    }


    /**
     * Set the properties from the DB record.
     *
     * @param   array   $A  Array of record properties
     * @return  object  $this
     */
    public function setVars($A)
    {
        if (is_array($A)) {
            $this->pg_id = (int)$A['pg_id'];
            $this->pg_tag = $A['pg_tag'];
            $this->pg_title = $A['pg_title'];
            $this->pg_orderby = (int)$A['pg_orderby'];
        }
        return $this;
    }


    /**
     * Get a position group by it's tag.
     *
     * @param   string  $pg_tag     Group tag, e.g. "Officers"
     * @return  object      PosGroup object
     */
    public static function getByTag(string $pg_tag) : self
    {
        global $_TABLES;

        $Obj = new self;
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['membership_posgroups']}
                WHERE pg_tag = ?
                ORDER BY pg_id LIMIT 1",
                array($pg_tag),
                array(Database::STRING)
            )->fetch(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            $data = NULL;
        }
        if (is_array($data)) {
            $Obj->setVars($data);
        }
        return $Obj;
    }


    /**
     * Check if this is a new record.
     *
     * @return  boolean     True if a group ID has not been assigned yet
     */
    public function isNew()
    {
        return $this->pg_id == 0;
    }


    /**
     * Get the record ID for this position group.
     *
     * @return  integer     Record ID
     */
    public function getID() : int
    {
        return (int)$this->pg_id;
    }


    /**
     * Set the group tag value.
     *
     * @param   string  $tag    Group tag
     * @return  object  $this
     */
    public function setTag(string $tag) : self
    {
        $this->pg_tag = $tag;
        return $this;
    }


    /**
     * Create a new position group from the position editor.
     *
     * @param   string  $pg_tag     Group Tag
     * @return  object      Group object
     */
    public static function create(string $pg_tag) : string
    {
        global $_TABLES;

        $Obj = self::getByTag($pg_tag);
        if ($Obj->isNew()) {
            $db = Database::getInstance();
            try {
                $db->conn->executeUpdate(
                    "INSERT INTO {$_TABLES['membership_posgroups']}
                    SET pg_tag = ?",
                    array($pg_tag),
                    array(Database::STRING)
                );
                $retval = $db->conn->lastInsertId();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
                $retval = 0;
            }
        } else {
            // Already exists, just return its ID.
            $retval = $Obj->getID();
        }
        return $retval;
    }


    /**
     * Creates the edit form.
     *
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES;

        $T = new \Template(Config::get('pi_path') . 'templates');
        $T->set_file('editform', 'pg_form.thtml');
        if ($this->pg_orderby > 0) {
            $orderby_sel = $this->pg_orderby - 10;
        } else {
            $orderby_sel = 1;
        }
        $T->set_var(array(
            'action_url'    => Config::get('admin_url'),
            'pg_id'         => $this->pg_id,
            'pg_tag'        => $this->pg_tag,
            'pg_title'      => $this->pg_title,
            'pg_orderby_sel' => COM_optionList(
                $_TABLES['membership_posgroups'],
                'pg_orderby,pg_tag',
                $orderby_sel,
                1,
                "pg_id <> {$this->pg_id}"
            ),
        ) );
        return $T->parse('output', 'editform');
    }


    /**
     * Reorder all records.
     */
    public static function reOrder()
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT pg_id, pg_orderby
                FROM {$_TABLES['membership_posgroups']}
                ORDER BY pg_orderby ASC;"
            )->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            $data = array();
        }
        $order = 10;
        $stepNumber = 10;
        if (is_array($data)) {
            foreach ($data as $A) {
                if ($A['pg_orderby'] != $order) {  // only update incorrect ones
                    try {
                        $db->conn->executeUpdate(
                            "UPDATE {$_TABLES['membership_posgroups']}
                            SET pg_orderby = ? WHERE pg_id = ?",
                            array($order, $A['pg_id']),
                            array(Database::INTEGER, Database::INTEGER)
                        );
                    } catch (\Throwable $e) {
                        // log the error but just keep going
                        Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
                    }
                }
            }
            $order += $stepNumber;
        }
    }


    /**
     * Set the group name if not done in the constructor.
     *
     * @param   string  $grpname    Group name to list
     * @return  object  $this
     */
    public function setGroupName($grpname)
    {
        $this->grpname = $grpname;
        return $this;
    }


    /**
     * Set the show_title flag value.
     *
     * @param   boolean $flag   True to show the title, False to not
     * @return  object  $this
     */
    public function showTitle($flag)
    {
        $this->show_title = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Get the page title created from the group name.
     *
     * @return  string      Page title
     */
    public function getPageTitle()
    {
        return $this->pg_title;
    }


    /**
     * Move a position up or down in the list.
     *
     * @param   integer $id     Record ID to move
     * @param   string  $where  Direction to move ('up' or 'down')
     */
    public static function Move($id, $where)
    {
        global $_CONF, $_TABLES, $LANG21;

        $retval = '';
        $id = (int)$id;

        switch ($where) {
        case 'up':
            $sign = '-';
            break;

        case 'down':
            $sign = '+';
            break;

        default:
            return '';
            break;
        }
        $db = Database::getInstance();
        try {
            $db->conn->executeUpdate(
                "UPDATE {$_TABLES['membership_posgroups']}
                SET pg_orderby = pg_orderby $sign 11 WHERE pg_id = '$id'",
                array($id),
                array(Database::INTEGER)
            );
            self::reOrder();
            $msg = '';
        } catch (\Throwable $e) {
            // log the error but just keep going
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            $msg = '5';
        }
        return $msg;
    }


    /**
     * Save the current values to the database.
     *
     * @param   array   $A  Optional array, current values used if empty
     * @return  boolean     True on success, False on failure
     */
    public function Save(?array $A = NULL) : bool
    {
        global $_TABLES;

        if (is_array($A) && !empty($A)) {
            $this->setVars($A, false);
        }

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        if ($this->pg_id == 0) {
            $qb->insert($_TABLES['membership_posgroups']);
        } else {
            $qb->update($_TABLES['membership_posgroups'])
               ->where('pg_id = :pg_id')
               ->setParameter('pg_id', $this->pg_id, Database::INTEGER);
        }
        try {
            $qb->set('pg_tag', ':pg_tag')
               ->set('pg_title', ':pg_title')
               ->set('pg_orderby', ':pg_orderby')
               ->setParameter('pg_tag', $this->pg_tag, Database::STRING)
               ->setParameter('pg_title', $this->pg_title, Database::STRING)
               ->setParameter('pg_orderby', $this->pg_orderby, Database::STRING)
               ->execute();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            return false;
        }
        self::reOrder();
        if ($this->isNew()) {
            $this->pg_id = $db->conn->lastInsertId();
        }

        return true;
    }


    /**
     * Remove the current position group, and all related positions.
     */
    public static function Delete(int $pg_id) : void
    {
        global $_TABLES;

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['membership_positions']}
                WHERE pg_id = {$pg_id}",
                array($pg_id),
                array(Database::INTEGER)
            )->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
            $data = array();
        }

        if (is_array($data)) {
            foreach ($data as $A) {
                $P = new Position($A);
                $P->Delete();
            }
        }
        // Then delete the position group record
        try {
            $db->conn->delete(
                $_TABLES['membership_posgroups'],
                array('pg_id' => $pg_id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . "(): " . $e->getMessage());
        }
    }


    /**
     * Displays the list of committee and board positions.
     *
     * @return  string  HTML string containing the contents of the ipnlog
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_MEMBERSHIP, $_USER, $LANG_ADMIN;

        USES_lib_admin();

        $sql = "SELECT * FROM {$_TABLES['membership_posgroups']}";
        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'pg_id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['edit'],
                'field' => 'editpg',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_MEMBERSHIP['move'],
                'field' => 'move',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_MEMBERSHIP['position_type'],
                'field' => 'pg_tag',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['description'],
                'field' => 'pg_title',
                'sort' => false,
            ),
        );
        $query_arr = array(
            'table' => 'membership_posgroups',
            'sql' => $sql,
            'query_fields' => array(),
            'default_filter' => '',
        );
        $defsort_arr = array(
            'field' => 'pg_orderby',
            'direction' => 'ASC'
        );
        $filter = '';
        $text_arr = array(
            'form_url' => Config::get('admin_url') . '/index.php?posgroups',
        );
        $options = array(
            'chkdelete' => true,
            'chkfield' => 'pg_id',
        );
        if (!isset($_REQUEST['query_limit'])) {
            $_GET['query_limit'] = 20;
        }

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_MEMBERSHIP['new_pg'],
            Config::get('admin_url') . '/index.php?editpg=0',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $display .= ADMIN_list(
            'membership_pgs',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Determine what to display in the admin list for each position.
     *
     * @param   string  $fieldname  Name of the field, from database
     * @param   mixed   $fieldvalue Value of the current field
     * @param   array   $A          Array of all name/field pairs
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML for the field cell
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $LANG_MEMBERSHIP;

        $retval = '';

        $pi_admin_url = Config::get('admin_url');
        switch($fieldname) {
        case 'editpg':
            $retval = COM_createLink(
                Icon::getHTML('edit'),
                Config::get('admin_url') . '/index.php?editpg=' . $A['pg_id'],
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_MEMBERSHIP['edit'],
                )
            );
            break;

        case 'move':
            $base_url = Config::get('admin_url') .
                '/index.php?id=' . $A['pg_id'] . '&reorderpg=';
            $retval .= COM_createLink(
                Icon::getHTML('arrow-up'),
                $base_url . 'up'
            );
            $retval .= '&nbsp;' . COM_createLink(
                Icon::getHTML('arrow-down'),
                $base_url . 'down'
            );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
