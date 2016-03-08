Membership Plugin for glFusion
==============================

This plugin is similar to the Subscription plugin, but geared towards club
memberships. Site members can be linked together to form "families" for
family memberships.  When one member joins or renews their membership, all
linked accounts are updated, depending on the membership plan's configuration.

Some of the characteristics of this plugin are:
- Memberships are managed through the user Account Settings.  When the
  settings are saved, the membership data is updated.  Any member who was
  linked to the account being saved is also updated.

- When a new site user logs in for the first time, a system message is displayed encouraging them to visit the membership area to join the organization. To disable this behavior, create custom language files and set $LANG_MEMBERSHIP['new_acct_msg'] to '' (empty string).

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

- Membership numbers can be automatically created. Define a custom function
  CUSTOM_createMemberNumber($uid) in lib-custom.php to create a custom number.
  The glFusion user id is provided, the return value should be a string.
  Alternatively, a format string can be supplied in the plugin configuration
  which will use sprintf and the user ID to create a number. e.g. "A-MEM-%03d"
  to get member numbers like "A-MEM-005". Membership numbers can be disabled in
  the plugin configuration, but will be automatically generated in the background.

REQUIREMENTS:
This plugin requires both the Profile plugin and lgLIB 0.0.2 or later for core functions.
The Paypal plugin can be integrated to handle membership payments.
