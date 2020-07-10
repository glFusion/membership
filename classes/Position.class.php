<?php
/**
 * Class to handle board and committee possitions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2014-2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;

/**
 * Class to handle board and committee positions.
 * @package membership
 */
class Position
{
    /** DB record ID of the position.
     * @var integer */
    private $id = 0;

    /** ID of user currently occupying the posision.
     * @var integer */
    private $uid = 0;

    /** Sequence for ordering in the lists.
     * @var integer */
    private $orderby = 9999;

    /** ID of group where the position's occupant is added.
     * @var integer */
    private $grp_id = 0;

    /** Flag to indicate that this position is enabled.
     * @var integer */
    private $enabled = 1;

    /** Show this position in user-facing lists even if unoccupied?
     * @var integer */
    private $show_vacant = 1;

    /** Type of position. Used to name lists.
     * @var string */
    private $type = '';

    /** Description of the position.
     * @var string */
    private $dscp = '';

    /** Contact info for the position's occupant.
     * @var string */
    private $contact = '';

    /** Original group ID, used when editing to detect changes.
     * @var integer */
    private $old_grp_id = 0;

    /** Original user ID, used when editing to detect changes.
     * @var integer */
    private $old_uid = 0;


    /**
     * Set variables and read a record if an ID is provided.
     *
     * @param   integer $id     Optional ID of existing position record
     */
    public function __construct($id=0)
    {
        if (is_array($id)) {
            $this->setVars($id);
        } elseif ($id > 0) {
            if (!$this->Read($id)) {
                $this->id = 0;
            }
        }
    }


    /**
     * Get the record ID of the position.
     * Used also to see if the position is new or existing.
     *
     * @return  integer     Record ID, zero indicates a new object
     */
    public function getID()
    {
        return (int)$this->id;
    }


    /**
     * Read a position from the database.
     *
     * @param   integer $id     Optional ID, current ID if empty
     * @return  boolean     True on success, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        if ($id == 0) $id = $this->id;
        if ($id == 0) return false;     // need a valid ID
        $id = (int) $id;

        $r = DB_query("SELECT * FROM {$_TABLES['membership_positions']}
                    WHERE id = $id");
        if ($r) {
            $A = DB_fetchArray($r, false);
            if (is_array($A)) {
                $this->setVars($A);
                return true;
            }
        }
        return false;
    }


    /**
     * Set the values from an array into the object members.
     *
     * @param   array   $A          Array of values
     * @param   boolean $fromDB     True if reading from the DB
     * @return  boolean     True on success, False on failure
     */
    public function setVars($A, $fromDB=true)
    {
        if (!is_array($A)) return false;

        if (isset($A['id'])) $this->id = (int)$A['id'];
        $this->uid      = (int)$A['uid'];
        $this->dscp = $A['descr'];
        $this->orderby  = (int)$A['orderby'];
        $this->contact  = $A['contact'];
        $this->grp_id   = (int)$A['grp_id'];
        $this->old_uid  = isset($A['old_uid']) ? (int)$A['old_uid'] : $this->uid;
        $this->old_grp_id  = isset($A['old_grp_id']) ? (int)$A['old_grp_id'] : $this->grp_id;
        $this->enabled  = isset($A['enabled']) ? (int)$A['enabled'] : 0;
        $this->show_vacant = isset($A['show_vacant']) ? (int)$A['show_vacant'] : 0;

        if ($fromDB) {
            $this->type = $A['type'];
        } else {
            if (isset($A['position_type']) && !empty($A['position_type'])) {
                $this->type = $A['position_type'];
            } else {
                $this->type = $A['position_type_sel'];
            }
        }
        return true;
   }


    /**
     * Save the current values to the database.
     *
     * @param   array   $A  Optional array, current values used if empty
     * @return  boolean     True on success, False on failure
     */
    public function Save($A = array())
    {
        global $_TABLES;

        if (is_array($A) && !empty($A)) {
            $this->setVars($A, false);
        }

        if ($this->id == 0) {
            $sql1 = "INSERT INTO {$_TABLES['membership_positions']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['membership_positions']} SET ";
            $sql3 = " WHERE id = {$this->id}";
        }

        $sql2 = "type = '" . DB_escapeString($this->type) . "',
                uid = {$this->uid},
                descr = '" . DB_escapeString($this->dscp) . "',
                contact = '" . DB_escapeString($this->contact) . "',
                show_vacant = {$this->show_vacant},
                orderby = {$this->orderby},
                enabled = {$this->enabled},
                grp_id = {$this->grp_id} ";
        //echo $sql1 . $sql2 . $sql3;die;
        DB_query($sql1 . $sql2 . $sql3, 1);
        if (!DB_error()) {
            self::Reorder($this->type);
            // Check and change group memberships if necessary
            $this->_updateGroups();
            return true;
        }
        return false;
    }


    /**
     * Set the given member as the occupant of a position.
     *
     * @param   integer $uid    User ID to hold the position
     * @return  boolean     Results from Save()
     */
    public function setMember($uid)
    {
        $this->uid = (int)$uid;
        return $this->Save();
    }


    /**
     * Get the positions held by a member.
     *
     * @param   integer $uid    Member's User ID
     * @return  array       Array of position IDs
     */
    public static function getByMember($uid)
    {
        global $_TABLES;

        $retval = array();
        $uid = (int)$uid;
        $sql = "SELECT * FROM {$_TABLES['membership_positions']}
                WHERE uid = '$uid'";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = new self($A);
        }
        return $retval;
    }


    /**
     * Sets a boolean field to the opposite of the supplied value.
     *
     * @param   integer $oldvalue   Old (current) value
     * @param   string  $field      Name of DB field to set
     * @param   integer $id         ID number of element to modify
     * @return         New value, or old value upon failure
     */
    public static function toggle($oldvalue, $field, $id)
    {
        global $_TABLES;

        // If it's still an invalid ID, return the old value
        if ($id == '')
            return $oldvalue;

        switch ($field) {       // sanitize
        case 'enabled':
        case 'show_vacant':
            break;
        default:
            return $oldvalue;
        }

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['membership_positions']}
                SET $field = $newvalue
                WHERE id = " . (int)$id;
        //echo $sql;die;
        DB_query($sql, 1);
        return DB_error() ? $oldvalue : $newvalue;
    }


    /**
     * Remove the current position.
     */
    public function Remove()
    {
        global $_TABLES;

        // First remove the member->group assignment, if any
        $this->uid = 0;
        $this->grp_id = 0;
        $this->_updateGroups();

        // Then delete the position record
        DB_delete($_TABLES['membership_positions'], 'id', $this->id);
    }


    /**
     * Creates the edit form.
     *
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES;

        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file('editform', 'position_form.thtml');
        $T->set_var(array(
            'action_url'    => MEMBERSHIP_ADMIN_URL,
            'id'            => $this->id,
            'description'   => $this->dscp,
            'option_user_select' => COM_optionList(
                        $_TABLES['users'],
                        'uid,fullname',
                        $this->uid, 1
                ),
            'orderby'       => $this->orderby,
            'show_vacant_chk'   => $this->show_vacant ? 'checked="checked"' : '',
            'ena_chk'       => $this->enabled ? 'checked="checked"' : '',
            'position_type_select' => COM_optionList(
                        $_TABLES['membership_positions'],
                        'DISTINCT type,type',
                        $this->type, 0
                ),
            'contact'       => $this->contact,
            'grp_select'    => COM_optionList($_TABLES['groups'],
                            'grp_id,grp_name', $this->grp_id, 1),
            'old_grp_id'    => $this->old_grp_id,
            'old_uid'       => $this->old_uid,
            'doc_url'       => LGLIB_getDocURL('position.html', 'membership'),
         ) );
        return $T->parse('output', 'editform');
    }   // function Edit()


    /**
     * Reorder the positions for admin lists and information pages.
     *
     * @param   string  $type   Type of position (board, committee, etc.)
     */
    public static function Reorder($type)
    {
        global $_TABLES;

        $type = DB_escapeString($type);
        $sql = "SELECT id, orderby FROM {$_TABLES['membership_positions']}
                WHERE type = '$type'
                ORDER BY orderby ASC";
        $result = DB_query($sql);

        $order = 10;
        $stepNumber = 10;

        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $sql = "UPDATE {$_TABLES['membership_positions']}
                    SET orderby = '$order'
                    WHERE id = '{$A['id']}'";
                DB_query($sql, 1);
                if (DB_error()) {
                    return 5;
                }
            }
            $order += $stepNumber;
        }
        return '';
    }


    /**
     * Move a position up or down in the list.
     *
     * @param   integer $id     Record ID to move
     * @param   string  $type   Type of position (board, committee, etc.)
     * @param   string  $where  Direction to move ('up' or 'down')
     */
    public static function Move($id, $type, $where)
    {
        global $_CONF, $_TABLES, $LANG21;

        $retval = '';
        $id = (int)$id;
        $typs = DB_escapeString($type);

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
        $sql = "UPDATE {$_TABLES['membership_positions']}
                SET orderby = orderby $sign 11
                WHERE id = '$id'";
        //echo $sql;die;
        DB_query($sql, 1);

        if (!DB_error()) {
            // Reorder fields to get them separated by 10
            self::Reorder($type);
            $msg = '';
        } else {
            $msg = 5;
        }
        return $msg;
    }


    /**
     * Update group membership based on changes in position.
     * If the group or user ID have changed, remove the old user
     * from the old group and add the new user to the new group
     */
    private function _updateGroups()
    {
        USES_lib_user();

        if (
            $this->old_grp_id != $this->grp_id ||
            $this->old_uid != $this->uid
        ) {
            if ($this->old_grp_id != 0 && $this->old_uid != 0) {
                // used to be a member in this position, now maybe not
                USER_delGroup($this->old_grp_id, $this->old_uid);
            }
            if ($this->grp_id != 0 && $this->uid != 0) {
                // There is a user in this position, add to the group
                USER_addGroup($this->grp_id, $this->uid);
            }
        }
        return $this;
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

        $sql = "SELECT p.*,u.fullname
            FROM {$_TABLES['membership_positions']} p
            LEFT JOIN {$_TABLES['users']} u
            ON u.uid = p.uid";
//            WHERE 1=1 ";

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['edit'],
                'field' => 'editpos',
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
                'text' => $LANG_MEMBERSHIP['enabled'],
                'field' => 'enabled',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_MEMBERSHIP['position_type'],
                'field' => 'type',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['description'],
                'field' => 'descr',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['current_user'],
                'field' => 'fullname',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['order'],
                'field' => 'orderby',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['show_vacant'],
                'field' => 'show_vacant',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'deletepos',
                'sort' => 'false',
                'align' => 'center'
            ),
        );

        $query_arr = array(
            'table' => 'membership_positions',
            'sql' => $sql,
            'query_fields' => array('u.fullname', 'p.descr'),
            'default_filter' => '',
        );
        $defsort_arr = array(
            'field' => 'type,orderby',
            'direction' => 'ASC'
        );
        $filter = '';
        $text_arr = array(
            'form_url' => MEMBERSHIP_ADMIN_URL . '/index.php?positions',
        );

        $options = array(
            'chkdelete' => true,
            'chkfield' => 'id',
        );
        if (!isset($_REQUEST['query_limit'])) {
            $_GET['query_limit'] = 20;
        }

        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink(
            $LANG_MEMBERSHIP['new_position'],
            MEMBERSHIP_ADMIN_URL . '/index.php?editpos=0',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $display .= ADMIN_list(
            'membership_positions',
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
        global $_CONF, $LANG_ACCESS, $LANG_MEMBERSHIP, $_CONF_MEMBERSHIP;

        $retval = '';

        $pi_admin_url = MEMBERSHIP_ADMIN_URL;
        switch($fieldname) {
        case 'editpos':
            $retval = COM_createLink(
                $_CONF_MEMBERSHIP['icons']['edit'],
                MEMBERSHIP_ADMIN_URL . '/index.php?editpos=' . $A['id'],
                array(
                    'class' => 'tooltip',
                    'title' => $LANG_MEMBERSHIP['edit'],
                )
            );
            break;

        case 'move':
            $base_url = MEMBERSHIP_ADMIN_URL .
                '/index.php?type=' . urlencode($A['type']) .
                '&id=' . $A['id'] . '&reorderpos=';
            $retval .= COM_createLink(
                $_CONF_MEMBERSHIP['icons']['arrow-up'],
                $base_url . 'up'
            );
            $retval .= '&nbsp;' . COM_createLink(
                $_CONF_MEMBERSHIP['icons']['arrow-down'],
                $base_url . 'down'
            );
            break;

        case 'deletepos':
            $retval = COM_createLink(
                $_CONF_MEMBERSHIP['icons']['delete'],
                MEMBERSHIP_ADMIN_URL . '/index.php?deletepos=' . $A['id'],
                array(
                    'onclick' => "return confirm('{$LANG_MEMBERSHIP['q_del_item']}');",
                    'class' => 'tooltip',
                    'title' => $LANG_MEMBERSHIP['hlp_delete'],
                )
            );
           break;

        case 'type':
            $retval = COM_createLink(
                $fieldvalue,
                MEMBERSHIP_PI_URL . '/group.php?type=' . $fieldvalue
            );
            break;

        case 'fullname':
            if ($A['uid'] == 0) {
                $retval = '<i>' . $LANG_MEMBERSHIP['vacant'] . '</i>';
            } else {
                $retval = $fieldvalue;
            }
            break;

        case 'enabled':
        case 'show_vacant':
            if ($fieldvalue == 1) {
                $chk = 'checked="checked"';
                $enabled = 1;
            } else {
                $chk = '';
                $enabled = 0;
            }
            $retval = '<input name="' . $fieldname . '_' . $A['id'] .
                '" id="' . $fieldname . '_' . $A['id'] .
                '" type="checkbox" ' . $chk .
                ' title="' . $LANG_MEMBERSHIP['hlp_' . $fieldname] .
                '" class="tooltip" ' .
                'onclick=\'MEMB_toggle(this, "' . $A['id'] . '", "position", "' .
                $fieldname . '", "' . $pi_admin_url . '");\' />' . LB;
            break;

            default:
            $retval = $fieldvalue;
            break;
        }

        return $retval;
    }

}

?>
