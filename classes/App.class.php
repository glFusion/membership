<?php
/**
 * Class to handle membership application viewing and editing.
 *
 * @author     Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2012-2018 Lee Garner <lee@leegarner.com>
 * @package    membership
 * @version    0.2.0
 * @license    http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;

/**
 * Class for a membership application.
 * @package membership
 */
class App
{
    /** User ID.
     * @var integer */
    protected $uid = 0;

    /** Application provider plugin name.
     * @var string */
    protected $plugin = '';

    public function __construct($uid = 0)
    {
        global $_USER, $_CONF_MEMBERSHIP;

        if ($uid == 0 || !MEMBERSHIP_isManager()) $uid = $_USER['uid'];
        $this->uid = $uid;
        $this->plugin = $_CONF_MEMBERSHIP['app_provider'];
    }


    /**
     * Get an instance of a specific membership.
     *
     * @param   integer $uid    User ID to retrieve, default=current user
     * @return  object      Membership object
     */
    public static function getInstance($uid = 0)
    {
        global $_USER, $_CONF_MEMBERSHIP;

        if ($uid == 0) $uid = $_USER['uid'];
        $uid = (int)$uid;
        switch ($_CONF_MEMBERSHIP['app_provider']) {
        case 'profile':
            $retval = new \Membership\Apps\Profile($uid);
            break;
        case 'forms':
            $retval = new \Membership\Apps\Forms($uid);
            break;
        default:
            $retval = new self($uid);
            break;
        }
        return $retval;
    }


    /**
     * Display an application within glFusion.
     * Calls DisplayProfile or DisplayForms depending on which plugin
     * is providing the application form function.
     *
     * @uses    self::DisplayForms()
     * @uses    self::DisplayProfile()
     * @param   integer $uid    User ID to display
     * @return  string      HTML to display application
     */
    public function Display()
    {
        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file('app', 'application.thtml');
        $T->set_block('app', 'DataRow', 'row');
        $values = $this->getDisplayValues();
        foreach ($values as $key=>$fld) {
            $T->set_var(array(
                'description'   => $fld['prompt'],
                'value'         => $fld['displayvalue'],
            ) );
            $T->parse('row', 'DataRow', true);
        }
        $M = \Membership\Membership::getInstance($uid);
        $rel_urls = '';
        if (!$M->isNew) {
            $relatives = $M->getLinks();
            foreach ($relatives as $key=>$name) {
                $rel_urls .= '&nbsp;&nbsp;<a href="' . $_CONF['site_url'] .
                    "/users.php?mode=profile&amp;uid=$key\">$name</a>";
            }
        }
        $T->set_var(array(
            'member_name'   => COM_getDisplayName($this->uid),
            'rel_accounts'  => $rel_urls,
        ) );
        $T->parse('output', 'app');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
        //return $this->getDisplay();
    }


    /**
     * Display an application saved by the Custom Profile plugin.
     *
     * @param   integer $uid    User ID to display
     * @return  string      HTML to display application
     */
    //private static function DisplayProfile($uid)
    public function XDisplay()
    {
        global $_USER, $_CONF;

        if ($this->uid == 0 || !MEMBERSHIP_isManager()) $uid = $_USER['uid'];
        $retval = '';
        $status = PLG_invokeService($this->plugin, 'getValues',
            array(
                'uid'=>$uid,
            ),
            $output,
            $svc_msg
        );
        if ($status == PLG_RET_OK && !empty($output)) {
            $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
            $T->set_file('app', 'application.thtml');
            $T->set_block('app', 'DataRow', 'row');
            foreach ($output as $key=>$data) {
                $T->set_var(array(
                    'description'   => $data->prompt,
                    'value'         => $data->FormatValue(),
                ) );
                $T->parse('row', 'DataRow', true);
            }
            $M = Membership::getInstance($uid);
            $rel_urls = '';
            if (!$M->isNew) {
                $relatives = $M->getLinks();
                foreach ($relatives as $key=>$name) {
                    $rel_urls .= '&nbsp;&nbsp;<a href="' . $_CONF['site_url'] .
                        "/users.php?mode=profile&amp;uid=$key\">$name</a>";
                }
            }
            $T->set_var(array(
                'member_name'   => COM_getDisplayName($uid),
                'rel_accounts'  => $rel_urls,
            ) );
            $T->parse('output', 'app');
            $retval = $T->finish($T->get_var('output'));
        }
        return $retval;
    }



    public function Edit()
    {
        global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        $M = \Membership\Membership::getInstance($this->uid);
        if (isset($_POST[$typeselect_var])) {
            $sel = $_POST[$typeselect_var];
        } elseif ($M->isNew) {
            $sel = '';
        } else {
            $sel = $M->plan_id;
        }
        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
        $T->set_file('app', 'app_form.thtml');
        $T->set_var(array(
            'form_id'       => 'membership_forms_form',
            'mem_uid'       => $this->uid,
            'purch_url'     => MEMBERSHIP_PI_URL . '/index.php?list1',
            'profile_fields' => $this->getEditForm(),
            'exp_msg'       => $M->isNew ? '' :
                sprintf($LANG_MEMBERSHIP['you_expire'], $M->plan_id, $M->expires),
        ) );
        if ($_CONF_MEMBERSHIP['update_maillist']) {
            $status = PLG_invokeService('mailchimp', 'issubscribed',
                array(
                    'uid'=>$this->uid,
                ),
                $output,
                $svc_msg
            );
            if ($status == PLG_RET_OK && $output) {
                $T->set_var('update_maillist', 'true');
            }
        }

        // Add the checkbox to accept terms or waivers, if so configured
        if ($_CONF_MEMBERSHIP['terms_url'] != '') {
            $terms_url = str_replace('%site_url%', $_CONF['site_url'],
                $_CONF_MEMBERSHIP['terms_url']);
            $T->set_var(array(
                'terms_link' => sprintf($LANG_MEMBERSHIP['terms_link'], $terms_url),
            ) );
        }
        if ($_CONF_MEMBERSHIP['terms_accept']) {
            $T->set_var(array(
                'terms_required'    => true,
                'terms_cls' => isset($_POST['app_errors']['terms_accept']) ? 'app_error' : '',
            ) );
        }

        $T->set_block('app', 'TypeSelect', 'row');
        $types = self::TypeSelect($sel);
        foreach ($types as $plan=>$type) {
            $T->set_var(array(
                'plan_id'   => $plan,
                'description' => $type['description'],
                'price'     => $type['price'],
                'totalprice' => $type['total'],
                'fee'       => $type['fee'],
                'selected'  => $type['sel'],
                'varname'   => $typeselect_var,
            ) );
            $T->parse('row', 'TypeSelect', true);
        }
        $T->parse('output', 'app');
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Allow a user to edit their application data.
     *
     * @param   integer $uid    User ID to edit
     * @return  string      HTML for application form
     */
    //private static function EditProfile($uid)
    public function XEdit()
    {
        global $LANG_MEMBERSHIP, $_CONF, $_CONF_MEMBERSHIP;

        $retval = '';
        $prf_args = array(
            'uid'       => $this->uid,
    //'form_id'   => 'membership_profile_form',
            'frm_id'    => $this->frm_id,
        );
        $typeselect_var = 'app_membership_type';
        $status = PLG_invokeService($this->plugin, 'renderForm',
            $prf_args,
            $output,
            $svc_msg
        );
        if ($status == PLG_RET_OK && !empty($output)) {
            $M = Membership::getInstance($uid);
            if (isset($_POST[$typeselect_var])) {
                $sel = $_POST[$typeselect_var];
            } elseif ($M->isNew) {
                $sel = '';
            } else {
                $sel = $M->plan_id;
            }

            $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
            $T->set_file('app', 'app_form.thtml');
            $T->set_var(array(
                'form_id'       => 'membership_profile_form',
                'mem_uid'       => $this->uid,
                'purch_url'     => MEMBERSHIP_PI_URL . '/index.php?list1',
                'profile_fields' => $output,
                'exp_msg'       => $M->isNew ? '' :
                    sprintf($LANG_MEMBERSHIP['you_expire'], $M->plan_id, $M->expires),
            ) );
            if ($_CONF_MEMBERSHIP['update_maillist']) {
                $status = PLG_invokeService('mailchimp', 'issubscribed',
                    array(
                        'uid'=>$uid,
                    ),
                    $output,
                    $svc_msg
                );
                if ($status == PLG_RET_OK && $output) {
                    $T->set_var('update_maillist', 'true');
                }
            }

            // Add the checkbox to accept terms or waivers, if so configured
            if ($_CONF_MEMBERSHIP['terms_url'] != '') {
                $terms_url = str_replace('%site_url%', $_CONF['site_url'],
                    $_CONF_MEMBERSHIP['terms_url']);
                $T->set_var(array(
                    'terms_link' => sprintf($LANG_MEMBERSHIP['terms_link'],
                                            $terms_url),
                ) );
            }
            if ($_CONF_MEMBERSHIP['terms_accept']) {
                var_dump($U);die;
                $T->set_var(array(
                    'terms_required'    => true,
                    'terms_cls' => isset($_POST['app_errors']['terms_accept']) ? 'app_error' : '',
                ) );
            }

            $T->set_block('app', 'TypeSelect', 'row');
            $types = self::TypeSelect($sel);
            foreach ($types as $plan=>$type) {
                $T->set_var(array(
                    'plan_id'   => $plan,
                    'description' => $type['description'],
                    'price'     => $type['price'],
                    'totalprice' => $type['total'],
                    'fee'       => $type['fee'],
                    'selected'  => $type['sel'],
                    'varname'   => $typeselect_var,
                ) );
                $T->parse('row', 'TypeSelect', true);
            }
            $T->parse('output', 'app');
            $retval .= $T->finish($T->get_var('output'));
        }
        return $retval;
    }


    /**
     * Create radio buttons to select the membership type on the application.
     *
     * @param   string  $sel        Selected value. Optional
     * @return  string          HTML for radio button selections
     */
    public static function TypeSelect($sel='')
    {
        global $_TABLES;

        $sql = "SELECT * FROM {$_TABLES['membership_plans']}";
        if (!MEMBERSHIP_isManager()) {
            $sql .= ' WHERE grp_access = 2';
        }
        $res = DB_query($sql);
        $P = new Plan();
        $retval = array();
        // If selection is populated, this can't be a new membership
        $isNew = $sel == '' ? true : false;
        while ($A = DB_fetchArray($res, false)) {
            $P->setVars($A, true);
            $retval[$P->plan_id] = array(
                'plan_id' => $P->plan_id,
                'sel' => $P->plan_id == $sel ? ' checked="checked"' : '',
                'description' => $P->description,
                'total' => $P->Price($isNew),
                'price' => $P->Price($isNew, 'priceonly'),
                'fee' => $P->Price($isNew, 'fee'),
            );
        }
        return $retval;
    }


    /**
     * Save the application.
     * This is a wrapper around other functions; at the moment only
     * saving the user profile is supported.
     *
     * @return  integer     Status from PLG_invokeService()
     */
    public function Save()
    {
        global $_TABLES, $_CONF_MEMBERSHIP, $_CONF, $_USER;

        $uid = (int)$_POST['mem_uid'];
        if ($this->Validate($_POST) == 0) {
            $status = $this->_Save();   // call plugin-specific save function

            if ($status == PLG_RET_OK) {
                // Save and log the terms and conditions acceptance.
                if (MEMB_getVar($_POST, 'terms_accept', 'integer') > 0) {
                    $dt = new \Date('now', $_CONF['timezone']);
                    $type = 'Terms Accepted';
                    $data = 'Initial by ' . DB_escapeString(MEMB_getVar($_POST, 'terms_initial'));
                    $sql = "INSERT INTO {$_TABLES['membership_users']} SET
                        uid = $this->uid,
                        terms_accept = UNIX_TIMESTAMP()
                        ON DUPLICATE KEY UPDATE
                        terms_accept = UNIX_TIMESTAMP()";
                    DB_query($sql);
                    DB_query("INSERT INTO {$_TABLES['membership_log']}
                            (uid, dt, type, data)
                        VALUES
                            ($this->uid, '$dt', '$type', '$data')");
                }

                // Subscribe the user to the default mailing list
                // if selected
                if (isset($_POST['mailchimp_subscribe']) &&
                    $_POST['mailchimp_subscribe'] == 1) {
                    PLG_invokeService('mailchimp', 'subscribe',
                        array(
                            'uid'=> $uid,
                        ),
                        $output,
                        $svc_msg
                    );
                }
            }
        } else {
            $status = PLG_RET_ERROR;
        }
        return $status;
    }


    /**
     * Validate the application entry in case other validation was bypassed.
     *
     * @param   integer $uid    User ID
     * @param   array   $A      $_POST or NULL to check the current on-file app
     * @return  boolean     True if app is valid, False if not
     */
    public function Validate($A = NULL)
    {
        global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        if ($this->uid < 2) {
            return false;
        }

        $status = true;
        // Check that the terms acceptance is supplied, or was done within a year
        $terms_accept = 0;
        if ($_CONF_MEMBERSHIP['terms_accept']) {
            $U = User::getInstance($this->uid);
            $terms_accept = $U->terms_accept;
            if (is_array($A)) {
                $terms_chk = MEMB_getVar($A, 'terms_accept', 'integer');
                $initial = MEMB_getVar($A, 'terms_initial');
                if ($terms_chk == 1 && !empty($initial)) {
                    $terms_accept = time();
                };
            }
            if ($terms_accept < time() - 31536000) {
                COM_setMsg($LANG_MEMBERSHIP['err_terms_accept'], 'error', 1);
                $_POST['app_errors']['terms_accept'] = 1;
                $status = false;
            }
        }

        if ($_CONF_MEMBERSHIP['require_app'] != MEMBERSHIP_APP_REQUIRED) {
            // App is not required, return status now.
            return $status;
        }

        // Now validate according to the plugin supplying the application
        $status = $this->_Validate($A);
        return $status;
    }


    /**
     * Check if the app exists, e.g. has been filled out.
     *
     * @return  boolean     True if it exists, False if not
     */
    public function Exists()
    {
        return false;
    }

}

?>
