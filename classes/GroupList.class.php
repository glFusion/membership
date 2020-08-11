<?php
/**
 * Class to handle group lists.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     membership
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Membership;


/**
 * Class to handle board and committee positions.
 * @package membership
 */
class GroupList
{
    /** Flag to show the title in the heading.
     * May be false for autotags.
     * @var boolean */
    private $show_title = true;

    /** Title string. Created from the group name if empty.
     * @var string */
    private $title = '';

    /** Group name to be shown.
     * @var string */
    private $grpname = '';

    /** Page title. Generated from the group name.
     * @var string */
    private $page_title = '';

    /** Group tags to be shown.
     * @var array */
    private $groups = array();


    /**
     * Constructor. Set the group name to list.
     *
     * @param   string  $groups Single or array of groups to show
     */
    public function __construct($groups=NULL)
    {
        if ($groups !== NULL) {
            $this->setGroups($groups);
        }
    }


    /**
     * Set the groups to show if not done in the constructor.
     *
     * @param   string  $groups Single or array of group tags
     * @return  object  $this
     */
    public function setGroups($groups)
    {
        if (is_string($groups)) {
            $groups = explode(',', $groups);
        }
        $this->groups = $groups;
        return $this;
    }


    /**
     * Set the group name if not done in the constructor.
     *
     * @param   string  $grpname    Group name to list
     * @return  object  $this
     */
    public function setGroupName($grpname)
    {
        $this->grpname = $grpname;
        return $this;
    }


    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * Set the show_title flag value.
     *
     * @param   boolean $flag   True to show the title, False to not
     * @return  object  $this
     */
    public function ehowTitle($flag)
    {
        $this->show_title = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Get the page title created from the group name.
     *
     * @return  string      Page title
     */
    public function getPageTitle()
    {
        return $this->page_title;
    }


    /**
     * Render the listing.
     *
     * @return  string      Formatted group list
     */
    public function Render()
    {
        global $_TABLES, $LANG_MEMBERSHIP;

        USES_lib_user();

        if (empty($this->groups)) {
            COM_404();
        }

        $groups = "'" . implode("','", $this->groups) . "'";
        $sql = "SELECT p.*,u.username,u.fullname,u.email
            FROM {$_TABLES['membership_positions']} p
            LEFT JOIN {$_TABLES['membership_posgroups']} pg
            ON pg.pg_id = p.pg_id
            LEFT JOIN {$_TABLES['users']} u
                ON u.uid = p.uid
            WHERE pg.pg_tag IN ($groups)
            ORDER BY pg.pg_orderby, p.orderby ASC";
        //echo $sql;die;
        $res = DB_query($sql);

        $T = new \Template(MEMBERSHIP_PI_PATH . '/templates');
        $T ->set_file(array(
            'groups' => 'groups.thtml',
        ));

        while ($A = DB_fetchArray($res, false)) {
            $T->set_block('groups', 'userRow', 'uRow');
            if ($A['uid'] == 0) {    // vacant position
                $user_img = '';
                $show_vacant = $A['show_vacant'] ? 'true' : '';
                $username = '';
            } else {
                $user_img = USER_getPhoto($A['uid']);
                $username = COM_getDisplayName(
                    $A['uid'],
                    $A['username'],
                    $A['fullname']
                );
                $show_vacant = '';
            }
            if ($this->show_title) {
                if (!empty($this->title)) {
                    $page_title = $this->title;
                } else {
                    $page_title = sprintf($LANG_MEMBERSHIP['title_positionpage'], ucfirst($this->groups[0]));
                }
            } else {
                $page_title = '';
            }
            $T->set_var(array(
                'title' => $page_title,
                'position'  => $A['descr'],
                'user_name' => $username,
                'show_vacant' => $show_vacant,
                'user_img'  => $user_img,
                'user_email' => empty($A['contact']) ?
                    $LANG_MEMBERSHIP['contact'] : $A['contact'],
                'uid'       => $A['uid'],
            ) );
            $T->parse('uRow', 'userRow', true);
        }
        $T->parse('output', 'groups');
        return $T->finish($T->get_var('output'));
    }

}

?>
