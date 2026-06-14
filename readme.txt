=== Convoca Members ===
Contributors: josecarlosnietoramos
Tags: members, volunteers, association, socios, voluntarios, convoca
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 2.6.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestión de socios, voluntarios y comunicaciones para asociaciones.

== Description ==

Plugin completo para gestionar miembros (socios y voluntarios) de una asociación:

* Formulario multi-paso de alta de socios con 5 planes y sub-modalidades
* Formulario de voluntariado con áreas de interés y detección de menores
* Máquina de estados con log de auditoría
* Sistema de emails plantilla con variables dinámicas
* Panel admin con WP_List_Table filtrable, búsqueda y exportación CSV
* REST API para integraciones externas
* Sistema de voluntariado con proyectos, registro de horas y certificados PDF
* Tarjeta de socio digital con código QR
* Shortcodes: [convoca_alta_socio], [convoca_mi_area], [convoca_voluntariado], [convoca_verificar_socio], [convoca_verificar_certificado]

== Installation ==

1. Asegúrate de que Convoca Core está activo
2. Sube la carpeta convoca-members a /wp-content/plugins/
3. Ejecuta composer install (requerido para Dompdf)
4. Activa el plugin
5. Crea una página con [convoca_alta_socio] para el formulario de socios
6. Crea página con [convoca_voluntariado] para voluntarios

== Frequently Asked Questions ==

= ¿Qué planes de membresía incluye? =

5 planes: Familiar, Joven, Amigo Protector, Apadrina un Árbol, Simpatizante. Cada uno con sub-modalidades configurables.

= ¿Genera certificados PDF? =

Sí, los voluntarios pueden descargar certificados PDF de sus horas realizadas.

= ¿Requiere Composer? =

Sí, para la generación de PDF con Dompdf.

== Changelog ==

= 2.6.1 =
* Fix: Logo en tarjeta de socio, acciones del listado, feedback notices
* Nuevo: Asignación masiva de números de socio

= 2.6.0 =
* Seguridad: Dedup email con INSERT ON DUPLICATE KEY UPDATE
* Corrección: Locks, fechas wp_date(), consultas renovación

= 2.5.1 =
* Seguridad: CSRF en export CSV, capability webhooks admin page
* Fix: Recordatorios de pago sin fecha pendiente

= 2.5.0 =
* Centralización: Flujo de transferencia al Convoca Gateway
* Rediseño: Tarjeta de socio premium

= 1.0.2 =
* Primera versión
