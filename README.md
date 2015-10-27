$Id: README.md 101 2014-07-08 03:46:54Z root $

Membership Plugin for glFusion
==============================

This plugin is similar to the Subscription plugin, but geared towards club
memberships. Site members can be linked together to form "families" for
"family" memberships.  When one member joins or renews their membership, all
linked accounts are updated, depending on the membership plan's configuration.

Some of the characteristics of this plugin are:
- Memberships are managed through the user Account Settings.  When the
    settings are saved, the membership data is updated.  Any member who was
    linked to the account being saved is also updated.

- Memberships are never deleted.  Removing a membership from a user account
    sets the expiration date to "now" but does not remove the membership.

- Accounts can be linked to multiple other accounts. All accounts are then
    linked together in a mesh.

- When an account is unlinked from another account, its membership record is
    not changed. If you wish to terminate one of the linked accounts'
    membership, you'll need to unlink and edit that account.

- Members can join or renew at any time, or the membership may be for a fixed
    year beginning in any month.  There is an option to round the expiration
    up to the end of the expiration month.

- All memberships are annual, and a member can have only one membership at
    a time.

REQUIREMENTS:
This plugin requires both the Profile plugin and lgLIB 0.0.2 or later for core functions.

Some core changes are needed to enable full functionality:
-  public_html/users.php starting around line 392. The $redir value is a url
    if set by the membership plugin, or false if none set. This is used for
    workflow.
        $redir = function_exists('LGLIB_getGlobal') ? LGLIB_getGlobal('redirect', true) : false;
        if ($mailresult == false) {
            $retval = COM_refresh ("{$_CONF['site_url']}/index.php?msg=85");
        } else if ($msg) {
            $retval = $redir ? COM_refresh($redir) : COM_refresh ("{$_CONF['site_url']}/index.php?msg=$msg");
        } else {
            if ($_CONF['registration_type'] == 1 ) {
                $retval = $redir ? COM_refresh($redir) : COM_refresh ("{$_CONF['site_url']}/index.php?msg=3");
            } else {
                $retval = $redir ? COM_refresh($redir) : COM_refresh ("{$_CONF['site_url']}/index.php?msg=1");
            }
        }

