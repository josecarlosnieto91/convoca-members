=== Biodevas Members ===
Contributors: josecarlosnietoramos
Tags: members, volunteers, association, socios, voluntarios
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestión de socios, voluntarios y comunicaciones para la Asociación Biodevas.

== Description ==

Plugin completo para gestionar miembros (socios y voluntarios) de una asociación:

* Formulario multi-paso de alta de socios con 5 planes y sub-modalidades
* Formulario de voluntariado con áreas de interés y detección de menores
* Máquina de estados con log de auditoría
* Sistema de emails plantilla con variables dinámicas
* Panel admin con WP_List_Table filtrable, búsqueda y exportación CSV
* REST API para integraciones externas
* Widget de dashboard con métricas rápidas
* Sistema de voluntariado con proyectos, registro de horas y certificados PDF

== Installation ==

1. Subir el directorio `biodevas-members` a `/wp-content/plugins/`
2. Descargar e instalar la librería Dompdf en `vendor/dompdf/` (requerido para certificados PDF)
3. Activar en Plugins
4. Ir a "Miembros > Ajustes" para configurar IBAN y email admin
5. Crear una página con el shortcode `[biodevas_alta]` para el formulario de socios
6. Crear otra página con `[biodevas_voluntariado]` para voluntarios
7. Crear página con `[biodevas_verificar_certificado]` para verificación de certificados

== Changelog ==

= 2.5.1 =
* Fix: CSRF vulnerability in export CSV (monitor CRM)
* Fix: Invalid state validation in manual inscription creation
* Fix: Webhooks admin page now requires manage_options capability
* Fix: False payment reminders when no pending payment date exists (Cron_Manager)
* Fix: Improved HTML email detection in Email_Manager

= 2.5.0 =
* Nuevo: Sistema de gestión de Voluntariado expandido (separación clara entre Socios y Voluntarios)
* Nuevo: Generación automática del "Acuerdo de Incorporación de Voluntariado" mediante BDV_Signature
* Nuevo: Registro de datos técnicos de aceptación (IP, timestamp, hash) en el PDF del acuerdo
* Mejora: Campos personalizables en el formulario [biodevas_voluntariado] (Teléfono, DNI, Dirección, etc.)
* Mejora: Lógica de asignación de roles y capacidades (voluntario_aprobado) centralizada

= 1.4.0 =
* Mejora: Refactorizado el sistema de generación de Acuerdos para usar el motor centralizado BDV_Signature

= 1.3.0 =
* Nuevo: Sistema de auditoría completo (cambios de estado, borrados, emails, contactos)
* Nuevo: Bloques de Gutenberg nativos para todos los shortcodes
* Nuevo: Botones AJAX corregidos en metabox de socio
* Fix: Migración completa date() → wp_date() para consistencia de zona horaria
* Fix: Número de socio en tarjeta, checkbox renovación, estado de cuota dinámico
* Fix: Botones superpuestos en listado, error JS innerHTML en metabox

= 1.2.1 =
* Fix: Corregidas meta keys RGPD para horas de voluntariado (_bdv_volunteer_id → _bdv_miembro_id, _bdv_hora_* → _bdv_fecha/_bdv_horas/_bdv_estado)
* Fix: Corregida búsqueda RGPD de inscripciones (ahora busca por _bde_email en vez de _bde_member_id inexistente)
* Fix: Corregida búsqueda RGPD de pagos (ahora busca por _bdg_origin + _bdg_origin_id en vez de _bdg_member_id inexistente)
* Fix: Corregida meta key en resumen trimestral de voluntariado (_bdv_estado_hora → _bdv_estado)
* Fix: Corregido bug en Payment Listener que comparaba arrays con strings al usar get_post_meta bulk
* Fix: Sincronizada constante BDV_MEMBERS_VERSION con la versión del header (1.1.0 → 1.2.1)

= 1.2.0 =
* Nuevo: Sistema de versionado de base de datos con Members_Upgrade_Manager
* Nuevo: Integración con Upgrade_Manager base de biodevas-common
* Nuevo: Comprobación automática de versiones en admin_init con caché de 24h
* Nuevo: Hook upgrader_process_complete para forzar comprobación tras actualizar
* Nuevo: Herramientas RGPD en metabox de miembro (exportar/eliminar datos JSON)
* Nuevo: Botón "Exportar datos" genera archivo JSON con todos los datos del miembro
* Nuevo: Botón "Eliminar todos los datos" con doble confirmación (cumplimiento RGPD)
* Actualización: Documentación técnica completa (API.md, USER_GUIDE.md, HOOKS.md)

= 1.1.0 =
* Nuevo: Sistema completo de voluntariado con CPT Proyectos
* Nuevo: Registro de horas de voluntariado con proyecto y tareas
* Nuevo: Conversión automática de voluntario a miembro al alcanzar horas objetivo
* Nuevo: Generación de certificados PDF con Dompdf
* Nuevo: Página pública de verificación de certificados
* Nuevo: Notificaciones email al completar objetivo de voluntariado
* Actualización: Formulario de voluntariado con selector de proyecto
* Actualización: Metabox de nonce verification corregida para CPT_Proyecto
* Actualización: REST API expandida con endpoints de proyectos y certificados

= 1.0.8 =
* Fix: Mejora en la visualización de ventajas de planes.

= 1.0.7 =
* Fix: Validación de configuración de pasarela de pago.

= 1.0.6 =
* Fix: Corrección error 403 en REST API para usuarios logueados.

= 1.0.5 =
* Fix: Corrección en la visualización de ventajas (ahora carga los valores por defecto si la base de datos está vacía).
* Fix: Validación de configuración de pasarela de pago para evitar errores en Redsys.

= 1.0.4 =
* Fix: Solucionado error 403 Forbidden para usuarios logueados añadiendo cabecera X-WP-Nonce en peticiones REST.

= 1.0.3 =
* Fix: Añadida constante faltante ESTADO_CUOTA que rompía el listado de administración.
* Fix: Corregida URL de obtención de nonce en el formulario público de alta.
* Fix: Añadidos metadatos faltantes (es_voluntario, forma_pago) en el registro de voluntarios para que aparezcan en el listado.
* Fix: Rellenadas las ventajas de los sub-planes (Familiar/Juvenil) que aparecían vacías.
* Fix: Corregido conflicto de nonce entre formulario de alta y voluntariado.

= 1.0.2 =
* Primera versión: CPT, formularios, emails, admin, REST API.
