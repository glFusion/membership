<?php
/**
 * Class to handle membership transactions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership\Models;
use Membership\Config;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for membership transactions.
 * @package membership
 */
class Transaction
{
    /** Transaction record ID.
     * @var integer */
    private $tx_id = 0;

    /** Transaction date.
     * @var string */
    private $tx_dt = '';

    /** User ID creating the transaction, 0 for system action.
     * @var integer */
    private $tx_by = 0;

    /** Member's user ID.
     * @var integer */
    private $tx_uid = 0;

    /** Membership plan ID.
     * @var string */
    private $tx_planid = '';

    /** Payment gateway or description.
     * @var string */
    private $tx_gw = '';

    /** Transaction amount.
     * @var float */
    private $tx_amt = 0;

    /** New membership expiration date as a result of this transaction.
     * @var string */
    private $tx_exp = '';

    /** Transaction ID, e.g. from the payment webhook.
     * @var string */
    private $tx_txn_id = '';


    /**
     * Crate a transaction object, optionally reading from the database.
     *
     * @param   integer $tx_id      Optional transaction ID to read from DB
     */
    public function __construct(?int $tx_id=NULL)
    {
        $this->withDoneBy();
        $this->withDate();
        if (is_int($tx_id)) {
            $this->Read($tx_id);
        }
    }


    /**
     * Read a transaction from the database.
     *
     * @param   integer $tx_id      Transaction record ID
     * @return  object  $this
     */
    public function Read(int $tx_id) : self
    {
        global $_TABLES;

        try {
            $data = $db->conn->executeQuery(
                "SELECT * FROM {$_TABLES['membership_trans']} WHERE tx_id = ?",
                array($tx_id),
                array(Database::INTEGER)
            )->fetchAssociative();
        } catch (\Exception $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = false;
        }
        if (is_array($data)) {
            $this->setVars($data);
        }
        return $this;
    }


    /**
     * Set all the object properties from a DB record.
     *
     * @param   array   $A      Database record array
     * @return  object  $this
     */
    public function setVars(array $A) : self
    {
        $this->tx_id = (int)$A['tx_id'];
        $this->tx_dt = $A['tx_dt'];
        $this->tx_by = (int)$A['tx_by'];
        $this->tx_uid = (int)$A['tx_uid'];
        $this->tx_planid = $A['tx_planid'];
        $this->tx_gw = $A['tx_gw'];
        $this->tx_amt = (float)$A['tx_amt'];
        $this->tx_exp = $A['tx_exp'];
        $this->tx_txn_id = $A['tx_txn_id'];
        return $this;
    }


    /**
     * Set the user ID creating the transaction.
     *
     * @param   integer $uid    User ID, null to use current user
     * @return  object  $this
     */
    public function withDoneBy(?int $uid=NULL) : self
    {
        global $_USER;

        if ($uid === NULL) {
            $this->tx_by = (int)$_USER['uid'];
        } else {
            $this->tx_by = (int)$uid;
        }
        return $this;
    }


    /**
     * Set the transaction date.
     *
     * @param   string  $dt     Datetime string, null for current date
     * @return  object  $this
     */
    public function withDate(?string $dt=NULL) : self
    {
        global $_CONF;

        if ($dt === NULL) {
            $this->tx_dt = $_CONF['_now']->toMySQL(true);
        } else {
            $this->tx_dt = $dt;
        }
        return $this;
    }


    /**
     * Set the new membership expiration date.
     *
     * @param   string  $exp    Expiration date as YYYY-MM-DD
     * @return  object  $this
     */
    public function withExpiration(string $exp) : self
    {
        $this->tx_exp = $exp;
        return $this;
    }


    /**
     * Set the user ID of the member.
     *
     * @param   integer $uid    Member's user ID
     * @return  object  $this
     */
    public function withUid(int $uid) : self
    {
        $this->tx_uid = (int)$uid;
        return $this;
    }


    /**
     * Set the membership plan ID paid by this transaction.
     *
     * @param   string  $plan_id    Plan ID
     * @return  object  $this
     */
    public function withPlanId(string $plan_id) : self
    {
        $this->tx_planid = $plan_id;
        return $this;
    }


    /**
     * Get the plan ID in the transaction.
     * Used to verify if the plan has changed during renewal.
     *
     * @return  string      New plan ID
     */
    public function getPlanId() : string
    {
        return $this->tx_planid;
    }


    /**
     * Set the payment gateway description.
     *
     * @param   string  $gw     Payment gateway name or other description
     * @return  object  $this
     */
    public function withGateway(string $gw) : self
    {
        $this->tx_gw = $gw;
        return $this;
    }


    /**
     * Set the payment amount.
     *
     * @param   float   $amt    Payment amount
     * @return  object  $this
     */
    public function withAmount(float $amt) : self
    {
        $this->tx_amt = (float)$amt;
        return $this;
    }


    /**
     * Set the payment transaction ID.
     *
     * @param   string  $id     Transaction ID
     * @return  object  $this
     */
    public function withTxnId(string $id) : self
    {
        $this->tx_txn_id = $id;
        return $this;
    }


    /**
     * Save the transaction to the database.
     *
     * @return  boolean     True on success, False on error
     */
    public function save() : bool
    {
        global $_TABLES;

        $db = Database::getInstance();
        $qb = $db->conn->createQueryBuilder();
        try {
            $qb->insert($_TABLES['membership_trans'])
               ->setValue('tx_date', ':tx_date')
               ->setValue('tx_uid', ':tx_by')
               ->setValue('tx_uid', ':tx_uid')
               ->setValue('tx_planid', ':tx_planid')
               ->setValue('tx_gw', ':tx_gw')
               ->setValue('tx_amt', ':tx_amt')
               ->setValue('tx_exp', ':tx_exp')
               ->setValue('tx_txn_id', ':tx_txn_id')
               ->setValue('tx_by', ':tx_by')
               ->setParameter('tx_date', $this->tx_dt, Database::STRING)
               ->setParameter('tx_by', $this->tx_by, Database::INTEGER)
               ->setParameter('tx_uid', $this->tx_uid, Database::INTEGER)
               ->setParameter('tx_planid', $this->tx_planid, Database::STRING)
               ->setParameter('tx_gw', $this->tx_gw, Database::STRING)
               ->setParameter('tx_amt', $this->tx_amt, Database::INTEGER)
               ->setParameter('tx_exp', $this->tx_exp, Database::STRING)
               ->setParameter('tx_txn_id', $this->tx_txn_id, Database::STRING)
               ->setParameter('tx_by', $this->tx_by, Database::INTEGER)
               ->execute();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . '(): ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * List transactions.
     *
     * @return  string  HTML output for the page
     */
    public static function adminList() : string
    {
        global $_TABLES, $LANG_MEMBERSHIP, $_CONF;

        $tx_from = MEMB_getVar($_POST, 'tx_from');
        if (!empty($tx_from)) {
            $from_sql = "AND tx_date >= '" . DB_escapeString($tx_from . ' 00:00:00') . "'";
        } else {
            $tx_from = '';
            $from_sql = '';
        }
        $tx_to = MEMB_getVar($_POST, 'tx_to');
        if (!empty($tx_to)) {
            $to_sql = "AND tx_date <= '" . DB_escapeString($tx_to . ' 23:59:59') . "'";
        } else {
            $tx_to = '';
            $to_sql = '';
        }
        $uid = MEMB_getVar($_GET, 'uid', 'integer');
        if ($uid > 0) {
            $user_sql = 'AND tx_uid = ' . (int)$_GET['uid'];
        } else {
            $user_sql = '';
        }

        $query_arr = array(
            'table' => 'membership_trans',
            'sql' => "SELECT tx.*, u.fullname as tx_fullname
                FROM {$_TABLES['membership_trans']} tx
                LEFT JOIN {$_TABLES['users']} u
                    ON u.uid = tx.tx_uid
                WHERE 1=1 $from_sql $to_sql $user_sql",
            'query_fields' => array('u.fullname'),
            'default_filter' => '',
        );
        $defsort_arr = array(
            'field' => 'tx_date',
            'direction' => 'DESC',
        );
        $text_arr = array(
            'has_extras' => true,
            'form_url'  => Config::get('admin_url') . '/index.php?listtrans',
        );
        $tx_from = MEMB_getVar($_POST, 'tx_from');
        $tx_to = MEMB_getVar($_POST, 'tx_to');
        $filter = $LANG_MEMBERSHIP['from'] .
            ': <input id="f_tx_from" type="text" size="10" name="tx_from" data-uk-datepicker value="' . $tx_from . '" />&nbsp;' .
            $LANG_MEMBERSHIP['to'] .
            ': <input id="f_tx_to" type="text" size="10" name="tx_to" data-uk-datepicker value="' . $tx_to . '" />';
        $header_arr = array(
            array(
                'text' => $LANG_MEMBERSHIP['date'],
                'field' => 'tx_date',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['entered_by'],
                'field' => 'tx_by',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['member_name'],
                'field' => 'tx_fullname',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['plan'],
                'field' => 'tx_planid',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['expires'],
                'field' => 'tx_exp',
                'sort' => true,
                'nowrap' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['pmt_method'],
                'field' => 'tx_gw',
                'sort' => true,
            ),
            array(
                'text' => $LANG_MEMBERSHIP['amount'],
                'field' => 'tx_amt',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_MEMBERSHIP['txn_id'],
                'field' => 'tx_txn_id',
                'sort' => true,
            ),
        );
        $form_arr = array();
        return ADMIN_list(
            'membership_listtrans',
            array(__CLASS__, 'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr, $filter, '',
            '', $form_arr
        );
    }


    /**
     * Determine what to display in the admin list for each form.
     *
     * @param  string  $fieldname  Name of the field, from database
     * @param  mixed   $fieldvalue Value of the current field
     * @param  array   $A          Array of all name/field pairs
     * @param  array   $icon_arr   Array of system icons
     * @return string              HTML for the field cell
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr) : string
    {
        global $_CONF, $LANG_ACCESS, $LANG_MEMBERSHIP, $_TABLES, $LANG_ADMIN;

        $retval = '';
        $pi_admin_url = Config::get('admin_url');

        switch($fieldname) {
        case 'tx_fullname':
            $retval = COM_createLink(
                $fieldvalue,
                Config::get('admin_url') . '/index.php?listtrans&amp;uid=' . $A['tx_uid']
            );
            break;

        case 'tx_by':
            if ($fieldvalue == 0) {
                $retval = $LANG_MEMBERSHIP['system_task'];
            } else {
                $retval = COM_getDisplayName($fieldvalue);
            }
            break;

        case 'tx_txn_id':
            $non_gw = array('', 'cc', 'check', 'cash');
            $retval = $fieldvalue;
            if (!empty($fieldvalue) && !in_array($A['tx_gw'], $non_gw)) {
                $status = PLG_callFunctionForOnePlugin(
                    'service_getUrl_shop',
                    array(
                        1 => array('id' => $fieldvalue, 'type' => 'order'),
                        2 => &$output,
                        3 => &$svc_msg,
                    )
                );
                if ($status == PLG_RET_OK) {
                    $retval = COM_createLink($fieldvalue, $output);
                }
            }
            break;

        case 'tx_amt':
            $retval = number_format((float)$fieldvalue, 2, '.', '');
            break;

        default:
            $retval = $fieldvalue;

        }
        return $retval;
    }

}
