=== Convoca Members ===
Contributors: josecarlosnietoramos
Tags: members, volunteers, membership, certificates, associations
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.7.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Member management, memberships, volunteering, PDF certificates and gamification.

== Description ==

Manage the complete lifecycle of members: registration, renewals, cancellations, fees, membership states, volunteer hours, PDF certificates and gamification.

Free features:
* Member registration via public form or admin panel
* Member area shortcodes `[convoca_mi_area]`, `[convoca_mi_perfil]` and `[convoca_renovar]`
* Registration shortcode `[convoca_alta_socio]`
* Customizable membership types
* States: active, pending, suspended, expired, cancelled
* Automatic and manual renewals
* Member profile editing (address, phone, email, birthday)
* Email and phone verification via emailed confirmation links
* Pluggable email provider (wp_mail / Mailgun)
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

= 2.7.2 =
* Security: CPT miembro ya no se expone vía REST por defecto (show_in_rest=false) — antes listaba nombres de socios en /wp/v2/miembro.
* Security: búsqueda /search exige sesión de socio para miembros (anónimos solo actividades) y no devuelve emails.
* Security: fix IDOR en descarga de documentos — propiedad resuelta desde la sesión de socio; huérfanos denegados a terceros.
* Security: verificación de duplicados en alta usa SELECT ... LIMIT 1 FOR UPDATE (COUNT(*) FOR UPDATE no bloquea en MySQL).
* Security: Estados::change usa lock atómico en BD en vez de transients no atómicos.
* Security: transiciones de horas de voluntariado atómicas (UPDATE condicional) — sin hooks duplicados en doble aprobación.
* Security: creación de miembro exige capability gestionar_miembros (antes edit_posts).

= 2.7.1 =
* Fix: el formulario de alta y el registro respetan el flag active de los planes — un plan desactivado ya no aparece ni puede contratarse.
* Fix: los selectores Familiar/Juvenil solo se muestran si hay sub-planes activos de esa modalidad (evita min() sobre array vacío).
* Fix: la validación de edad juvenil depende de la modalidad del plan, no del prefijo del slug (robusto al renombrar IDs cortos).

= 2.7.0 =
* New: Manual membership renewal (button in member panel + `[convoca_renovar]` shortcode)
* New: Member profile editing (address, phone, email, birthday)
* New: Email and phone verification via emailed confirmation links
* New: Pluggable email provider (wp_mail / Mailgun)
* Fix: Member panel REST namespace (convoca-members/v1)
* Fix: Member sequence MAX(id) SQL error on paid signups

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

= 2.7.0 =
* Manual renewal, profile editing and email/phone verification. Recommended update.

= 2.6.2 =
* Compatibility and stability improvements. Recommended update.
