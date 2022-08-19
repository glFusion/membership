<?php
/**
 * Class to read and manipulate Membership configuration values.
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
namespace Membership;

/**
 * Class to get plugin configuration data.
 * @package membership
 */
class Config
{
    /** Plugin Name.
     */
    public const PI_NAME = 'membership';

     /** Array of config items (name=>val).
     * @var array */
    private $properties = NULL;


    /**
     * Get the Membership configuration object.
     * Creates an instance if it doesn't already exist.
     *
     * @return  object      Configuration object
     */
    public static function getInstance() : self
    {
        static $cfg = NULL;
        if ($cfg === NULL) {
            $cfg = new self;
        }
        return $cfg;
    }


    /**
     * Create an instance of the Membership configuration object.
     */
    private function __construct()
    {
        global $_CONF;

        $this->properties = \config::get_instance()
             ->get_config(self::PI_NAME);

        $this->properties['pi_path'] = $_CONF['path'] . 'plugins/membership/';
        $this->properties['url'] = $_CONF['site_url'] . '/' . self::PI_NAME;
        $this->properties['admin_url'] = $_CONF['site_admin_url'] . '/plugins/' . self::PI_NAME;

        if (is_file($this->properties['pi_path'] . '/plugin.json')) {
            // Get values from the installed plugin.json
            $pi_json = json_decode(file_get_contents($this->properties['pi_path'] . '/plugin.json'));
            $this->properties['pi_display_name'] = $pi_json->name;
            $this->properties['pi_url'] =  $pi_json->url;
            $this->properties['gl_version'] = $pi_json->glfusionversion;
            $this->properties['pi_version'] = $pi_json->version;
        }
    }


    /**
     * Returns a configuration item.
     * Returns all items if `$key` is NULL.
     *
     * @param   string|NULL $key    Name of item to retrieve
     * @return  mixed       Value of config item
     */
    public static function get($key=NULL)
    {
        return self::getInstance()->_get($key);
    }


    /**
     * Set a configuration value.
     * Unlike the root glFusion config class, this does not add anything to
     * the database. It only adds temporary config vars.
     *
     * @param   string  $key    Configuration item name
     * @param   mixed   $val    Value to set, NULL to unset
     */
    public static function set($key, $val=NULL)
    {
        return self::getInstance()->_set($key, $val);
    }


    /**
     * Set a configuration value.
     * Unlike the root glFusion config class, this does not add anything to
     * the database. It only adds temporary config vars.
     *
     * @param   string  $key    Configuration item name
     * @param   mixed   $val    Value to set, NULL to unset
     * @return  object  $this
     */
    public function _set($key, $val=NULL)
    {
        if ($val === NULL) {
            unset($this->properties[$key]);
        } else {
            $this->properties[$key] = $val;
        }
        return $this;
    }


    /**
     * Get a configuration value from the current config instance.
     *
     * @param   string  $key    Name of item to retrieve
     * @return  mixed       Value of config item
     */
    private function _get($key)
    {
        if ($key === NULL) {
            return $this->properties;
        } else {
            return array_key_exists($key, $this->properties) ? $this->properties[$key] : NULL;
        }
    }


    /**
     * Write a value to the config table. Does not set a local property.
     *
     * @param   string  $key    Config key name
     * @param   string  $val    Value
     */
    public function write($key, $val)
    {
        \config::get_instance()->set($key, $val, self::PI_NAME);
    }

}

