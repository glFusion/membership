<?php
/**
 * Class to manage membership plans.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2011-2020 Lee Garner
 * @package     membership
 * @version     0.2.0
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
    /** Flag to indicate that this is a new record.
     * @var boolean */
    private $isNew = true;

    /** Membership plan ID.
     * @var string */
    private $plan_id = '';

    /** Group ID allowed to buy the membership.
     * Default = logged-in users.
     * @var integer */
    private $grp_access = 13;

    /** Plan price.
     * @var float */
    private $price = 0;

    /** Name or short description of plan.
     * @var string */
    private $name = '';

    /** Full text description of plan.
     * @var string */
    private $dscp = '';

    /** Flag to indicate that sales of this plan are allowed.
     * @var boolean */
    private $enabled = 1;

    /** Flag to indicate that related memberships are updated with this one.
     * @var boolean */
    private $upd_links = 0;

    /** Array of error messages.
     * @var array */
    private $Errors = array();

    /** Array of new and renewal fees.
     * @var array */
    private $fees = array();

    /** Notify of impending expiration?
     * May exclude for honorary or other "special" plans.
     * @var boolean */
    private $notify_exp = 1;


    /**
     * Constructor.
     * Reads in the specified class, if $id is set.  If $id is zero,
     * then a new entry is being created.
     *
     * @param   integer $id  Optional plan ID
     */
    public function __construct($id = '')
    {
        global $LANG_MEMBERSHIP;

        $this->plan_id = $id;
        if ($this->plan_id != '') {
            if (!$this->Read($this->plan_id)) {
                $this->plan_id = '';
            }
        } else {
            $this->name = '';
            $this->dscp = '';
            $this->fees = array();
            $this->enabled = 1;
            $this->upd_links = 0;
            $this->grp_access = 2;  // default to "All Users"
        }
    }


    /**
     * Set the plan ID.
     *
     * @param   string  $id     Plan ID
     * @return  object  $this
     */
    private function setPlanID($id)
    {
        $this->plan_id = COM_sanitizeID($value, false);
        return $this;
    }


    /**
     * Get the plan ID.
     *
     * @return  string  Plan ID
     */
    public function getPlanID()
    {
        return $this->plan_id;
    }


    /**
     * Set the group allowed to purchase this plan.
     *
     * @param   integer $grp_id Gropu ID
     * @return  object  $this
     */
    private function setGrpAccess($grp_id)
    {
        $this->grp_access = (int)$grp_id;
        return $this;
    }


    /**
     * Get the ID of the group allowed to purchase this plan.
     *
     * @return  integer     Group ID
     */
    public function getGrpAccess()
    {
        return (int)$this->grp_access;
    }


    /**
     * Set the plan price.
     *
     * @param   float   $price  Plan price
     * @return  object  $this
     */
    private function setPrice($price)
    {
        $this->price = (float)$price;
    }


    /**
     * Get the plan price.
     *
     * @return  float       Plan price
     */
    public function getPrice()
    {
        return (float)$this->price;
    }


    /**
     * Set the short description of the plan.
     *
     * @param   string  $dscp   Short description
     * @return  object  $this
     */
    private function setName($dscp)
    {
        $this->name = $dscp;
    }


    /**
     * Get the short description of the plan.
     *
     * @return  string      Sort description (name)
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Set the full text description of the plan.
     *
     * @param   string  $dscp   Full description
     * @return  object  $this
     */
    private function setDscp($dscp)
    {
        $this->dscp = $dscp;
        return $this;
    }


    /**
     * Get the full text description of the plan.
     *
     * @return  string      Full text description
     */
    public function getDscp()
    {
        return $this->dscp;
    }


    /**
     * Set the `enabled` flag for the plan.
     *
     * @param   boolean $flag   1 if enabled, 0 if not
     * @return  object  $this
     */
    private function setEnabled($flag)
    {
        $this->enabled = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Get the enabled status for the plan.
     *
     * @return  integer     1 if enabled, 0 if not
     */
    public function isEnabled()
    {
        return $this->enabled ? 1 : 0;
    }


    /**
     * Set the flag to update related membership links.
     *
     * @param   boolean $flag   1 if enabled, 0 if not
     * @return  object  $this
     */
    private function setUpdateLinks($flag)
    {
        $this->upd_links = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Check if this is a new record. Also indicates whether a record was read.
     *
     * @return  integer     1 if new, 0 if existing
     */
    public function isNew()
    {
        return $this->isNew ? 1 : 0;
    }


    /**
     * Get the flag to update related membership links.
     * Referred to as a "family plan".
     *
     * @return  integer     1 if enabled, 0 if not
     */
    public function isFamily()
    {
        return $this->upd_links ? 1 : 0;
    }


    /**
     * See if expiration notifications should be sent for this plan.
     *
     * @return  integer     1 to send notification, 0 to suppress
     */
    public function notificationsEnabled()
    {
        return $this->notify_exp ? 1 : 0;
    }


    /**
     * Sets all variables to the matching values from $rows.
     *
     * @param   array   $row        Array of values, from DB or $_POST
     * @param   boolean $fromDB     True if read from DB, false if from $_POST
     */
    public function setVars($row, $fromDB=false)
    {
        if (!is_array($row)) return;

        $this->plan_id = $row['plan_id'];
        $this->name = $row['name'];
        $this->dscp = $row['description'];
        $this->grp_access = (int)$row['grp_access'];
        $this->enabled = isset($row['enabled']) ? (int)$row['enabled'] : 0;
        $this->upd_links = isset($row['upd_links']) ? (int)$row['upd_links'] : 0;
        $this->notify_exp = isset($row['notify_exp']) ? (int)$row['notify_exp'] : 0;

        if ($fromDB) {
            $this->fees = @unserialize($row['fees']);
        } elseif (is_array($row['fee'])) {  // should always be an array from the form
            if (Config::get('period_start') > 0) {
                // Each month has a specified new and renewal fee
                $this->fees = $row['fee'];
            } else {
                // Expand the single new/renewal fee into all 12 months
                $this->fees['new'] = array();
                $this->fees['renew'] = array();
                for ($i = 0; $i < 12; $i++) {
                    $this->fees['new'][$i] = (float)$row['fee']['new'][1];
                    $this->fees['renew'][$i] = (float)$row['fee']['renew'][1];
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
     * Get an instance of a specific membership plan.
     *
     * @param   string  $plan_id    Plan ID to retrieve
     * @return  object      Plan object
     */
    public static function getInstance($plan_id = '')
    {
        static $plans = array();

        if (!isset($plans[$plan_id])) {
            $plans[$plan_id] = new self($plan_id);
        }
        return $plans[$plan_id];
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

        $old_plan_id = $this->plan_id;
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
            Logger::debug('Preparing to save a new product.');
            $sql1 = "INSERT INTO {$_TABLES['membership_plans']} SET ";
            $sql3 = '';
        } else {
            // Updating a plan.  Make sure that if the plan_id is changed it
            // isn't changed to an existing value
            if (
                $this->plan_id != $old_plan_id &&
                DB_count($_TABLES['membership_plans'], 'plan_id', $this->plan_id) > 0
            ) {
               return false;
            }
            $sql1 = "UPDATE {$_TABLES['membership_plans']} SET ";
            $sql3 = " WHERE plan_id = '" . DB_escapeString($old_plan_id) .
                     "'";
            Logger::debug('Preparing to update product id ' . $this->plan_id);
        }

        $price = number_format($this->price, 2, '.', '');
        $sql2 = "plan_id = '" . DB_escapeString($this->plan_id) . "',
                name = '" . DB_escapeString($this->name) . "',
                description = '" . DB_escapeString($this->dscp) . "',
                fees = '" . DB_escapeString(@serialize($this->fees)) . "',
                enabled = '{$this->enabled}',
                upd_links = '{$this->upd_links}',
                notify_exp = '{$this->notify_exp}',
                grp_access = '{$this->grp_access}'";
        $sql = $sql1 . $sql2 . $sql3;
        //Logger::debug($sql);
        //echo $sql;die;
        DB_query($sql);

        // If the update succeeded and the plan ID changed,
        // rename the plan IDs in the membership table.
        if (!DB_error()) {
            if ($this->plan_id != $old_plan_id) {
                Membership::Transfer($old_plan_id, $this->plan_id);
            }
            $status = 'OK';
        } else {
            $status = 'Error Saving';
        }

        Logger::debug('Status of last update: ' . print_r($status,true));
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
        global $_TABLES, $LANG_MEMBERSHIP;

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
    public function isValidRecord()
    {
        global $LANG_MEMBERSHIP;

        // Check that basic required fields are filled in
        if ($this->name == '') {
            $this->Errors[] = $LANG_MEMBERSHIP['err_name'];
        }

        if ($this->hasErrors()) {
            Logger::debug('Errors encountered: ' . print_r($this->Errors,true));
            return false;
        } else {
            Logger::debug('isValidRecord(): No errors');
            return true;
        }
    }


    /**
     * Creates the edit form.
     *
     * @return  string          HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $LANG_MEMBERSHIP,
                $LANG24, $LANG_postmodes, $LANG_configselects, $LANG_MONTH;

        $T = new \Template(Config::get('pi_path') . 'templates');
        $T->set_file('product', 'plan_form.thtml');
        if ($this->plan_id != '') {
            $T->set_var('plan_id', $this->plan_id);
            $retval = COM_startBlock($LANG_MEMBERSHIP['edit'] . ': ' . $this->name);
        } else {
            $retval = COM_startBlock($LANG_MEMBERSHIP['new_plan']);
        }

        $T->set_var(array(
            'plan_id'       => $this->plan_id,
            'old_plan_id'   => $this->plan_id,
            'name'          => $this->name,
            'description'   => $this->dscp,
            'pi_admin_url'  => Config::get('admin_url'),
            'pi_url'        => Config::get('url'),
            'doc_url'       => MEMBERSHIP_getDocURL('plan_form.html',
                                            $_CONF['language']),
            'ena_chk'       => $this->enabled == 1 ?
                                    'checked="checked"' : '',
            'upd_links_chk' => $this->upd_links == 1 ?
                                    'checked="checked"' : '',
            'notify_chk'    => $this->notify_exp == 1 ?
                                    'checked="checked"' : '',
            'period_start'  => Config::get('period_start'),
            'group_options' => COM_optionList(
                $_TABLES['groups'],
                'grp_id,grp_name',
                $this->grp_access
            ),
            'grp_0_select'  => $this->grp_access == 0 ? 'selected="selected"' : '',
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

        if (Config::get('period_start') > 0) {
            $fee_rows = 12;
            $text_1 = $LANG_MONTH[1];
        } else {
            $fee_rows = 1;
            $text_1 = $LANG_MEMBERSHIP['any_period'];
        }
        $T->set_block('feetable', 'FeeTable', 'FTable');
        for ($i = 1; $i < $fee_rows+1; $i++) {
            $T->set_var(array(
                'text'      =>  $i == 1 ? $text_1 : $LANG_MONTH[$i],
                'counter'   => $i,
            ) );
            if (isset($this->fees['new'][$i])) {
                $T->set_var('new_fee', sprintf('%.2f', $this->fees['new'][$i]));
            }
            if (isset($this->fees['renew'][$i])) {
                $T->set_var('renew_fee', sprintf('%.2f', $this->fees['renew'][$i]));
            }
            $T->parse('FTable', 'FeeTable', true);
        }

        if ($this->hasMembers()) {
            $sel = '';
            foreach (self::getPlans() as $P) {
                if ($P->getPlanID() != $this->plan_id) {
                    $sel .= '<option value="' . $P->getPlanID() . '">' . $P->getName() .
                        '</option>' . LB;
                }
            }
            $T->set_var(array(
                'has_members'   => 'true',
                'xfer_plan_select' => $sel,
            ) );
        }

        /*@setcookie(
            $_CONF['cookie_name'].'fckeditor',
            SEC_createTokenGeneral('advancededitor'),
            time() + 1200, $_CONF['cookie_path'],
            $_CONF['cookiedomain'],
            $_CONF['cookiesecure']
        );*/

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
        global $_TABLES, $_CONF, $_USER, $LANG_MEMBERSHIP;

        $currency = self::getCurrency();
        $buttons = '';

        // Create product template
        $T = new \Template(Config::get('pi_path') . 'templates');
        $T->set_file('detail', 'plan_detail.thtml');

        $M = Membership::getInstance($_USER['uid']);
        if ($M->CanPurchase()) {
            $price = $this->Price($M->isNew());
            $price_txt = COM_numberFormat($price, 2);
            $buttons = implode('&nbsp;&nbsp;', $this->MakeButton($price, $M->isNew()));
        } else {
            $buttons = '';
            $price_txt = '';
        }

        $T->set_var(array(
            'pi_url'        => Config::get('url'),
            'user_id'       => $_USER['uid'],
            'plan_id'       => $this->plan_id,
            'name'          => $this->name,
            'description'   => PLG_replacetags($this->dscp),
            'encrypted'     => '',
            'price'         => $price_txt,
            'currency'      => $currency,
            'purchase_btn'  => $buttons,
        ) );
        if (COM_isAnonUser()) {
            // Anonymous must log in to purchase
            $T->set_var('you_expire', $LANG_MEMBERSHIP['must_login']);
            $T->set_var('exp_msg_class', 'alert');
        } elseif ($M->getPlanID() == $this->plan_id) {
            if ($M->getExpires() >= Dates::Today()) {
                $T->set_var(
                    'you_expire',
                    sprintf($LANG_MEMBERSHIP['you_expire'], $M->getPlanID(), $M->getExpires())
                );
                $T->set_var('exp_msg_class', 'info');
            } else {
                $T->set_var(
                    'you_expire',
                    sprintf($LANG_MEMBERSHIP['curr_plan_expired'], $M->getExpires())
                );
            }
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
     * @return  boolean True if used, False if not
     */
    public function hasMembers()
    {
        global $_TABLES;

        if (empty($this->plan_id)) return false;

        if (DB_count(
            $_TABLES['membership_members'],
            'mem_plan_id',
            DB_escapeString($this->plan_id)
        ) > 0) {
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
    public function PrintErrors($text = '')
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
    public function hasErrors()
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
                //'btn_type'      => 'pay_now',
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
        if (Config::get('ena_checkpay')) {
            $T = new \Template(Config::get('pi_path') . 'templates');
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
     * Wrapper function for the Shop plugin's getCurrency() function.
     *
     * @return  string  Currency type, "USD" by default.
     */
    public static function getCurrency()
    {
        static $currency = NULL;
        if ($currency === NULL) {
            if (self::ShopEnabled()) {
                $currency = PLG_callFunctionForOnePlugin('plugin_getCurrency_shop');
            }
            if ($currency === false) {
                $currency = Config::get('currency');
            }
            if (empty($currency)) $currency = 'USD';
        }
        return $currency;
    }


    /**
     * Get all the plans that can be purchased by the current user.
     *
     * @param   string  $plan_id    Optional specific plan to get
     * @param   boolean $admin      True to disregard group access
     * @return  array       Array of plan objects
     */
    public static function getPlans(?string $plan_id=NULL, ?bool $admin = NULL) : array
    {
        global $_TABLES, $_GROUPS;

        $plans = array();
        $sql = "SELECT plan_id
                FROM {$_TABLES['membership_plans']}
                WHERE enabled = 1";
        if (!$admin) {
            $groups = $_GROUPS;
            if (!in_array(13, $groups)) {
                $groups[] = 13;
            }
            $groups = implode(',', $groups);
            $sql .= " AND grp_access IN ($groups)";
        }
        if (!empty($plan_id)) {
            $sql .= " AND plan_id = '" . DB_escapeString($plan_id) . "'";
        }
        //echo $sql;die;
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
    public static function listPlans(?string $show_plan = NULL) : string
    {
        global $_TABLES, $_CONF, $LANG_MEMBERSHIP, $_USER;

        $have_app = App::getInstance($_USER['uid'])->Validate();
        /*if (!$have_app) {
            COM_refresh(Config::get('url') . '/index.php?editapp');
        }*/
        $T = new \Template(Config::get('pi_path') . 'templates');
        $T->set_file('planlist', 'plan_list.thtml');
        if (0) {
        if (COM_isAnonUser()) {
            // Anonymous must log in to purchase
            $login_url = '#" onclick="document.getElementById(\'loginform\').style.display=\'block\';';
            $T->set_var('login_msg', sprintf(
                $LANG_MEMBERSHIP['must_login'],
                $_CONF['site_url'] . '/users.php?mode=new', $login_url
            ));
            $T->set_var('exp_msg_class', 'alert');
            $T->set_var('login_form', SEC_loginform());
            $T->parse('output', 'planlist');
            return $T->finish($T->get_var('output', 'planlist'));
        }
        }

        $Plans = self::getPlans($show_plan);
        if (empty($Plans)) {
            $T->set_var('no_plans', true);
            $T->parse('output', 'planlist');
            $retval = $T->finish($T->get_var('output', 'planlist'));
            return $retval;
        }

        $M = Membership::getInstance();
        if ($M->isNew()) {
            // New member, no expiration message
            $T->set_var('you_expire', '');
        } elseif ($M->getExpires() >= Dates::Today()) {
            // Let current members know when they expire
            $T->set_var(
                'you_expire', sprintf(
                    $LANG_MEMBERSHIP['you_expire'],
                    $M->planDescription(),
                    $M->getExpires()
                )
            );
            if (Config::get('early_renewal') > 0) {
                $T->set_var(
                    'early_renewal', sprintf(
                        $LANG_MEMBERSHIP['renew_within'],
                        Config::get('early_renewal')
                    )
                );
            }
            $T->set_var('exp_msg_class', 'info');
        }
        if (COM_isAnonUser()) {
            $T->set_var('app_msg', $LANG_MEMBERSHIP['must_login']);
        } elseif (App::isRequired() > App::DISABLED) {
            if (Config::get('require_app') == App::OPTIONAL) {
                $T->set_var('app_msg',
                    sprintf($LANG_MEMBERSHIP['please_complete_app'],
                            Config::get('url') . '/index.php?editapp'));
            } elseif (
                Config::get('require_app') == App::REQUIRED &&
                !$have_app
            ) {
                $T->set_var('app_msg',
                    sprintf(
                        $LANG_MEMBERSHIP['plan_list_app_footer'],
                        Config::get('url') . '/index.php?editapp'
                    )
                );
            }
            // Offer a link to return to update the application
            $T->set_var('footer', $LANG_MEMBERSHIP['return_to_edit']);
        }

        $currency = self::getCurrency();

        $T->set_block('planlist', 'PlanBlock', 'PBlock');
        foreach ($Plans as $P) {
            $description = $P->getDscp();
            if ($M->getPlanID() == $P->getPlanID()) {
                if ($M->getExpires()< Dates::Today()) {
                    $T->set_var(
                        'cur_plan_msg',
                        sprintf($LANG_MEMBERSHIP['curr_plan_expired'], $M->getExpires())
                    );
                }
            } else {
                $T->clear_var('cur_plan_msg');
            }
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
            case Membership::CANPURCHASE:
                $exp_ts = strtotime($M->getExpires());
                $exp_format = strftime($_CONF['shortdate'], $exp_ts);
                if ($have_app) {
                    $output = $P->MakeButton(
                        $price_total,
                        $M->isNew(),
                        Config::get('redir_after_purchase')
                    );
                    if (!empty($output)) {
                        $buttons = implode('', $output);
                    }
                }
                break;
            case Membership::NEED_APP:
                echo "NEED_APP required";die;
                $buttons = sprintf(
                    $LANG_MEMBERSHIP['app_required'],
                    Config::get('url') . '/app.php'
                );
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
                'lang_price' => $LANG_MEMBERSHIP['price'],
            ) );
            $T->parse('PBlock', 'PlanBlock', true);
        }
        $T->parse('output', 'planlist');
        return PLG_replacetags($T->finish($T->get_var('output', 'planlist')));
    }


    /**
     * Return plan information for the getItemInfo function in functions.inc.
     *
     * @param   array $what   Array of field names, already exploded
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
            case 'short_description':
                $retval[$fld] = $this->name;
                break;
            case 'description':
                $retval[$fld] = $this->dscp;
                break;
            default:
                if (isset($this->$fld)) {
                    $retval[$fld] = $this->$fld;
                }
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
        global $_PLUGINS;

        static $enabled = NULL;

        if ($enabled !== NULL) {
            return $enabled;
        }
        $enabled = Config::get('enable_shop');
        if ($enabled) {
            if (!is_array($_PLUGINS) || !in_array('shop', $_PLUGINS)) {
                $enabled = false;
            }
        }
        return $enabled;
    }


    /**
     * Check if the current site user can purchase this plan.
     *
     * @return  boolean     True if purchase is allowed, False if not
     */
    public function canPurchase()
    {
        return SEC_inGroup($this->grp_access);
    }


    /**
     * Uses lib-admin to list the membership definitions and allow updating.
     *
     * @return  string  HTML for the list
     */
    public static function adminList()
    {
        global $_CONF, $_TABLES, $LANG_ADMIN, $LANG_MEMBERSHIP;

        $retval = '';

        $header_arr = array(
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort' => false,
                'align'=>'center',
            ),
            array(
                'text' => 'ID',
                'field' => 'plan_id',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['short_name'],
                'field' => 'name',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['enabled'],
                'field' => 'enabled',
                'sort' => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array('field' => 'plan_id', 'direction' => 'asc');
        $query_arr = array(
            'table' => 'membership_plans',
            'sql' => "SELECT * FROM {$_TABLES['membership_plans']} ",
            'query_fields' => array('name', 'description'),
            'default_filter' => '',
        );
        $text_arr = array(
            //'has_extras' => true,
            //'form_url'   => Config::get('admin_url') . '/index.php',
            'help_url'   => ''
        );
        $form_arr = array();
        $retval .= COM_createLink(
            $LANG_MEMBERSHIP['new_plan'],
            Config::get('admin_url') . '/index.php?editplan=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $retval .= ADMIN_list(
            'membership_planlist',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr, '', '',
            '', $form_arr
        );
        return $retval;
    }


    /**
     * Determine what to display in the admin list for each membership plan.
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
        case 'edit':
            $retval = COM_createLink(
                Icon::getHTML('edit'),
                Config::get('admin_url') . '/index.php?editplan=x&amp;plan_id=' . $A['plan_id']
            );
            break;

        case 'delete':
            // Deprecated
            if (!Plan::hasMembers($A['plan_id'])) {
                $retval = COM_createLink(
                    "<img src=\"{$_CONF['layout_url']}/images/admin/delete.png\"
                    height=\"16\" width=\"16\" border=\"0\"
                    onclick=\"return confirm('{$LANG_MEMBERSHIP['q_del_member']}');\"
                    >",
                    Config::get('admin_url') . '/index.php?deleteplan=x&plan_id=' .
                    $A['plan_id']
                );
            } else {
                $retval = '';
            }
           break;

        case 'enabled':
            if ($fieldvalue == 1) {
                $chk = ' checked="checked" ';
                $enabled = 1;
            } else {
                $chk = '';
                $enabled = 0;
            }
            $retval = "<input name=\"{$fieldname}_{$A['plan_id']}\" " .
                "id=\"{$fieldname}_{$A['plan_id']}\" ".
                "type=\"checkbox\" $chk " .
                "onclick='MEMB_toggle(this, \"{$A['plan_id']}\", \"plan\", \"{$fieldname}\", \"{$pi_admin_url}\");' />\n";
            /* TODO: glFusion 2.0
            $retval = FieldList::checkbox(array(
                'name' => "{$fieldname}_{$A['plan_id']}",
                'id' => "{$fieldname}_{$A['plan_id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "MEMB_toggle(this, '{$A['plan_id']}', 'plan', '{$fieldname}', '{$pi_admin_url}');",
            ) );
             */
            break;

        default:
            $retval = $fieldvalue;
            break;
        }

        return $retval;
    }

}
