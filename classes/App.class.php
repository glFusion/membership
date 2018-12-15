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
    public static function Display($uid=0)
    {
        return self::DisplayProfile($uid);
    }


    /**
     * Display an application saved by the Forms plugin.
     *
     * @param   integer $uid    User ID to display
     * @return  string      HTML to display application
     */
    public static function DisplayForms($uid)
    {
        global $_USER;

        if ($uid == 0 || !MEMBERSHIP_isManager()) $uid = $_USER['uid'];

        // Get the ID of the result record for this application
        $output = 0;
        $status = LGLIB_invokeService('forms', 'resultId',
            array('frm_id' => $_CONF_MEMBERSHIP['app_form'], 'uid' => $uid),
            $output, $svc_msg);
        $args = array(
            'frm_id' => $_CONF_MEMBERSHIP['app_form'],
            'uid' => $uid,
            'res_id' => (int)$output,
        );
        $status = LGLIB_invokeService('forms', 'renderForm', $args,
            $output, $svc_msg);
    }


    /**
     * Display an application saved by the Custom Profile plugin.
     *
     * @param   integer $uid    User ID to display
     * @return  string      HTML to display application
     */
    private static function DisplayProfile($uid)
    {
        global $_USER, $_CONF;

        if ($uid == 0 || !MEMBERSHIP_isManager()) $uid = $_USER['uid'];
        $retval = '';
        $status = LGLIB_invokeService('profile', 'getValues',
            array('uid'=>$uid), $output, $svc_msg);
        if ($status == PLG_RET_OK && !empty($output)) {
            $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
            $T->set_file('app', 'application.thtml');
            $T->set_block('app', 'DataRow', 'row');
            foreach ($output['fields'] as $key=>$data) {
                $T->set_var(array(
                    'description'   => $data->prompt,
                    'value'         => $data->FormatValue(),
                ) );
                $T->parse('row', 'DataRow', true);
            }
            $M = self::getMember($uid);
            $rel_urls = '';
            if (!$M->isNew) {
                $relatives = Link::getRelatives($M->uid);
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


    /**
     * Allow a user to edit their application data.
     *
     * @param   integer $uid    User ID to display, 0 for current user
     * @return  string      HTML for application form
     */
    public static function Edit($uid = 0)
    {
        global $_USER;
        if ($uid == 0 || !MEMBERSHIP_isManager()) $uid = $_USER['uid'];
        return self::EditProfile($uid);
    }


    /**
     * Allow a user to edit their application data.
     *
     * @param   integer $uid    User ID to edit
     * @return  string      HTML for application form
     */
    private static function EditProfile($uid)
    {
        global $LANG_MEMBERSHIP, $_CONF, $_CONF_MEMBERSHIP;

        $retval = '';
        $prf_args = array(
            'uid'       => $uid,
            'form_id'   => 'membership_profile_form',
        );
        $typeselect_var = 'app_membership_type';
        $status = LGLIB_invokeService('profile', 'renderForm', $prf_args,
                $output, $svc_msg);
        if ($status == PLG_RET_OK && !empty($output)) {
            $M = self::getMember($uid);
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
                'mem_uid'       => $uid,
                'purch_url'     => MEMBERSHIP_PI_URL . '/index.php?list1',
                'profile_fields' => $output,
                'exp_msg'       => $M->isNew ? '' :
                    sprintf($LANG_MEMBERSHIP['you_expire'], $M->plan_id, $M->expires),
                'is_uikit'      => $_CONF_MEMBERSHIP['_is_uikit'],
            ) );
            if ($_CONF_MEMBERSHIP['update_maillist']) {
                $status = LGLIB_invokeService('mailchimp', 'issubscribed',
                        array('uid'=>$uid), $output, $svc_msg);
                if ($status == PLG_RET_OK && $output) {
                    $T->set_var('update_maillist', 'true');
                }
            }

            // Add the checkbox to accept terms or waivers, if so configured
            if ($_CONF_MEMBERSHIP['terms_url'] != '') {
                $terms_url = str_replace('%site_url%', $_CONF['site_url'],
                        $_CONF_MEMBERSHIP['terms_url']);
                switch ($_CONF_MEMBERSHIP['terms_accept']) {
                case MEMBERSHIP_APP_REQUIRED:
                    $T->set_var('terms_required', 'true');
                    $T->set_var('terms_cls', isset($_POST['app_errors']['terms_accept']) ? 'app_error' : '');
                case MEMBERSHIP_APP_OPTIONAL:
                    $T->set_var(array(
                        'terms_link' => sprintf($LANG_MEMBERSHIP['terms_link'],
                                            $terms_url),
                    ) );
                    break;
                default:
                    // If not configured for terms, do nothing
                    break;
                }
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
            $P->SetVars($A, true);
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
     * @uses    self::SaveProfile()
     * @return  integer     Status from LGLIB_invokeService()
     */
    public static function Save()
    {
        global $_TABLES, $_CONF_MEMBERSHIP, $_CONF;

        if (self::Validate($_POST) == 0) {
            $status = self::SaveProfile();
            if ($status == PLG_RET_OK) {
                $uid = (int)$_POST['mem_uid'];
                if (MEMB_getVar($_CONF_MEMBERSHIP, 'terms_accept', 'integer') > 0) {
                    $dt = new \Date('now', $_CONF['timezone']);
                    $type = 'Terms Accepted';
                    $data = 'Initial by ' . DB_escapeString(MEMB_getVar($_POST, 'terms_initial'));
                    DB_query("INSERT INTO {$_TABLES['membership_log']}
                            (uid, dt, type, data)
                        VALUES
                            ($uid, '$dt', '$type', '$data')");
                }
                // Subscribe the user to the default mailing list
                // if selected
                if (isset($_POST['mailchimp_subscribe']) &&
                    $_POST['mailchimp_subscribe'] == 1) {
                    LGLIB_invokeService('mailchimp', 'subscribe',
                        array('uid'=> $uid), $output, $svc_msg);
                }
            }
        } else {
            $status = PLG_RET_ERROR;
        }
        return $status;
    }


    /**
     * Save the member application via the Profile plugin.
     *
     * @return  integer     Status from LGLIB_invokeService()
     */
    private static function SaveProfile()
    {
        global $_USER;

        if (!MEMBERSHIP_isManager()) $_POST['mem_uid'] = $_USER['uid'];
        $args = array(
            'uid'   => $_POST['mem_uid'],
            'data'  => $_POST,
        );

        $status = LGLIB_invokeService('profile', 'saveData', $args,
            $output, $svc_msg);
        return $status;
    }


    /**
     * Get a membership object for the specified use ID.
     *
     * @param   integer $uid    User ID
     * @return  object          Membership object
     */
    private static function getMember($uid)
    {
        static $members = array();

        if (!isset($members[$uid])) {
            $members[$uid] = new Membership($uid);
        }
        return $members[$uid];
    }


    /**
     * Validate the application entry in case other validation was bypassed.
     *
     * @param   array   $A      $_POST, typically
     * @return  integer         Number of errors found
     */
    private static function Validate($A)
    {
        global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        $status = 0;
        $_POST['app_errors'] = array();     // borrow some global storage
        if ($_CONF_MEMBERSHIP['terms_accept'] == MEMBERSHIP_APP_REQUIRED) {
            if (!isset($A['terms_accept']) || empty($A['terms_initial'])) {
                LGLIB_storeMessage(array(
                    'message' => $LANG_MEMBERSHIP['err_terms_accept'],
                    'persist' => true
                ) );
                $_POST['app_errors']['terms_accept'] = 1;
                $status++;
            }
        }

        return $status;
    }

}

?>
