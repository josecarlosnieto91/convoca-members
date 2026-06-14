# Manual de Instalación y Puesta en Marcha: Convoca Members

Guía para la gestión de socios, cuotas y voluntariado del ecosistema Convoca.

## 📥 1. Instalación del Plugin

1. **Requisito previo:** `convoca-core` debe estar instalado y activo.
2. Sube la carpeta `convoca-members` a `/wp-content/plugins/`.
3. **Dependencias:** Ejecuta `composer install` dentro de la carpeta si es necesario.
4. Activa el plugin desde el panel de **Plugins**.

## 🛠 2. Configuración Inicial

1. Ve a **Convoca Socios > Ajustes**:
   - **Planes de Membresía:** Define los nombres y cuotas (ej: Busgosu, Lugg, Deva).
   - **Email del administrador:** Dirección para notificaciones.
   - **IBAN:** Para domiciliaciones bancarias.
   - **Versión RGPD:** Texto de consentimiento.
   - **Recordatorios:** Configura desde **Convoca > Emails Automáticos**.

2. **Área Privada:** Crea una página llamada "Mi Área" y añade el shortcode `[convoca_mi_area]`. Añádela al menú principal.

## ⚙️ 3. Shortcodes Disponibles

| Shortcode | Descripción |
|-----------|-------------|
| `[convoca_alta_socio]` | Formulario de alta de socio |
| `[convoca_mi_area]` | Panel privado del socio (requiere acceso) |
| `[convoca_voluntariado]` | Formulario de inscripción de voluntariado |
| `[convoca_verificar_socio]` | Verificación de estado de socio |
| `[convoca_verificar_certificado]` | Verificación de certificado digital |

## 🔍 4. Operativa Diaria

- **Alta de Socios:** Los usuarios se registran desde `[convoca_alta_socio]`.
- **Registro de Horas:** Los voluntarios envían sus horas desde su área privada.
- **Aprobación de Horas:** El administrador revisa y aprueba las horas desde el panel.
- **Códigos de Acceso:** Se generan desde el perfil del socio para acceso al área privada.

## 🔍 Checklist de Verificación Final

- [ ] **Formulario de Alta:** Rellena `[convoca_alta_socio]`. El socio debe crearse en estado "pendiente" y recibir un email de confirmación.
- [ ] **Validación de Edad:** Prueba el registro con un plan juvenil pero fecha de nacimiento que supere la edad máxima.
- [ ] **DNI/NIE:** Introduce un DNI incorrecto; el sistema debe bloquearlo.
- [ ] **Código de Acceso:** Genera un código para un socio de prueba y accede a `[convoca_mi_area]`.
- [ ] **Registro de Horas:** Envía 2 horas de voluntariado. Verifica que aparece como "pendiente" en el admin.
- [ ] **Aprobación de Horas:** Aprueba las horas y verifica que el contador se actualiza.
- [ ] **Tarjeta Digital:** Descarga la tarjeta digital desde el panel del socio.
- [ ] **Integración Gateway:** (Si está activo) Al elegir un plan de pago, el sistema debe redirigir a la pasarela.

¡Sistema de socios listo para funcionar!
