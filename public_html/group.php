<?php
/**
 * Show members by group
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2014-2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.2.0
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

$autotag = array(
    'parm1' => 'grouplist',
    'parm2' => $type,
    'tagstr' => 'tagstr',
);

$GL = new Membership\GroupList($type);
$content = $GL->Render();
$title = $GL->getPageTitle();
echo Membership\Menu::siteHeader($title);
echo $content;
echo Membership\Menu::siteFooter();

?>
