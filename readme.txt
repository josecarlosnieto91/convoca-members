=== Convoca Members ===
Contributors: josecarlosnietoramos
Tags: members, volunteers, donations, certificates, asociaciones, ONG
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
