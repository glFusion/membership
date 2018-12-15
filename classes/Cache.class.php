<?php
/**
 * Class to cache DB and web lookup results.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;
use \glFusion\Cache\Cache as glCache;

/**
 * Class for Membership Cache.
 * @package membership
 */
class Cache
{
    /** Tag to be added to all cache keys */
    const TAG = 'membership';

    /** Minimum glFusion version to support caching */
    const MIN_GVERSION = '2.0.0';


    /**
     * Update the cache.
     * Adds an array of tags including the plugin name
     *
     * @param   string  $key    Item key
     * @param   mixed   $data   Data, typically an array
     * @param   mixed   $tag    Tag, or array of tags.
     * @param   integer $cache_mins Cache minutes
     * @return  boolean     True on success, False on error
     */
    public static function set($key, $data, $tag='', $cache_mins=1440)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return;     // glFusion version doesn't support caching
        }

        $ttl = (int)$cache_mins * 60;   // convert to seconds
        // Always make sure the base tag is included
        $tags = array(self::TAG);
        if (!empty($tag)) {
            if (!is_array($tag)) $tag = array($tag);
            $tags = array_merge($tags, $tag);
        }
        $key = self::makeKey($key);
        return glCache::getInstance()->set($key, $data, $tags, $cache_mins * 60);
    }


    /**
     * Delete a single item by key.
     *
     * @param   string  $key    Key to delete
     * @return  boolean     True on success, False on error
     */
    public static function delete($key)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return;     // glFusion version doesn't support caching
        }
        return glCache::getInstance()->delete(self::makeKey($key));
    }


    /**
     * Completely clear the cache.
     * Called after upgrade.
     *
     * @param   array|string    $tag    Optional tag or tags
     * @return  boolean     True on success, False on error
     */
    public static function clear($tag = array())
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return;     // glFusion version doesn't support caching
        }

        $tags = array(self::TAG);
        if (!empty($tag)) {
            if (!is_array($tag)) $tag = array($tag);
            $tags = array_merge($tags, $tag);
        }
        return glCache::getInstance()->deleteItemsByTagsAll($tags);
    }


    /**
     * Create a unique cache key.
     *
     * @param   string  $key    Base cache key
     * @return  string      Encoded key string to use as a cache ID
     */
    public static function makeKey($key)
    {
        return self::TAG . '_' . $key;
    }


    /**
     * Get an item from cache.
     *
     * @param   string  $key    Key to retrieve
     * @return  mixed       Value of key, or NULL if not found
     */
    public static function get($key)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            return;     // glFusion version doesn't support caching
        }

        $key = self::makeKey($key);
        if (glCache::getInstance()->has($key)) {
            return glCache::getInstance()->get($key);
        } else {
            return NULL;
        }
    }

}   // class Membership\Cache

?>
