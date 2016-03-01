<?php
/**
*   Class to manage membership plans
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2011-2015 Lee Garner
*   @package    membership
*   @version    0.1.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/


/**
*   Class for membership plan
*   @package membership
*/
class MembershipPlan
{
    /** Property fields.  Accessed via __set() and __get()
    *   @var array */
    var $properties = array();

    var $isNew;

    /** Array of error messages
    *   @var array */
    var $Errors = array();

    /*  Array of new and renewal fees
    *   @var array */
    var $fees = array();


    /**
    *   Constructor.
    *   Reads in the specified class, if $id is set.  If $id is zero,
    *   then a new entry is being created.
    *
    *   @param integer $id  Optional plan ID
    */
    function __construct($id = '')
    {
        global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        $this->isNew = true;
        $this->plan_id = $id;

        if ($this->plan_id != '') {
            if (!$this->Read($this->plan_id)) {
                $this->plan_id = '';
            }
        } else {
            $this->name = '';
            $this->description = '';
            $this->fees = array();
            $this->enabled = 1;
            $this->upd_links = 0;
            $this->access = 2;  // default to "All Users"
        }
    }


    /**
    *   Set a property's value.
    *
    *   @param  string  $var    Name of property to set.
    *   @param  mixed   $value  New value for property.
    */
    function __set($var, $value='')
    {
        switch ($var) {
        case 'plan_id':
        case 'old_plan_id':
            $this->properties[$var] = COM_sanitizeID($value, false);
            break;

        case 'access':
            // Integer values
            $this->properties[$var] = (int)$value;
            break;

        case 'price':
            // Float values
            $this->properties[$var] = (float)$value;
            break;

        case 'name':
        case 'description':
            // String values
            $this->properties[$var] = trim($value);
            break;

        case 'enabled':
        case 'upd_links':
            // Boolean values
            $this->properties[$var] = $value == 1 ? 1 : 0;
            break;

        default:
            // Undefined values (do nothing)
            break;
        }
    }


    /**
    *   Get the value of a property.
    *
    *   @param  string  $var    Name of property to retrieve.
    *   @return mixed           Value of property, NULL if undefined.
    */
    function __get($var)
    {
        if (isset($this->properties[$var])) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
    *   Sets all variables to the matching values from $rows.
    *
    *   @param  array   $row        Array of values, from DB or $_POST
    *   @param  boolean $fromDB     True if read from DB, false if from $_POST
    */
    public function SetVars($row, $fromDB=false)
    {
        global $_CONF_MEMBERSHIP;

        if (!is_array($row)) return;

        $this->plan_id = $row['plan_id'];
        $this->name = $row['name'];
        $this->description = $row['description'];
        $this->enabled = $row['enabled'];
        $this->upd_links = $row['upd_links'];
        $this->access = $row['access'];

        if ($fromDB) {
            $this->fees = @unserialize($row['fees']);
            $this->old_plan_id = $row['plan_id'];
        } else {
            if ($_CONF_MEMBERSHIP['period_start'] > 0 &&
                     is_array($row['fee'])) {
                $this->fees = $row['fee'];
            } else {
                for ($i = 0; $i < 12; $i++) {
                    $this->fees['new'][$i] = (float)$row['fee_new'];
                    $this->fees['renew'][$i] = (float)$row['fee_renew'];
                }
            }
            $this->fees['fixed'] = (float)$row['fixed_fee'];
        }
    }


    /**
     *  Read a specific record and populate the local values.
     *
     *  @param  integer $id Optional ID.  Current ID is used if zero.
     *  @return boolean     True if a record was read, False on failure
     */
    public function Read($id = '')
    {
        global $_TABLES;

        $id = COM_sanitizeID($id, false);
        if ($id == '') $id = $this->plan_id;
        if ($id == '') {
            $this->error = 'Invalid ID in Read()';
            return false;
        }

        $sql = "SELECT *
               FROM {$_TABLES['membership_plans']}
               WHERE plan_id='$id' ";
        $result = DB_query($sql, 1);
        if (!$result || DB_numRows($result) != 1) {
            return false;
        } else {
            $row = DB_fetchArray($result, false);
            $this->SetVars($row, true);
            $this->isNew = false;
            return true;
        }
    }


    /**
    *   Save the current values to the database.
    *   Appends error messages to the $Errors property.
    *
    *   @param  array   $A      Optional array of values from $_POST
    *   @return boolean         True if no errors, False otherwise
    */
    public function Save($A = '')
    {
        global $_TABLES, $LANG_MEMBERSHIP;

        if (is_array($A)) {
            $this->SetVars($A);
        }

        if ($this->plan_id == '') {
            $this->plan_id = COM_makeSid();
        }

        // Make sure the record has all necessary fields.
        if (!$this->isValidRecord()) {
            return false;
        }

        // Insert or update the record, as appropriate
        if ($this->isNew) {
            MEMBERSHIP_debug('Preparing to save a new product.');
            $sql1 = "INSERT INTO {$_TABLES['membership_plans']} SET ";
            $sql3 = '';
        } else {
            // Updating a plan.  Make sure that if the plan_id is changed it
            // isn't changed to an existing value
            if ($this->plan_id != $this->old_plan_id &&
                DB_count($_TABLES['membership_plans'], 'plan_id', $this->plan_id) > 0) {
               return false;
            }
            $sql1 = "UPDATE {$_TABLES['membership_plans']} SET ";
            $sql3 = " WHERE plan_id = '" . DB_escapeString($this->old_plan_id) .
                     "'";
            MEMBERSHIP_debug('Preparing to update product id ' . $this->plan_id);
        }

        $price = number_format($this->price, 2, '.', '');
        $sql2 = "plan_id = '" . DB_escapeString($this->plan_id) . "',
                name = '" . DB_escapeString($this->name) . "',
                description = '" . DB_escapeString($this->description) . "',
                fees = '" . DB_escapeString(@serialize($this->fees)) . "',
                enabled = '{$this->enabled}',
                upd_links = '{$this->upd_links}',
                access = '{$this->access}'";
        $sql = $sql1 . $sql2 . $sql3;
        //MEMBERSHIP_debug($sql);
        //echo $sql;die;
        DB_query($sql);

        // If the update succeeded and the plan ID changed, or if a transfer
        // plan was selected, rename the plan IDs in the membership table.
        if (!DB_error()) {
            if ($this->xfer_plan != '') {
                USES_membership_class_membership();
                Membership::Transfer($this->plan_id, $this->xfer_plan);
            } elseif ($this->plan_id != $this->old_plan_id) {
                USES_membership_class_membership();
                Membership::Transfer($this->old_plan_id, $this->plan_id);
            }
        }

        MEMBERSHIP_debug('Status of last update: ' . print_r($status,true));
        $msg = $LANG_MEMBERSHIP['update_of_plan'] . ' ' . $this->plan_id . ' ';
        if (!$this->hasErrors()) {
            $retval = true;
            $msg .= $LANG_MEMBERSHIP['succeeded'];
        } else {
            $retval = false;
            $msg .= $LANG_MEMBERSHIP['failed.'];
        }

        LGLIB_storeMessage(array(
            'message' => $msg . $LANG_MEMBERSHIP['succeeded'],
        ) );
        return $retval;
    }


    /**
    *   Delete a plan record from the database
    *
    *   @param  string  $id     Optional plan ID, current object if empty
    *   @return boolean         True on success, False on failure
    */
    public function Delete($id = '', $xfer_plan='')
    {
        global $_TABLES, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        if ($id == '' && is_object($this)) {
            $id = $this->plan_id;
            $this->plan_id = '';
        }
        if (empty($id)) {
            LGLIB_storeMessage(array(
                'message' => $LANG_MEMBERSHIP['msg_missing_id'],
            ) );
            return false;
        }

        if (self::hasMembers($id)) {
            if (!empty($xfer_plan)) {
                USES_membership_class_membership();
                if (!Membership::Transfer($id, $xfer_plan)) {
                    LGLIB_storeMessage(array(
                        'message' => $LANG_MEMBERSHIP['msg_unable_xfer_members'],
                    ) );
                    return false;
                }
            } else {
                LGLIB_storeMessage(array(
                        'message' => $LANG_MEMBERSHIP['msg_plan_has_members'],
                ) );
                return false;
            }
        }
        DB_delete($_TABLES['membership_plans'], 'plan_id', $id);
        LGLIB_storeMessage(array(
            'message' => $LANG_MEMBERSHIP['msg_plan_deleted'],
        ) );
        return true;
    }


    /**
     *  Determines if the current record is valid.
     *
     *  @return boolean     True if ok, False when first test fails.
     */
    function isValidRecord()
    {
        global $LANG_MEMBERSHIP;

        // Check that basic required fields are filled in
        if ($this->name == '') {
            $this->Errors[] = $LANG_MEMBERSHIP['err_name'];
        }

        if ($this->hasErrors()) {
            MEMBERSHIP_debug('Errors encountered: ' . print_r($this->Errors,true));
            return false;
        } else {
            MEMBERSHIP_debug('isValidRecord(): No errors');
            return true;
        }
    }


    /**
     *  Creates the edit form.
     *
     *  @param  integer $id     Optional ID, current record used if zero.
     *  @return string          HTML for edit form
     */
    function Edit($id = '')
    {
        global $_TABLES, $_CONF, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP,
                $LANG24, $LANG_postmodes, $LANG_configselects, $LANG_MONTH,
                $_SYSTEM;

        if ($id != '') {
            // If an id is passed in, then read that record
            if (!$this->Read($id)) {
                return MEMBERSHIP_errorMessage($LANG_MEMBERSHIP['err_plan_id'], 'info');
            }
        }
        $id = $this->plan_id;
        $T = new Template(MEMBERSHIP_PI_PATH . '/templates');

        if (isset($_CONF['advanced_editor']) &&
                $_CONF['advanced_editor'] == 1) {
            $editor_type = '_advanced';
            $postmode_adv = 'selected="selected"';
            $postmode_html = '';
        } else {
            $editor_type = '';
            $postmode_adv = '';
            $postmode_html = 'selected="selected"';
        }

        //$T->set_file(array('product' => "plan_form{$editor_type}.thtml"));
        $T->set_file('product', 'plan_form.thtml');
        $action_url = MEMBERSHIP_ADMIN_URL . '/index.php';
        if ($editor_type == '_advanced') {
            $T->set_var('show_adveditor','');
            $T->set_var('show_htmleditor','none');
        } else {
            $T->set_var('show_adveditor','none');
            $T->set_var('show_htmleditor','');
        }
        $post_options = "<option value=\"html\" $postmode_html>{$LANG_postmodes['html']}</option>";
        $post_options .= "<option value=\"adveditor\" $postmode_adv>{$LANG24[86]}</option>";
        $T->set_var('lang_postmode', $LANG24[4]);
        $T->set_var('post_options',$post_options);
        $T->set_var('change_editormode', 'onchange="change_editmode(this);"');
        $T->set_var('glfusionStyleBasePath', $_CONF['site_url']. '/fckeditor');
        $T->set_var('gltoken_name', CSRF_TOKEN);
        $T->set_var('gltoken', SEC_createToken());
        $T->set_var('site_url', $_CONF['site_url']);

        $T->set_var('mootools', $_SYSTEM['disable_mootools'] ? '' : 'true');

        if ($id != '') {
            $T->set_var('plan_id', $this->plan_id);
            $retval = COM_startBlock($LANG_MEMBERSHIP['edit'] . ': ' . $this->name);
        } else {
            $retval = COM_startBlock($LANG_MEMBERSHIP['new_plan']);
        }

        $T->set_var(array(
            'plan_id'       => $this->plan_id,
            'old_plan_id'   => $this->plan_id,
            'name'          => $this->name,
            'description'   => $this->description,
            'pi_admin_url'  => MEMBERSHIP_ADMIN_URL,
            'pi_url'        => MEMBERSHIP_URL,
            'doc_url'       => MEMBERSHIP_getDocURL('plan_form.html',
                                            $_CONF['language']),
            'ena_chk'       => $this->enabled == 1 ?
                                    'checked="checked"' : '',
            'upd_links_chk' => $this->upd_links == 1 ?
                                    'checked="checked"' : '',
            'new_0'         => sprintf('%.2f', $this->fees['new'][0]),
            'renew_0'       => sprintf('%.2f', $this->fees['renew'][0]),
            'fixed_fee'     => sprintf('%.2f', $this->fees['fixed']),
            'period_start'  => $_CONF_MEMBERSHIP['period_start'],
            'group_options' => COM_optionList($_TABLES['groups'],
                                'grp_id,grp_name', $this->access),
        ) );

        if ($_CONF_MEMBERSHIP['period_start'] > 0) {
            $T->set_var('period_start_text',
                    $LANG_MONTH[$_CONF_MEMBERSHIP['period_start']]);
            for ($i = 1; $i < 12; $i++) {
                $T->set_var(array(
                    'new_' . $i => sprintf('%.2f', $this->fees['new'][$i]),
                    'renew_' . $i => sprintf('%.2f', $this->fees['renew'][$i]),
                ) );
            }
        } else {
            $T->set_var(array(
                'fee_rolling'   => 'true',
                'period_start_text' => $LANG_MEMBERSHIP['any_period'],
            ) );
        }

        if ($this->hasMembers()) {
            $sql = "SELECT plan_id, name
                    FROM {$_TABLES['membership_plans']}
                    WHERE plan_id <> '{$this->plan_id}'";
            $res = DB_query($sql);
            $sel = '';
            while ($A = DB_fetchArray($res, false)) {
                $sel .= '<option value="' . $A['plan_id'] . '">' . $A['name'] .
                    '</option>' . LB;
            }
            $T->set_var(array(
                'has_members'   => 'true',
                'xfer_plan_select' => $sel,
            ) );
        }

        $retval .= $T->parse('output', 'product');

        @setcookie($_CONF['cookie_name'].'fckeditor',
                SEC_createTokenGeneral('advancededitor'),
                time() + 1200, $_CONF['cookie_path'],
                $_CONF['cookiedomain'], $_CONF['cookiesecure']);

        $retval .= COM_endBlock();
        return $retval;

    }   // function Edit()


    /**
    *   Set a boolean field to the specified value.
    *
    *   @param  integer $id ID number of element to modify
    *   @param  integer $value New value to set
    *   @return         New value, or old value upon failure
    */
    function _toggle($oldvalue, $varname, $id)
    {
        global $_TABLES;

        // If it's still an invalid ID, return the old value
        if (empty($id))
            return $oldvalue;

        // Determing the new value (opposite the old)
        $newvalue = $oldvalue == 1 ? 0 : 1;

        $sql = "UPDATE {$_TABLES['membership_plans']}
                SET $varname=$newvalue
                WHERE plan_id='$id'";
        //echo $sql;die;
        DB_query($sql);

        return $newvalue;
    }


    /**
    *   Display the product detail page.
    *
    *   @return string      HTML for product detail
    */
    public function Detail()
    {
        global $_TABLES, $_CONF, $_USER, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        $currency = self::getCurrency();
        $buttons = '';

        // Create product template
        $T = new Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file('detail', 'plan_detail.thtml');

        USES_membership_class_membership();
        $M = new Membership($_USER['uid']);
        if ($M->CanPurchase()) {
            $price = $this->Price($M->isNew);
            $price_txt = COM_numberFormat($price, 2);
            $buttons = implode('&nbsp;&nbsp;', $this->MakeButton($price, $M->isNew));
        } else {
            $buttons = '';
            $price_txt = '';
        }

        $T->set_var(array(
                'pi_url'        => MEMBERSHIP_URL,
                'user_id'       => $_USER['uid'],
                'plan_id'       => $this->plan_id,
                'name'          => $this->name,
                'description'   => PLG_replacetags($this->description),
                'encrypted'     => '',
                'price'         => $price_txt,
                'currency'      => $currency,
                'purchase_btn'  => $buttons,
        ) );
        if (COM_isAnonUser()) {
            // Anonymous must log in to purchase
            $T->set_var('you_expire', $LANG_MEMBERSHIP['must_login']);
            $T->set_var('exp_msg_class', 'alert');
        } elseif ($M->expires >= $_CONF_MEMBERSHIP['today'] &&
                 $M->plan_id == $this->plan_id) {
            $T->set_var('you_expire', sprintf($LANG_MEMBERSHIP['you_expire'],
                    $M->plan_id, $M->expires));
            $T->set_var('exp_msg_class', 'info');
        }
        $T->parse('output', 'detail');
        return $T->finish($T->get_var('output', 'detail'));
    }


    /**
    *   Sets the "enabled" field to the specified value.
    *
    *   @param  integer $id ID number of element to modify
    *   @param  integer $value New value to set
    *   @return         New value, or old value upon failure
    */
    public function toggleEnabled($oldvalue, $id)
    {
        $id = COM_sanitizeID($id);

        if ($id == '') {
            if (is_object($this))
                $id = $this->plan_id;
            else
                return $oldvalue;
        }
        return self::_toggle($oldvalue, 'enabled', $id);
    }


    /**
    *   Determine if this product is mentioned in any purchase records.
    *   Typically used to prevent deletion of product records that have
    *   dependencies.
    *
    *   @return boolean True if used, False if not
    */
    function hasMembers($id = '')
    {
        global $_TABLES;

        if ($id == '') {
            if (is_object($this)) {
                $id = $this->plan_id;
            } else {
                return;
            }
        } else {
            $id = COM_sanitizeID($id, false);
        }

        if (DB_count($_TABLES['membership_members'], 'mem_plan_id', $id) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Create a formatted display-ready version of the error messages.
    *
    *   @return string      Formatted error messages.
    */
    function PrintErrors($text = '')
    {
        $retval = '<span class="alert">';
        if ($text != '') {
            $retval .= '<span class="bold">' . $text . '</span>' . LB;
        }
        $retval .= '<ul>';
        foreach($this->Errors as $key=>$msg) {
            $retval .= "<li>$msg</li>\n";
        }
        return $retval . '</ul></span>' . LB;
    }


    /**
    *   Check if this item has any error messages
    *
    *   @return boolean     True if Errors[] is not empty, false if it is.
    */
    function hasErrors()
    {
        return (!empty($this->Errors));
    }


    /**
    *   Create a purchase-now button.
    *   This plugin only uses one type of button, so that's all that we return.
    *
    *   @param  float   $price  Price for membership
    *   @param  boolean $isnew  True for new membership, false for renewal
    *   @param  string  $return Optional return URL after purchase
    *   @return string      Button code
    */
    public function MakeButton($price, $isnew = false, $return='')
    {
        global $_CONF_MEMBERSHIP;

        $retval = array();
        $is_renewal = $isnew ? 'new' : 'renewal';

        if (MEMBERSHIP_PAYPAL_ENABLED) {
            $vars = array(
                'item_number'   => 'membership:' . $this->plan_id .
                            ':' . $is_renewal,
                'item_name'     => $this->name,
                'short_description' => $this->name,
                'amount'        => sprintf("%5.2f", $price),
                'no_shipping'   => 1,
                'quantity'      => 1,
                'tax'           => 0,
                'btn_type'      => 'pay_now',
                'add_cart'      => true,
                '_ret_url'      => $return,
            );
            $status = LGLIB_invokeService('paypal', 'genButton', $vars,
                    $output, $svc_msg);
            if ($status == PLG_RET_OK && is_array($output)) {
                if (!$_CONF_MEMBERSHIP['allow_buy_now']) {
                    // A little trickery to only allow add-to-cart button
                    $output = array('add_cart' => $output['add_cart']);
                }
                $retval = $output;
            }
        }
        if ($_CONF_MEMBERSHIP['ena_checkpay']) {
            $T = new Template(MEMBERSHIP_PI_PATH . '/templates');
            $T->set_file('checkpay', 'pmt_check_btn.thtml');
            $T->set_var('plan_id', $this->plan_id);
            $retval[] = $T->parse('output', 'checkpay');
        }
        return $retval;
    }


    /**
    *   Get the membership price for new or renewing members effective this
    *   month.
    *
    *   @param  boolean $isNew  Indicate whether new or renewing. Default true.
    *   @param  string  $ptype  Price type. "total", "actual", or "fee".
    *   @return float       Current price
    */
    public function Price($isNew = true, $ptype = 'total')
    {
        if ($ptype == 'fee') {      // Get the processing fee only
            $price = $this->Fee();
        } else {
            $type = $isNew ? 'new' : 'renew';
            $thismonth = date('n');
            $price = (float)$this->fees[$type][$thismonth - 1];
            if ($ptype == 'total') $price += $this->Fee();
        }
        return sprintf('%.02f', $price);
    }


    /**
    *   Get the payment processing fee for this plan.
    *   Allows for the separation of dues and processing fee for manual
    *   payments.
    *
    *   @return float   Processing fee
    */
    public function Fee()
    {
        return (float)$this->fees['fixed'];
    }


    /**
    *   Get the next expiration date.
    *   If memberships are rolling and can be started in any month,
    *   then just add a year to today.
    *   If memberships are for a fixed period, like July - June, then
    *   get the month & day from this year or next
    *
    *   @param  string  $today  Date to use as base (YYYY-MM-DD)
    *   @return string      Expiration date (YYYY-MM-DD)
    */
    public static function calcExpiration($exp = '')
    {
        global $_CONF_MEMBERSHIP;

        USES_lglib_class_datecalc();

        if ($exp == '') $exp = $_CONF_MEMBERSHIP['today'];

        // If a rolling membership period, just add a year to today or
        // the current expiration, whichever is greater.
        if ($_CONF_MEMBERSHIP['period_start'] == 0) {
            // Check if within the grace period.
            if ($exp < $_CONF_MEMBERSHIP['dt_end_grace'])
                $exp = $_CONF_MEMBERSHIP['today'];
            list($exp_year, $exp_month, $exp_day) = explode('-', $exp);
            $exp_year++;
            if ($_CONF_MEMBERSHIP['expire_eom']) {
                $exp_day = Date_Calc::daysInMonth($month, $year);
            }
        } else {
            // If there's a fixed month for renewal, check if the membership
            // is expired. If so, get the most recent past expiration date and
            // add a year. If not yet expired, add a year to the current
            // expiration.
            list($year, $month, $day) = explode('-', $exp);
            list($c_year, $c_month, $c_day) = 
                    explode('-', $_CONF_MEMBERSHIP['today']);
            $exp_month = $_CONF_MEMBERSHIP['period_start'] - 1;
            if ($exp_month == 0) $exp_month = 12;
            $exp_year = $year;
            if ($exp <= $_CONF_MEMBERSHIP['today']) {
                if ($exp_month > $c_month)
                    $exp_year = $c_year - 1;
            }
            $exp_year += 1;
            $exp_day = Date_Calc::daysInMonth($exp_month, $exp_year);
        }
        return sprintf('%d-%02d-%02d', $exp_year, $exp_month, $exp_day);
    }


    /**
    *   Wrapper function for the Paypal plugin's getCurrency() function.
    *
    *   @return string  Currency type, "USD" by default.
    */
    public static function getCurrency()
    {
        static $currency = NULL;
        if ($currency === NULL) {
            $status = LGLIB_invokeService('paypal', 'getCurrency', array(),
                    $output, $svc_msg);
            if ($status == PLG_RET_OK) {
                $currency = $output;
            } else {
                $currency = 'USD';
            }
        }
        return $currency;
    }


    /**
    *   Get the number of days in the given month.
    *
    *   @param integer  $month  Month
    *   @param integer  $year   Year
    *   @return integer     Number of days in month
    */
    private static function X_daysInMonth($month, $year)
    {
        switch ($month) {
        case 4:
        case 6:
        case 9:
        case 11:
            $days = 30;
            break;
        case 2:
            if (($year % 4 == 0 && $year % 100 != 0) || $year % 400 == 0)
                $days = 29;
            else
                $days = 28;
            break;
        default:
            $days = 31;
            break;
        }
        return $days;
    }

}   // class MembershipPlan

?>
