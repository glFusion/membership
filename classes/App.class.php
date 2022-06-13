<?php
/**
 * Class to handle membership application viewing and editing.
 *
 * @author     Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2012-2022 Lee Garner <lee@leegarner.com>
 * @package    membership
 * @version    v1.0.0
 * @license    http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for a membership application.
 * @package membership
 */
class App
{
    const DISABLED = 0;
    const OPTIONAL = 1;
    const REQUIRED = 2;

    /** User ID.
     * @var integer */
    protected $uid = 0;

    /** Application provider plugin name.
     * @var string */
    protected $plugin = '';


    /**
     * Constructor.
     * Sets the plugin name and user ID.
     *
     * @param   integer $uid    User ID associated to the application
     */
    public function __construct(?int $uid = NULL)
    {
        global $_USER;

        if (empty($uid)) {
            $uid = $_USER['uid'];
        }
        $this->uid = $uid;
        $this->plugin = Config::get('app_provider');
    }


    /**
     * Get an instance of a specific member application.
     *
     * @param   integer $uid    User ID to retrieve, default=current user
     * @return  object      Membership object
     */
    public static function getInstance($uid = 0)
    {
        global $_USER;

        if ($uid == 0) {
            $uid = $_USER['uid'];
        }
        $uid = (int)$uid;

        switch (self::getProvider()) {
        case 'profile':
            $retval = new Apps\Profile($uid);
            break;
        case 'forms':
            $retval = new Apps\Forms($uid);
            break;
        default:
            // If the provider plugin is not available, just return an empty
            // object so calls to object methods won't fail.
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
        global $_CONF;

        $T = new \Template(Config::get('pi_path') . 'templates');
        $T->set_file('app', 'application.thtml');
        $T->set_block('app', 'DataRow', 'row');
        $values = $this->getDisplayValues();
        $retval = COM_siteHeader();
        foreach ($values as $key=>$fld) {
            $T->set_var(array(
                'description'   => $fld['prompt'],
                'value'         => $fld['displayvalue'],
            ) );
            $T->parse('row', 'DataRow', true);
        }
        $M = Membership::getInstance($this->uid);
        $rel_urls = '';
        if (!$M->isNew()) {
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
        $retval .= $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Dummy function to get the display values.
     * Only used if a valid provider instance couldn't be created.
     *
     * @return  array       Array of prompt=>value pairs
     */
    public function getDisplayValues()
    {
        return array();
    }


    /**
     * Provide the editing page for an application.
     *
     * @return  string      HTML for application edit form.
     */
    public function Edit()
    {
        global $_CONF, $LANG_MEMBERSHIP;

        $M = Membership::getInstance($this->uid);
        /*if (isset($_POST[$typeselect_var])) {
            $sel = $_POST[$typeselect_var];
        } elseif ($M->isNew()) {
            $sel = '';
        } else {*/
            $sel = $M->getPlanID();
        //}
        $T = new \Template(Config::get('pi_path') . 'templates');
        $T->set_file('app', 'app_form.thtml');
        $T->set_var(array(
            'form_id'       => 'membership_forms_form',
            'mem_uid'       => $this->uid,
            'purch_url'     => Config::get('url') . '/index.php?list1',
            'profile_fields' => $this->getEditForm(),
            'exp_msg'       => $M->isNew() ? '' :
                sprintf($LANG_MEMBERSHIP['you_expire'], $M->getPlanID(), $M->getExpires()),
        ) );

        // Add the checkbox to accept terms or waivers, if so configured
        if (Config::get('terms_url') != '') {
            $terms_url = str_replace(
                '%site_url%',
                $_CONF['site_url'],
                Config::get('terms_url')
            );
            $T->set_var(array(
                'terms_link' => sprintf($LANG_MEMBERSHIP['terms_link'], $terms_url),
            ) );
        }
        if (Config::get('terms_accept')) {
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
                //'varname'   => $typeselect_var,
            ) );
            $T->parse('row', 'TypeSelect', true);
        }
        $T->parse('output', 'app');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Create radio buttons to select the membership plan type.
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
        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery($sql)->fetchAll(Database::ASSOCIATIVE);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $data = array();
        }
        $P = new Plan();
        $retval = array();
        // If selection is populated, this can't be a new membership
        $isNew = $sel == '' ? true : false;
        foreach ($data as $A) {
            $P->setVars($A, true);
            $retval[$P->getPlanID()] = array(
                'plan_id' => $P->getPlanID(),
                'sel' => $P->getPlanID() == $sel ? ' checked="checked"' : '',
                'description' => $P->getDscp(),
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
        global $_TABLES, $_CONF, $_USER;

        $uid = (int)$_POST['mem_uid'];

        if (MEMB_getVar($_POST, 'terms_accept', 'integer') > 0) {
            // Update the terms-accepted checkbox first since it will
            // be checked in Validate()
            $dt = $_CONF['_now']->toMySQL(false);
            $type = 'Terms Accepted';
            $data = 'Initial by ' . MEMB_getVar($_POST, 'terms_initial');
            $db = Database::getInstance();
            try {
                $db->conn->insert(
                    $_TABLES['membership_users'],
                    array('uid' => $this->uid, 'initials' => $initials, 'terms_accept' => time()),
                    array(Database::INTEGER, Database::STRING, Database::INTEGER)
                );
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
                $db->conn->update(
                    $_TABLES['membership_users'],
                    array('initials' => $initials, 'terms_accept' => time()),
                    array('uid' => $this->uid),
                    array(Database::STRING, Database::INTEGER, Database::INTEGER)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }

            try {
                $db->conn->insert(
                    $_TABLES['membership_log'],
                    array('uid' => $this->uid, 'dt' => $dt, 'type' => $type, 'data' => $data),
                    array(Database::INTEGER, Database::STRING, Database::STRING, Database::STRING)
                );
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            }
        }

        if ($this->Validate($_POST)) {
            $status = $this->_Save();   // call plugin-specific save function

            if ($status == PLG_RET_OK) {
                // Save and log the terms and conditions acceptance.
                // Subscribe the user to the default mailing list
                // if selected
                if (
                    isset($_POST['maillist_subscribe']) &&
                    $_POST['maillist_subscribe'] == 1
                ) {
                    PLG_invokeService('mailer', 'subscribe',
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
     * @param   array|null  $A      $_POST or NULL to check the current on-file app
     * @return  boolean     True if app is valid, False if not
     */
    public function Validate($A = NULL)
    {
        global $LANG_MEMBERSHIP;

        if ($this->uid < 2 || !$this->isValidForm()) {
            return false;
        }

        $status = true;
        // Check that the terms acceptance is supplied, or was done within a year
        // This is done if terms are enabled regardless of whether an app is
        // used.
        if (Config::get('terms_accept')) {
            $U = User::getInstance($this->uid);
            $terms_accept = $U->getTermsAccepted();
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
        if (!self::isRequired()) {
            // App is not required, return status now.
            return $status;
        }

        // Now validate according to the plugin supplying the application
        if ($status) {
            $status = $this->_Validate($A);
        }
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


    /**
     * Validate the application entry in case other validation was bypassed.
     * Default return is true.
     *
     * @param   array   $A      $_POST or NULL to check the current on-file app
     * @return  boolean     True if app is valid, False if not
     */
    protected function _Validate($A = NULL)
    {
        return true;
    }


    /**
     * Get the prompts and fields for the application.
     * Default is empty value if neither Profile nor Forms are used.
     *
     * @return  string      HTML for application form
     */
    protected function getEditForm()
    {
        return '';
    }


    /**
     * Get an arra of plugins that are supported and available for applications.
     *
     * @return  array   Array of plugin names
     */
    public static function supportedPlugins()
    {
        static $plugins = NULL;
        if ($plugins !== NULL) {
            return $plugins;
        }

        // Start with all the supported plugins
        $plugins = array('forms', 'profile');
        // Now verify that the plugin is installed and enabled, and remove
        // from the list if not.
        foreach ($plugins as $idx=>$pi_name) {
            if (!function_exists('plugin_chkVersion_' . $pi_name)) {
                unset($plugins[$idx]);
            }
        }
        return $plugins;
    }


    /**
     * Determine if an application is required.
     * Returns true if the require_app config value is set, unless the app
     * provider plugin is not available.
     *
     * @return  boolean     True if an app is required, False if not.
     */
    public static function isRequired()
    {
        static $isRequired = NULL;

        if ($isRequired !== NULL) {
            return $isRequired;
        } elseif (!self::providerAvailable()) {
            // Don't require an app if the provider plugin is not available
            $isRequired = false;
        } elseif (Config::get('require_app') < self::REQUIRED) {
            // App is not required
            $isRequired = false;
        } else {
            $isRequired = true;
        }
        return $isRequired;
    }


    /**
     * Check if the configured app provider plugin is available.
     *
     * @return  boolean     True if the provider is available, False if not.
     */
    public static function providerAvailable()
    {
        static $isAvailable = NULL;

        if ($isAvailable === NULL) {
            $isAvailable = in_array(
                Config::get('app_provider'),
                self::supportedPlugins()
            );
        }
        return $isAvailable;
    }


    /**
     * Get the configured provider, also checking that it is available.
     *
     * @return  string|boolean  Name of provider, False if not enabled
     */
    public static function getProvider()
    {
        if (self::providerAvailable()) {
            return Config::get('app_provider');
        } else {
            return false;
        }
    }


    /**
     * Check if the form is valid.
     * Used mainly when the Forms plugin is used in case the configured form
     * id doesn't correspond to an actual form.
     *
     * @return  boolean     True if form can be used, False if not.
     */
    public function isValidForm()
    {
        return true;
    }

}

?>
