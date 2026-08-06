=== Convoca Members ===
Contributors: josecarlosnietoramos
Tags: members, volunteers, membership, certificates, associations
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.6.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Member management, memberships, volunteering, PDF certificates and gamification.

== Description ==

Manage the complete lifecycle of members: registration, renewals, cancellations, fees, membership states, volunteer hours, PDF certificates and gamification.

Free features:
* Member registration via public form or admin panel
* Customizable membership types
* States: active, pending, suspended, expired, cancelled
* Automatic and manual renewals
* Soft-delete with history preservation
* Fee management and manual payments
* Volunteer hours tracking and approval
* Public membership and certificate verification
* GDPR data export
* Change audit log
* Automated emails (welcome, renewal, reminders)

PRO features (require license):
* Volunteer PDF certificates
* Gamification with badges
* Automatic PDF reports
* Member webhooks

= External services =

This plugin may contact getconvoca.app to validate PRO licenses, only when a key is entered in the admin panel.

== Installation ==

1. Ensure Convoca Core is active
2. Upload the `convoca-members` folder to `/wp-content/plugins/`
3. Activate the plugin from the Plugins menu

== Changelog ==

= 2.6.2 =
* Improvement: 92 unit tests, 250 assertions
* New: Membership state transition tests
* Fix: PDF certificate generation with real QR code

== Screenshots ==

1. Members list with filters
2. Member profile with history
3. Registration form (frontend)
4. PDF certificate example
5. Volunteer hours panel

== Frequently Asked Questions ==

= Does it require Convoca Core? =

Yes. Convoca Members requires Convoca Core to be active.

= Can I manage fees? =

Yes. You can create manual payments and track fee status per member. Integration with Convoca Gateway enables online payments.

= Are PDF certificates generated locally? =

Yes. Certificates are generated on your server with Dompdf. No external service is involved.

== Upgrade Notice ==

= 2.6.2 =
* Compatibility and stability improvements. Recommended update.
