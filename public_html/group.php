<?php
//  $Id$
/**
*   Home page for the Member List
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2014 Lee Garner <lee@leegarner.com>
*   @package    membership
*   @version    0.1.3
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

require_once '../lib-common.php';
USES_membership_functions();

$autotag = array(
    'parm1' => 'grouplist',
    'parm2' => $_GET['type'],
    'tagstr' => 'tagstr',
);
echo MEMBERSHIP_siteHeader();
echo plugin_autotags_membership('parse', 'tagstr', $autotag);
echo MEMBERSHIP_siteFooter();

?>
