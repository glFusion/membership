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

    /**
     * Constructor. Sets the form ID and gets the result set ID for the user.
     *
     * @param   integer $uid    User ID.
     */
    public function __construct($uid)
    {
        global $_CONF_MEMBERSHIP;

        $this->frm_id = $_CONF_MEMBERSHIP['app_form_id'];
        parent::__construct($uid);
        // Get the result ID if the user has filled out the form
        $status = LGLIB_invokeService('forms', 'resultId',
            array(
                'frm_id' => $this->frm_id,
                'uid' => $this->uid,
            ),
            $output,
            $svc_msg
        );
        if ($status == PLG_RET_OK) {
            $this->result_id = $output;
        }
    }


    /**
     * Display an application saved by the Forms plugin.
     *
     * @return  string      HTML to display application
     */
    public function getDisplayValues()
    {
        global $_USER;

        // Get the ID of the result record for this application
        $status = LGLIB_invokeService('forms', 'getValues',
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
        return $output;
    }


    /**
     * Get the prompts and fields for the application.
     * Called from the parent App class.
     *
     * @return  string      HTML for application form
     */
    protected function getEditForm()
    {
        $status = LGLIB_invokeService('forms', 'renderForm',
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
     * @return  integer         Number of errors found
     */
    protected function _Validate($A = NULL)
    {
        global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        $status = 0;
        // todo: Add method to check new application from $_POST
        if ($A === NULL) {      // checking existing application
            if ($status == PLG_RET_OK) {
                $x = PLG_invokeService($this->plugin, 'validate',
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
                    $status++;
                }
            } else {
                $status++;
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

}

?>
