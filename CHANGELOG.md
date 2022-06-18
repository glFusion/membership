# glFusion Membership Plugin - Change Log

## 1.0.0
Release TBD
- Requires glFusion 2.0.0+.
- Fix typo in application print popup.
- Show notification interval on member editing page.
- Deprecate import from Subscription plugin.
- Include the final expiration count in notifycount.
- Log transactions created via quick renewal.
- Don't change the expiration from the form value when creating a new menber.

## 0.3.1
Release 2022-01-05
- Use list description from Position List as Group List page title.
- Quick-renew is now a popup, only available from the Member Admin list.
- Use a clone of the current date object when it will be modidified.
- Default to Logged-In Users as the initial member group, essentially a noop.

## 0.3.0
Release 2021-01-30
- Remove support for non-UIkit themes.
- Members can always view their own application.
- Applications can be provided by the Profile or Forms plugin.
- Enable web services so plugins can call `PLG_invokeService()`.
- Correct namespace usage
- Use Uikit datepicker
- Change Paypal plugin to Shop
- Change Mailchimp plugin to Mailer
- Add a message to plan lists indicating the latest plan if expired.
- Positions can be optionally shown on profile lists.
- Separate position group table for more flexibility in position lists.
- Allow multiple expiration notifications at fixed intervals.
- Allow admins to manually send expiration notifications.
- Send a final notification at expiration if notifications are used.

## 0.2.0
- Implement PHP class autoloader
- Require glFusion 1.6.0+
- Change AJAX to use Jquery
- Implement Membership namespace
- Fix default date for quick renewal in user profile
- Add configurable redirect after add-to-cart purchase

## 0.1.1
- Add option to remove "Buy Now" button from purchase options
- Do not remove default membership group during plugin removal
- Add a function to the admin page to import existing site members
- Added a way to disable new-user welcome messages.
- Add handling for membership numbers
- Remove member from any positions held upon expiration or cancellation
- Enable trial memberships, where a renewal is treated as "new"
- Optionally disable logins for expired accounts
- Allow PayPal integration to be disabled, cart only or cart + buy-now
