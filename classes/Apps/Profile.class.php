<?php
/**
 * Class to handle membership applications provided by the Profile plugin.
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
 * Class for a membership application provided by the Custom Profile plugin.
 * @package membership
 */
class Profile extends \Membership\App
{

    /**
     * Display an application saved by the Custom Profile plugin.
     *
     * @param   integer $uid    User ID to display
     * @return  string      HTML to display application
     */
    protected function getDisplayValues()
    {
        global $_USER, $_CONF;

        $retval = array();
        $status = PLG_invokeService('profile', 'getValues',
            array(
                'uid' => $this->uid,
            ),
            $output,
            $svc_msg
        );
        if ($status == PLG_RET_OK && !empty($output)) {
            foreach ($output as $key=>$data) {
                $retval[$key] = array(
                    'prompt' => $data->prompt,
                    'displayvalue' => $data->FormatValue(),
                );
            }
        }
        return $retval;
    }


    /**
     * Get the prompts and fields for the Custom Profile part of the app.
     *
     * @return  string      HTML for the application form
     */
    protected function getEditForm()
    {
        //$retval = '';
        $prf_args = array(
            'uid'       => $uid,
            'form_id'   => 'membership_profile_form',
        );
        //$typeselect_var = 'app_membership_type';
        $status = PLG_invokeService('profile', 'renderForm',
            $prf_args,
            $output,
            $svc_msg
        );
        return $status == PLG_RET_OK ? $output : '';
    }


    /**
     * Save the member application via the Profile plugin.
     *
     * @return  integer     Status from PLG_invokeService()
     */
    protected function _Save()
    {
        global $_USER;

        if (!MEMBERSHIP_isManager()) $_POST['mem_uid'] = $_USER['uid'];
        $args = array(
            'uid'   => $_POST['mem_uid'],
            'data'  => $_POST,
        );

        $status = PLG_invokeService('profile', 'saveData',
            $args,
            $output,
            $svc_msg
        );
        return $status;
    }


    /**
     * Validate the application entry in case other validation was bypassed.
     *
     * @param   array|null  $A      $_POST or NULL to check the current on-file app
     * @return  boolean     True if app is valid, False if not
     */
    protected function _Validate($A = NULL)
    {
        global $_CONF_MEMBERSHIP, $LANG_MEMBERSHIP;

        $status = true;
        // todo: Add method to check new application from $_POST
        if ($A === NULL) {      // checking existing application
            $x = PLG_invokeService('profile', 'validate',
                array(
                    'uid' => $this->uid,
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
     * For the Profile plugin, the app always exists.
     *
     * @return  boolean     True if it exists, False if not
     */
    public function Exists()
    {
        return true;
    }

}

?>
