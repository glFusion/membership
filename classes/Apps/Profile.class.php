<?php
/**
 * Class to handle membership applications provided by the Profile plugin.
 *
 * @author     Lee Garner <lee@leegarner.com>
 * @copyright  Copyright (c) 2018-2022 Lee Garner <lee@leegarner.com>
 * @package    membership
 * @version    v1.0.0
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
     * @return  array   Array of field prompt=>value pairs
     */
    public function getDisplayValues()
    {
        global $_USER, $_CONF;

        $retval = array();
        $status = PLG_callFunctionForOnePlugin(
            'service_getValues_' . $this->plugin,
            array(
                1 => array(
                    'uid' => $this->uid,
                ),
                2 => &$output,
                3 => &$svc_msg,
            ),
        );
        if ($status == PLG_RET_OK && !empty($output)) {
            foreach ($output as $key=>$data) {
                $retval[$key] = array(
                    'prompt' => $data->getPrompt(),
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
            'uid'       => $this->uid,
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

        if (!MEMBERSHIP_isManager()) {
            $_POST['mem_uid'] = $_USER['uid'];
        }
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

