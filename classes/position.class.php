<?php
/**
*   Class to handle board and committee possitions.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2014-2016 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/


/**
*   Class to handle board and committee positions
*   @package    membership
*/
class MemPosition
{
    private $properties;

    /**
    *   Constructor.
    *
    *   @param  integer $id     Optional ID of existing position record
    */
    public function __construct($id=0)
    {
        $this->properties = array();
        $this->id= (int)$id;
        $this->orderby = 999;
        $this->enabled = 1;
        $this->show_vacant = 1;
        $this->contact = '';
        $this->uid = 0;
        $this->grp_id = 0;
        $this->old_grp_id = 0;
        $this->old_uid = 0;
        if ($id > 0) {
            if (!$this->Read()) $this->id = 0;
        }
    }


    public function __set($key, $value)
    {
        switch ($key) {
        case 'id':
        case 'uid':
        case 'orderby':
        case 'grp_id':
        case 'old_uid':
        case 'old_grp_id':
            $this->properties[$key] = (int)$value;
            break;
        case 'enabled':
        case 'show_vacant':
            $this->properties[$key] = $value == 1 ? 1 : 0;
            break;
        default:
            $this->properties[$key] = trim($value);
            break;
        }

    }


    public function __get($key)
    {
        if (isset($this->properties[$key]))
            return $this->properties[$key];
        else
            return NULL;
    }


    /**
    *   Read a position from the database
    *
    *   @param  integer $id     Optional ID, current ID if empty
    *   @return boolean     True on success, False on failure
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
                $this->SetVars($A);
                return true;
            }
        }
        return false;
    }


    /**
    *   Set the values from an array into the object members
    *
    *   @param  array   $A  Array of values
    *   @return boolean     True on success, False on failure
    */
    public function SetVars($A, $fromDB=true)
    {
        if (!is_array($A)) return false;
            
        if (isset($A['id'])) $this->id = $A['id'];
        $this->uid      = $A['uid'];
        $this->descr    = $A['descr'];
        $this->enabled  = $A['enabled'];
        $this->show_vacant = $A['show_vacant'];
        $this->orderby  = $A['orderby'];
        $this->contact  = $A['contact'];
        $this->grp_id    = $A['grp_id'];
        $this->ld_uid  = isset($A['old_uid']) ? $A['old_uid'] : $this->uid;
        $this->old_grp_id  = isset($A['old_grp_id']) ? $A['old_grp_id'] : $this->grp_id;

        if ($fromDB) {
            $this->type     = $A['type'];
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
    *   Save the current values to the database
    *
    *   @param  array   $A  Optional array, current values used if empty
    *   @return boolean     True on success, False on failure
    */
    public function Save($A = array())
    {
        global $_TABLES;

        if (is_array($A) && !empty($A)) {
            $this->SetVars($A, false);
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
                descr = '" . DB_escapeString($this->descr) . "',
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

    }   // function Save


    /**
    *   Set the given member as the occupant of a position.
    *
    *   @param  integer $uid    User ID to hold the position
    */
    public function setMember($uid)
    {
        global $_TABLES;

        $this->uid = $uid;
        $this->Save();
    }


    /**
    *   Get the positions held by a member.
    *
    *   @param  integer $id     Member's User ID
    *   @return array       Array of position IDs
    */
    public static function getMemberPositions($uid)
    {
        global $_TABLES;

        $retval = array();
        $uid = (int)$uid;
        $sql = "SELECT id FROM {$_TABLES['membership_positions']}
                WHERE uid = '$uid'";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = $A['id'];
        }
        return $retval;
    }


    /**
    *   Sets a boolean field to the opposite of the supplied value
    *
    *   @param  integer $oldvalue   Old (current) value
    *   @param  string  $varname    Name of DB field to set
    *   @param  integer $id         ID number of element to modify
    *   @return         New value, or old value upon failure
    */
    public function toggle($oldvalue, $field, $id='')
    {
        global $_TABLES;

        // See if we're called with an ID, or expecting to use the
        // current object.
        if ($id == '') {
            if (is_object($this))
                $id = $this->name;
            else
                return;
        }

        // If it's still an invalid ID, return the old value
        if ($id == '')
            return $oldvalue;

        switch ($field) {       // sanitize 
        case 'enabled':
        case 'show_vacant':
            break;
        default:
            return;
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
    *   Remove the current position.
    */
    public function Remove()
    {
        global $_TABLES;

        DB_delete($_TABLES['membership_positions'], 'id', $this->id);
    }


    /**
    *   Creates the edit form.
    *
    *   @return string      HTML for edit form
    */
    public function Edit()
    {
        global $_TABLES;

        $T = MEMBERSHIP_getTemplate('position_form', 'editform');
        $T->set_var(array(
            'action_url'    => MEMBERSHIP_ADMIN_URL,
            'id'            => $this->id,
            'description'   => $this->descr,
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

        $retval .= $T->parse('output', 'editform');

        return $retval;

    }   // function Edit()


    /**
    *   Reorder the positions for admin lists and information pages
    *
    *   @param  string  $type   Type of position (board, committee, etc.)
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
    *   Move a position up or down in the list.
    *
    *   @param  integer $id     Record ID to move
    *   @param  string  $type   Type of position (board, committee, etc.)
    *   @param  string  $where  Direction to move ('up' or 'down')
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
    *   Update group membership based on changes in position.
    *   If the group or user ID have changed, remove the old user
    *   from the old group and add the new user to the new group
    */
    private function _updateGroups()
    {
        if ($this->old_grp_id != $this->grp_id ||
                $this->old_uid != $this->uid) {
            if ($this->old_grp_id != 0 && $this->old_uid != 0) {
                // used to be a member in this position, now maybe not
                USER_delGroup($this->old_grp_id, $this->old_uid);
            }
            if ($this->grp_id != 0 && $this->uid != 0) {
                // There is a user in this position, add to the group
                USER_addGroup($this->grp_id, $this->uid);
            }
        }
    }

}   // class MemPosition

?>
