<?php
/**
 * Class to handle membership applications provided by the Forms plugin.
 *
 * @author     Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package    membership
 * @version    0.2.0
 * @license    http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership\Apps;

/**
 * Class for a membership application from the Forms plugin.
 * The form is expected to be a single-entry form with editing allowed.
 * @package membership
 */
class Forms extends \Membership\App
{
    /** Form ID.
     * @var string */
    private $frm_id;

    /** Result set ID.
     * @var integer */
    private $result_id = 0;

    /** Mark if this form can't be used (non-existent, undefined)
     * @var boolean */
    private $isValid = true;


    /**
     * Constructor. Sets the form ID and gets the result set ID for the user.
     *
     * @param   integer $uid    User ID.
     */
    public function __construct($uid)
    {
        $this->frm_id = Config::get('app_form_id');
        parent::__construct($uid);

        // Check that the form exists and can be filled out.
        $status = LGLIB_invokeService($this->plugin, 'getFormInfo',
            array(
                'frm_id' => $this->frm_id,
                'perm' =>  2,
            ),
            $output,
            $svc_msg
        );
        if ($status != PLG_RET_OK || empty($output)) {
            $this->isValid = false;
        } else {
            // Get the result ID if the user has filled out the form
            $status = LGLIB_invokeService($this->plugin, 'resultId',
                array(
                    'frm_id' => $this->frm_id,
                    'uid' => $this->uid,
                ),
                $output,
                $svc_msg
            );
            if ($status == PLG_RET_OK) {
                $this->result_id = $output;
            } else {
                $this->isValid = false;
            }
        }
    }


    /**
     * Display an application saved by the Forms plugin.
     *
     * @return  array   Array of field prompt=>value pairs
     */
    public function getDisplayValues()
    {
        $retval = array();

        // Get the ID of the result record for this application
        $status = LGLIB_invokeService($this->plugin, 'getValues',
            array(
                'frm_id' => $this->frm_id,
                'uid' => $this->uid,
                'res_id' => $this->result_id,
            ),
            $output,
            $svc_msg
        );
        if ($status == PLG_RET_OK) {
            foreach ($output as $fld) {
                $retval[] = array(
                    'prompt' => $fld['prompt'],
                    'displayvalue' => $fld['displayvalue'],
                );
            }
        }
        return $retval;
    }


    /**
     * Get the prompts and fields for the application.
     * Called from the parent App class.
     *
     * @return  string      HTML for application form
     */
    protected function getEditForm()
    {
        $status = LGLIB_invokeService($this->plugin, 'renderForm',
            array(
                'uid' => $this->uid,
                'frm_id' => $this->frm_id,
                'res_id' => $this->result_id,
                'nobuttons' => true,
            ),
            $output,
            $svc_msg
        );
        return $status == PLG_RET_OK ? $output['content'] : '';
    }


    /**
     * Save the member application via the Profile plugin.
     *
     * @return  integer     Status from LGLIB_invokeService()
     */
    protected function _Save()
    {
        global $_USER;

        if (!MEMBERSHIP_isManager()) $_POST['mem_uid'] = $_USER['uid'];
        $args = array(
            'uid'   => $_POST['mem_uid'],
            'data'  => $_POST,
        );
        $status = LGLIB_invokeService($this->plugin, 'saveData',
            $args,
            $output,
            $svc_msg
        );
        return $status;
    }


    /**
     * Validate the application entry in case other validation was bypassed.
     *
     * @param   array   $A      $_POST or NULL to check the current on-file app
     * @return  boolean     True if app is valid, False if not
     */
    protected function _Validate($A = NULL)
    {
        $status = true;
        // todo: Add method to check new application from $_POST
        if ($A === NULL) {      // checking existing application
            $x = LGLIB_invokeService($this->plugin, 'validate',
                array(
                    'uid' => $this->uid,
                    'frm_id' => $this->frm_id,
                    'vars' => $A,
                    'res_id' => $this->result_id,
                ),
                $output,
                $svc_msg
            );
            if ($x != PLG_RET_OK) {
                $status = false;
            }
        }
        return $status;
    }


    /**
     * Check if the app exists, e.g. has been filled out.
     * For the Forms plugin, check if there is a valid resultset ID.
     *
     * @return  boolean     True if it exists, False if not
     */
    public function Exists()
    {
        return $this->result_id > 0 ? true : false;
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
        return $this->isValid;  // set in __construct() if form is found
    }

}

?>
