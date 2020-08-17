<?php
/**
 * Show members by group
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2014-2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

require_once '../lib-common.php';
COM_setArgNames(array('type'));
$type = COM_getArgument('type');
if (empty($type)) {
    COM_404();
}

$GL = new Membership\GroupList($type);
if (isset($_GET['title']) && !empty($_GET['title'])) {
    $GL->setTitle($_GET['title']);
}
$content = $GL->Render();
$title = $GL->getPageTitle();
echo Membership\Menu::siteHeader($title);
echo $content;
echo Membership\Menu::siteFooter();

?>
