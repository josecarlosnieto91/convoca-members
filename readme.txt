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

= Privacidad =

Este plugin recoge y almacena datos personales de socios y voluntarios: nombre y apellidos, correo electrónico, teléfono, dirección postal, DNI/NIF, fecha de nacimiento, plan de membresía seleccionado, áreas de interés de voluntariado, registro de horas de voluntariado y datos de la tarjeta de socio digital (incluyendo código QR). Estos datos se almacenan en la base de datos local de WordPress (tablas personalizadas wp_convoca_members_* y metadatos de usuario).

Los datos se utilizan para gestionar la afiliación, comunicación con socios y voluntarios, control de cuotas y períodos de carencia, emisión de certificados PDF de horas de voluntariado, generación de la tarjeta de socio digital y verificación de membresía. Los certificados PDF se generan localmente mediante Dompdf y no se envían a servidores externos.

Los datos de pago (cuotas) se gestionan a través del plugin Convoca Gateway; este plugin no almacena datos bancarios ni de tarjeta de crédito.

No se comparten datos personales con terceros. Los correos electrónicos automáticos se envían a través del sistema de correo de WordPress.

Los usuarios tienen derecho a:
* Solicitar acceso a sus datos almacenados
* Solicitar la exportación de sus datos en formato estructurado
* Solicitar la corrección de datos inexactos
* Solicitar la eliminación de sus datos (con la limitación de registros necesarios para obligaciones legales y contables)
* Revocar el consentimiento para comunicaciones
Para ejercer estos derechos, contacte con el administrador del sitio.

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
