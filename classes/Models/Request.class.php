<?php
/**
 * Utility class to get values from URL parameters.
 * Merges $_GET and $_POST to create one array of properties that can then
 * be accessed safely via the accessor functions of DataArray.
 * This should be instantiated via getInstance() to ensure consistency.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.6.1
 * @since       v1.6.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership\Models;


/**
 * HTTP Request class.
 * @package    membership
 */
class Request extends DataArray
{

    /**
     * Initialize the properties from a supplied string or array.
     * Use array_merge to preserve default properties by child classes.
     *
     * @param   string|array    $val    Optonal initial properties (ignored here)
     */
    public function __construct(?array $A=NULL)
    {
        $this->properties = array_merge($_GET, $_POST);
    }


    /**
     * Get a single instance of the request object.
     *
     * @return  object      Request object
     */
    public static function getInstance() : self
    {
        static $instance = NULL;
        if ($instance === NULL) {
            $instance = new self;
        }
        return $instance;
    }


    /**
     * Determine if the current request is via AJAX.
     *
     * @return  boolean     True if AJAX, False if not.
     */
    public function isAjax() : bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

}

