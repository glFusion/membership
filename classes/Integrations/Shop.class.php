<?php
/**
 * Class to describe the shop-enabled flag states
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
namespace Membership\Integrations;
use Membership\Config;


/**
 * Class to describe the shop-enabled config flag.
 * @package membership
 */
class Shop extends \Membership\Integration
{
    const NONE = 0;
    const BUY_NOW = 1;
    const CART = 2;

    static $pi_name = 'shop';


    /**
     * Get the currency code.
     * If the shop plugin is enabled, use its currency.
     * Otherwise use the defualt.
     *
     * @return  string      Currency code
     */
    public static function getCurrency() : string
    {
        static $currency = NULL;
        if (self::isEnabled()) {
            $currency = PLG_callFunctionForOnePlugin('plugin_getCurrency_shop');
        }
        if ($currency === false) {
            $currency = Config::get('currency');
        }
        if (empty($currency)) $currency = 'USD';
        return $currency;
    }


    /**
     * See if the "Buy Now" button is permitted.
     *
     * @return  boolean     True if a buy-now button can be used
     */
    public static function canBuyNow() : bool
    {
        return Config::get('enable_shop') & self::BUY_NOW == self::BUY_NOW;
    }

}
