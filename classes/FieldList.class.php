<?php
/**
 * Class to create custom admin list fields.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2021-2022 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v1.0.0
 * @since       v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;


/**
 * Class to handle custom fields.
 * @package membership
 */
class FieldList extends \glFusion\FieldList
{
    private static $t = NULL;

    protected static function init()
    {
        static $t = NULL;
        if (self::$t === NULL) {
            $t = new \Template(Config::get('path') . 'templates/');
            $t->set_file('field','fieldlist.thtml');
        }
        return $t;
    }


    /**
     * Create a view-application link.
     *
     * @param   array   $args   Argument array
     * @return  string      HTML for field
     */
    public static function view($args)
    {
        $t = self::init();

        $t->set_block('field','field-view');
        if (isset($args['url'])) {
            $t->set_var('edit_url',$args['url']);
        } else {
            $t->set_var('edit_url','#');
        }

        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-view');
        $t->clear_var('attributes');
        return $t->finish($t->get_var('output'));
    }


    /**
     * Create a `renew membership` button.
     *
     * @param   array   $args   Arguments for the button
     * @return  string      HTML for the button
     */
    public static function renewButton($args)
    {
        //$t = new \Template(__DIR__ . '/../templates/');
        //$t->set_file('delete','fieldlist.thtml');
        $t = self::init();
        $t->set_block('field','field-renew-button');

        $t->set_var('button_name',$args['name']);
        $t->set_var('text',$args['text']);

        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-renew-button','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-renew-button',true);
        return $t->finish($t->get_var('output'));
    }


    /**
     * Create a button to notify members of upcoming expiration.
     *
     * @param   array   $args   Arguments for the button
     * @return  string      HTML for the button
     */
    public static function notifyButton($args)
    {
        $t = self::init();
        $t->set_block('field','field-notify-button');

        $t->set_var('button_name',$args['name']);
        $t->set_var('text',$args['text']);

        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-notify-button','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-notify-button',true);
        $t->clear_var('attributes');
        return $t->finish($t->get_var('output'));
    }


    /**
     * Create a button to regenerate membership numbers.
     *
     * @param   array   $args   Arguments for the button
     * @return  string      HTML for the button
     */
    public static function regenButton($args)
    {
        $t = self::init();
        $t->set_block('field','field-regen-button');

        $t->set_var('button_name',$args['name']);
        $t->set_var('text',$args['text']);

        if (isset($args['attr']) && is_array($args['attr'])) {
            $t->set_block('field-regen-button','attr','attributes');
            foreach($args['attr'] AS $name => $value) {
                $t->set_var(array(
                    'name' => $name,
                    'value' => $value)
                );
                $t->parse('attributes','attr',true);
            }
        }
        $t->parse('output','field-regen-button',true);
        $t->clear_var('attributes');
        return $t->finish($t->get_var('output'));
    }

}
