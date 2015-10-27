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

- When a new site user logs in for the first time, a system message is displayed encouraging them to visit the membership area to join the organization.

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

