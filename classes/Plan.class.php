<?php
/**
 * Class to manage membership plans.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2015 Lee Garner
 * @package     membership
 * @version     0.1.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;

/**
 * Class for membership plans
 * @package membership
 */
class Plan
{
    /** Property fields.  Accessed via `__set()` and `__get()`.
     * @var array */
    var $properties = array();

    /** Flag to indicate that this is a new record.
     * @var boolean */
    var $isNew;

    /** Array of error messages.
     * @var array */
    var $Errors = array();

    /** Array of new and renewal fees.
     * @var array */
    var $fees = array();


    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id  Optional plan ID
     */
    public function __construct($id = '')
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
            $this->grp_access = 2;  // default to "All Users"
        }
    }


    /**
     * Set a property's value.
     *
     * @param   string  $var    Name of property to set.
     * @param   mixed   $value  New value for property.
     */
    public function __set($var, $value='')
    {
        switch ($var) {
        case 'plan_id':
        case 'old_plan_id':
            $this->properties[$var] = COM_sanitizeID($value, false);
            break;

        case 'grp_access':
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
     * Get the value of a property.
     *
     * @param   string  $var    Name of property to retrieve.
     * @return  mixed           Value of property, NULL if undefined.
     */
    public function __get($var)
    {
        if (isset($this->properties[$var])) {
            return $this->properties[$var];
        } else {
            return NULL;
        }
    }


    /**
     * Sets all variables to the matching values from $rows.
     *
     * @param   array   $row        Array of values, from DB or $_POST
     * @param   boolean $fromDB     True if read from DB, false if from $_POST
     */
    public function setVars($row, $fromDB=false)
    {
        global $_CONF_MEMBERSHIP;

        if (!is_array($row)) return;

        $this->plan_id = $row['plan_id'];
        $this->name = $row['name'];
        $this->description = $row['description'];
        $this->grp_access = $row['grp_access'];
        $this->enabled = isset($row['enabled']) ? $row['enabled'] : 0;
        $this->upd_links = isset($row['upd_links']) ? $row['upd_links'] : 0;

        if ($fromDB) {
            $this->fees = @unserialize($row['fees']);
            $this->old_plan_id = $row['plan_id'];
        } elseif (is_array($row['fee'])) {  // should always be an array from the form
            if ($_CONF_MEMBERSHIP['period_start'] > 0) {
                // Each month has a specified new and renewal fee
                $this->fees = $row['fee'];
            } else {
                // Expand the single new/renewal fee into all 12 months
                $this->fees['new'] = array();
                $this->fees['renew'] = array();
                for ($i = 0; $i < 12; $i++) {
                    $this->fees['new'][$i] = (float)$row['fee']['new'];
                    $this->fees['renew'][$i] = (float)$row['fee']['renew'];
                }
            }
            $this->fees['fixed'] = (float)$row['fixed_fee'];
        } else {
            var_dump($row);die;
        }
    }


    /**
     * Read a specific record and populate the local values.
     *
     * @param  integer $id Optional ID.  Current ID is used if zero.
     * @return boolean     True if a record was read, False on failure
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

        $cache_key = 'plan_' . $id;
        $row = Cache::get($cache_key);
        if ($row === NULL) {
            $sql = "SELECT *
               FROM {$_TABLES['membership_plans']}
               WHERE plan_id='$id' ";
            $result = DB_query($sql, 1);
            if (!$result || DB_numRows($result) != 1) {
                return false;
            } else {
                $row = DB_fetchArray($result, false);
                Cache::set($cache_key, $row, 'plans');
            }
        }
        $this->setVars($row, true);
        $this->isNew = false;
        return true;
    }


    /**
     * Get an instance of a specific membership.
     *
     * @param   string  $plan_id    Plan ID to retrieve
     * @return  object      Plan object
     */
    public static function getInstance($plan_id = '')
    {
        $retval = new self($plan_id);
        return $retval;
    }


    /**
     * Save the current values to the database.
     * Appends error messages to the $Errors property.
     *
     * @param   array   $A      Optional array of values from $_POST
     * @return  boolean         True if no errors, False otherwise
     */
    public function Save($A = '')
    {
        global $_TABLES, $LANG_MEMBERSHIP;

        if (is_array($A)) {
            $this->setVars($A);
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
                grp_access = '{$this->grp_access}'";
        $sql = $sql1 . $sql2 . $sql3;
        //MEMBERSHIP_debug($sql);
        //echo $sql;die;
        DB_query($sql);

        // If the update succeeded and the plan ID changed, or if a transfer
        // plan was selected, rename the plan IDs in the membership table.
        if (!DB_error()) {
            if ($this->xfer_plan != '') {
                Membership::Transfer($this->plan_id, $this->xfer_plan);
            } elseif ($this->plan_id != $this->old_plan_id) {
                Membership::Transfer($this->old_plan_id, $this->plan_id);
            }
            $status = 'OK';
        } else {
            $status = 'Error Saving';
        }

        MEMBERSHIP_debug('Status of last update: ' . print_r($status,true));
        $msg = $LANG_MEMBERSHIP['update_of_plan'] . ' ' . $this->plan_id . ' ';
        if (!$this->hasErrors()) {
            $retval = true;
            $msg .= $LANG_MEMBERSHIP['succeeded'];
            Cache::clear();     // clear all since this might affect memberships
        } else {
            $retval = false;
            $msg .= $LANG_MEMBERSHIP['failed'];
        }
        LGLIB_storeMessage(array(
            'message' => $msg,
        ) );
        return $retval;
    }


    /**
     * Delete a plan record from the database.
     *
     * @param   string  $id     Optional plan ID, current object if empty
     * @param   string  $xfer_plan  Plan to transfer members to, if any
     * @return  boolean         True on success, False on failure
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
        Cache::clear();     // clear all since this might affect memberships
        LGLIB_storeMessage(array(
            'message' => $LANG_MEMBERSHIP['msg_plan_deleted'],
        ) );
        return true;
    }


    /**
     * Determines if the current record is valid.
     *
     * @return  boolean     True if ok, False when first test fails.
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
     * Creates the edit form.
     *
     * @param   integer $id     Optional ID, current record used if zero.
     * @return  string          HTML for edit form
     */
    function Edit($id = '')
    {
        global $_TABLES, $_CONF, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP,
                $LANG24, $LANG_postmodes, $LANG_configselects, $LANG_MONTH;

        if ($id != '') {
            // If an id is passed in, then read that record
            if (!$this->Read($id)) {
                return MEMBERSHIP_errorMessage($LANG_MEMBERSHIP['err_plan_id'], 'info');
            }
        }
        $id = $this->plan_id;

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

        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
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
            'pi_url'        => MEMBERSHIP_PI_URL,
            'doc_url'       => MEMBERSHIP_getDocURL('plan_form.html',
                                            $_CONF['language']),
            'ena_chk'       => $this->enabled == 1 ?
                                    'checked="checked"' : '',
            'upd_links_chk' => $this->upd_links == 1 ?
                                    'checked="checked"' : '',
           'period_start'  => $_CONF_MEMBERSHIP['period_start'],
            'group_options' => '<option value="0">-- ' . $LANG_MEMBERSHIP['none'] .
                                ' --</option>' . COM_optionList($_TABLES['groups'],
                                'grp_id,grp_name', $this->grp_access),
        ) );
        if (isset($this->fees['new'])) {
            $T->set_var('new_0', sprintf('%.2f', $this->fees['new'][0]));
        }
        if (isset($this->fees['renew'])) {
            $T->set_var('renew_0', sprintf('%.2f', $this->fees['renew'][0]));
        }
        if (isset($this->fees['fixed'])) {
            $T->set_var('fixed_fee', sprintf('%.2f', $this->fees['fixed']));
        }

        if ($_CONF_MEMBERSHIP['period_start'] > 0) {
            $T->set_var('period_start_text',
                    $LANG_MONTH[$_CONF_MEMBERSHIP['period_start']]);
            for ($i = 1; $i < 12; $i++) {
                if (isset($this->fees['new'][$i])) {
                    $T->set_var('new_' . $i, sprintf('%.2f', $this->fees['new'][$i]));
                }
                if (isset($this->fees['renew'][$i])) {
                    $T->set_var('renew_' . $i, sprintf('%.2f', $this->fees['renew'][$i]));
                }
            }
        } else {
            $T->set_var(array(
                'fee_rolling'   => 'true',
                'period_start_text' => $LANG_MEMBERSHIP['any_period'],
            ) );
        }

        if (self::hasMembers($this->id)) {
            foreach (self::getPlans() as $P) {
                if ($P->plan_id != $this->id) {
                    $sel .= '<option value="' . $P->plan_id . '">' . $P->name .
                        '</option>' . LB;
                }
            }
            $T->set_var(array(
                'has_members'   => 'true',
                'xfer_plan_select' => $sel,
            ) );
        }

        @setcookie($_CONF['cookie_name'].'fckeditor',
                SEC_createTokenGeneral('advancededitor'),
                time() + 1200, $_CONF['cookie_path'],
                $_CONF['cookiedomain'], $_CONF['cookiesecure']);

        $retval .= $T->parse('output', 'product');
        $retval .= COM_endBlock();
        return $retval;
    }   // function Edit()


    /**
     * Set a boolean field to the specified value.
     *
     * @param   integer $oldvalue   Original value to change
     * @param   string  $varname    Field name to be changed
     * @param   integer $id         ID number of element to modify
     * @return         New value, or old value upon failure
     */
    private static function _toggle($oldvalue, $varname, $id)
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
        Cache::clear();     // clear all since this might affect memberships
        return $newvalue;
    }


    /**
     * Display the product detail page.
     *
     * @return  string      HTML for product detail
     */
    public function Detail()
    {
        global $_TABLES, $_CONF, $_USER, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        $currency = self::getCurrency();
        $buttons = '';

        // Create product template
        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file('detail', 'plan_detail.thtml');

        $M = Membership::getInstance($_USER['uid']);
        if ($M->CanPurchase()) {
            $price = $this->Price($M->isNew);
            $price_txt = COM_numberFormat($price, 2);
            $buttons = implode('&nbsp;&nbsp;', $this->MakeButton($price, $M->isNew()));
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
        } elseif ($M->expires >= MEMBERSHIP_today() &&
                 $M->plan_id == $this->plan_id) {
            $T->set_var('you_expire', sprintf($LANG_MEMBERSHIP['you_expire'],
                    $M->plan_id, $M->expires));
            $T->set_var('exp_msg_class', 'info');
        }
        $T->parse('output', 'detail');
        return $T->finish($T->get_var('output', 'detail'));
    }


    /**
     * Sets the "enabled" field to the specified value.
     *
     * @param   integer $oldvalue   Original value to change
     * @param   integer $id         ID number of element to modify
     * @return         New value, or old value upon failure
     */
    public static function toggleEnabled($oldvalue, $id)
    {
        $id = COM_sanitizeID($id);
        return self::_toggle($oldvalue, 'enabled', $id);
    }


    /**
     * Determine if this product is mentioned in any purchase records.
     * Typically used to prevent deletion of product records that have
     * dependencies.
     *
     * @param   string  $id     Plan ID to check
     * @return  boolean True if used, False if not
     */
    public static function hasMembers($id)
    {
        global $_TABLES;

        if (empty($id)) return false;

        if (DB_count($_TABLES['membership_members'], 'mem_plan_id', DB_escapeString($id)) > 0) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Create a formatted display-ready version of the error messages.
     *
     * @param   string  $text   Text to display
     * @return  string      Formatted error messages.
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
     * Check if this item has any error messages.
     *
     * @return  boolean     True if Errors[] is not empty, false if it is.
     */
    function hasErrors()
    {
        return (!empty($this->Errors));
    }


    /**
     * Create a purchase-now button.
     * This plugin only uses one type of button, so that's all that we return.
     *
     * @param   float   $price  Price for membership
     * @param   boolean $isnew  True for new membership, false for renewal
     * @param   string  $return Optional return URL after purchase
     * @return  string      Button code
     */
    public function MakeButton($price, $isnew = false, $return='')
    {
        global $_CONF_MEMBERSHIP;

        $retval = array();
        $is_renewal = $isnew ? 'new' : 'renewal';

        if (self::ShopEnabled()) {
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
                //'_ret_url'      => $return,
                'unique'        => true,
            );
            $status = LGLIB_invokeService(
                'shop',
                'genButton',
                $vars,
                $output,
                $svc_msg
            );
            if ($status == PLG_RET_OK && is_array($output)) {
                if (self::ShopEnabled()) {
                    // A little trickery to only allow add-to-cart button
                    // if not using buy-now + cart
                    $output = array('add_cart' => $output['add_cart']);
                }
                $retval = $output;
            }
        }
        if ($_CONF_MEMBERSHIP['ena_checkpay']) {
            $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
            $T->set_file('checkpay', 'pmt_check_btn.thtml');
            $T->set_var('plan_id', $this->plan_id);
            $retval[] = $T->parse('output', 'checkpay');
        }
        return $retval;
    }


    /**
     * Get the membership price for new or renewing members effective this month.
     *
     * @param   boolean $isNew  Indicate whether new or renewing. Default true.
     * @param   string  $ptype  Price type. "total", "actual", or "fee".
     * @return  float       Current price
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
     * Get the payment processing fee for this plan.
     * Allows for the separation of dues and processing fee for manual payments.
     *
     * @return  float   Processing fee
     */
    public function Fee()
    {
        return (float)$this->fees['fixed'];
    }


    /**
     * Get the next expiration date.
     * If memberships are rolling and can be started in any month,
     * then just add a year to today.
     * If memberships are for a fixed period, like July - June, then
     * get the month & day from this year or next
     *
     * @param   string  $exp    Current expiration date, default = today
     * @return  string      New Expiration date (YYYY-MM-DD)
     */
    public static function calcExpiration($exp = '')
    {
        global $_CONF_MEMBERSHIP;

        if ($exp == '') $exp = MEMBERSHIP_today();

        // If a rolling membership period, just add a year to today or
        // the current expiration, whichever is greater.
        if ($_CONF_MEMBERSHIP['period_start'] == 0) {
            // Check if within the grace period.
            if ($exp < MEMBERSHIP_dtEndGrace())
                $exp = MEMBERSHIP_today();
            list($exp_year, $exp_month, $exp_day) = explode('-', $exp);
            $exp_year++;
            if ($_CONF_MEMBERSHIP['expire_eom']) {
                $exp_day = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            }
        } else {
            // If there's a fixed month for renewal, check if the membership
            // is expired. If so, get the most recent past expiration date and
            // add a year. If not yet expired, add a year to the current
            // expiration.
            list($year, $month, $day) = explode('-', $exp);
            list($c_year, $c_month, $c_day) =
                    explode('-', MEMBERSHIP_today());
            $exp_month = $_CONF_MEMBERSHIP['period_start'] - 1;
            if ($exp_month == 0) $exp_month = 12;
            $exp_year = $year;
            if ($exp <= MEMBERSHIP_today()) {
                if ($exp_month > $c_month)
                    $exp_year = $c_year - 1;
            }
            $exp_year += 1;
            $exp_day = cal_days_in_month(CAL_GREGORIAN, $exp_month, $exp_year);
        }
        return sprintf('%d-%02d-%02d', $exp_year, $exp_month, $exp_day);
    }


    /**
     * Wrapper function for the Shop plugin's getCurrency() function.
     *
     * @return  string  Currency type, "USD" by default.
     */
    public static function getCurrency()
    {
        global $_CONF_MEMBERSHIP;
        static $currency = NULL;
        if ($currency === NULL) {
            if (self::ShopEnabled()) {
                $currency = PLG_callFunctionForOnePlugin('plugin_getCurrency_shop');
            }
            if ($currency === false) {
                $currency = $_CONF_MEMBERSHIP['currency'];
            }
            if (empty($currency)) $currency = 'USD';
        }
        return $currency;
    }


    /**
     * Get all the plans that can be purchased by the current user.
     *
     * @param   string  $plan_id    Optional specific plan to get
     * @return  array       Array of plan objects
     */
    public static function getPlans($plan_id='')
    {
        global $_TABLES;

        $plans = array();
        $sql = "SELECT plan_id
                FROM {$_TABLES['membership_plans']}
                WHERE enabled = 1 " . SEC_buildAccessSql();
        if (!empty($plan_id)) {
            $sql .= " AND plan_id = '" . DB_escapeString($plan_id) . "'";
        }
        $cache_key = md5($sql);
        $plans = Cache::get($cache_key);
        if ($plans === NULL) {
            $result = DB_query($sql);
            while ($A = DB_fetchArray($result, false)) {
                $plans[$A['plan_id']] = new self($A['plan_id']);
            }
            Cache::set($cache_key, $plans, 'plans');
        }
        return $plans;
    }


    /**
     * Display the membership plans available.
     * Supports autotags in the plan_list.thtml template.
     *
     * @param   string  $show_plan      A single plan_id to show (selected on app)
     * @return  string      HTML for product catalog.
     */
    public static function listPlans($show_plan = '')
    {
        global $_TABLES, $_CONF, $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP,
                $_USER, $_PLUGINS, $_IMAGE_TYPE, $_GROUPS, $_SYSTEM;

        $have_app = App::getInstance($_USER['uid'])->Validate();
        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file('planlist', 'plan_list.thtml');
        if (COM_isAnonUser()) {
            // Anonymous must log in to purchase
            //$T->set_var('you_expire', $LANG_MEMBERSHIP['must_login']);
            //$login_url = "#\" onclick=\"Popup.showModal('loginform',null,null,{'screenColor':'#999999','screenOpacity':.6,'className':'piMembershipLoginForm'});return false;\"";
            $login_url = '#" onclick="document.getElementById(\'loginform\').style.display=\'block\';';
            $T->set_var('login_msg', sprintf($LANG_MEMBERSHIP['must_login'],
                $_CONF['site_url'] . '/users.php?mode=new', $login_url));
            /*$T->set_var('login_msg', sprintf($LANG_MEMBERSHIP['must_login'],
                $_CONF['site_url'] . '/users.php?mode=new',
                '#" onclick="document.getElementById(\'loginform\').style.display=\'block\';'));*/
            $T->set_var('exp_msg_class', 'alert');
            $T->set_var('login_form', SEC_loginform());
            $T->parse('output', 'planlist');
            return $T->finish($T->get_var('output', 'planlist'));
        }

        $Plans = self::getPlans($show_plan);
        if (empty($Plans)) {
            $T->parse('output', 'planlist');
            $retval = $T->finish($T->get_var('output', 'planlist'));
            $retval .= '<p />' . $LANG_MEMBERSHIP['no_plans_avail'];
            return $retval;
        }

        $M = Membership::getInstance();
        if ($M->isNew) {
            // New member, no expiration message
            $T->set_var('you_expire', '');
        } elseif ($M->expires >= MEMBERSHIP_today()) {
            // Let current members know when they expire
            $T->set_var('you_expire', sprintf($LANG_MEMBERSHIP['you_expire'],
                $M->planDescription(), $M->expires));
            if ($_CONF_MEMBERSHIP['early_renewal'] > 0) {
                $T->set_var('early_renewal', sprintf($LANG_MEMBERSHIP['renew_within'],
                    $_CONF_MEMBERSHIP['early_renewal']));
            }
            $T->set_var('exp_msg_class', 'info');
        }
        if (App::isRequired() > MEMBERSHIP_APP_DISABLED) {
            if ($_CONF_MEMBERSHIP['require_app'] == MEMBERSHIP_APP_OPTIONAL) {
                $T->set_var('app_msg',
                    sprintf($LANG_MEMBERSHIP['please_complete_app'],
                            MEMBERSHIP_PI_URL . '/index.php?editapp'));
            } elseif ($_CONF_MEMBERSHIP['require_app'] == MEMBERSHIP_APP_REQUIRED
                && !$have_app) {
                $T->set_var('app_msg',
                    sprintf($LANG_MEMBERSHIP['plan_list_app_footer'],
                            MEMBERSHIP_PI_URL . '/index.php?editapp'));
            }
            // Offer a link to return to update the application
            $T->set_var('footer', $LANG_MEMBERSHIP['return_to_edit']);
        }

        $currency = self::getCurrency();
        $lang_price = $LANG_MEMBERSHIP['price'];

        $T->set_block('planlist', 'PlanBlock', 'PBlock');
        foreach ($Plans as $P) {
            $description = $P->description;
            $price = $P->Price($M->isNew(), 'actual');
            if (self::ShopEnabled()) {
                $fee = $P->Fee();
                $price_total = $price + $fee;
            } else {
                $price_total = $price;
                $fee = 0;
            }
            $buttons = '';
            switch($M->CanPurchase()) {
            case MEMBERSHIP_CANPURCHASE:
                $exp_ts = strtotime($M->expires);
                $exp_format = strftime($_CONF['shortdate'], $exp_ts);
                if ($have_app) {
                    $output = $P->MakeButton($price_total, $M->isNew(),
                        $_CONF_MEMBERSHIP['redir_after_purchase']);
                    if (!empty($output)) {
                        $buttons = implode('', $output);
                    }
                }
                break;
            case MEMBERSHIP_NEED_APP:
                 $buttons = sprintf($LANG_MEMBERSHIP['app_required'], MEMBERSHIP_PI_URL . '/app.php');
                break;
            default:
                $exp_format = '';
                $buttons = '';
            }
            $T->set_var(array(
                'plan_id'   => $P->plan_id,
                'name'      => $P->name,
                'description' => PLG_replacetags($description),
                'exp_date'  => $exp_format,
                'price'     => COM_numberFormat($price_total, 2),
                'price_actual' => COM_numberFormat($price, 2),
                'fee' => $fee > 0 ? COM_numberFormat($fee, 2) : '',
                'encrypted' => '',
                'currency'  => $currency,
                'purchase_btn' => $buttons,
                'lang_price' => $lang_price,
            ) );
            $T->parse('PBlock', 'PlanBlock', true);
        }
        $T->parse('output', 'planlist');
        return PLG_replacetags($T->finish($T->get_var('output', 'planlist')));
    }


    /**
     * Return plan information for the getItemInfo function in functions.inc.
     *
     * @param   string  $what   Array of field names, already exploded
     * @param   array   $options    Additional options
     * @return  array       Array of fieldname=>value
     */
    public function getItemInfo($what, $options = array())
    {
        $retval = array();
        foreach ($what as $fld) {
            switch ($fld) {
            case 'id':
                $retval[$fld] = $this->plan_id;
                break;
            default:
                $retval[$fld] = $this->$fld;
                if ($retval[$fld] === NULL) {
                    $retval[$fld] = '';
                }
                break;
            }
        }
        return $retval;
    }


    /**
     * Determine if the Shop plugin is installed and integration is enabled.
     *
     * @return  boolean     True if the integration is enabled, False if not.
     */
    public static function ShopEnabled()
    {
        global $_PLUGINS, $_CONF_MEMBERSHIP;

        static $enabled = NULL;

        if ($enabled !== NULL) {
            return $enabled;
        }
        $enabled = $_CONF_MEMBERSHIP['enable_shop'];
        if ($enabled) {
            if (!is_array($_PLUGINS) || !in_array('shop', $_PLUGINS)) {
                $enabled = false;
            }
        }
        return $enabled;
    }

}

?>
