# Manual de Instalación y Puesta en Marcha: Biodevas Members

Guía para la gestión de socios y voluntarios de la Asociación Biodevas.

## 📥 1. Instalación del Plugin

1. **Requisito previo:** Asegúrate de que `biodevas-common` esté instalado y activo.
2. Sube la carpeta `biodevas-members` a `/wp-content/plugins/`.
3. Activa el plugin desde el panel de **Plugins**.

## 🛠 2. Configuración Inicial

1. Ve a **Biodevas Socios > Ajustes**:
   - **Planes de Membresía:** Define los nombres y cuotas (ej: Socio Ordinario, Socio Joven).
   - **Emails:** Configura la dirección de remitente y la plantilla de bienvenida.
   - **Recordatorios:** Establece cuántos días antes de la expiración se debe avisar al socio.
2. **Área Privada:** Crea una página en WordPress llamada "Mi Área" y añade el shortcode `[biodevas_mi_area]`. Configura esta página en los ajustes del plugin.

## ⚙️ 3. Operativa Diaria

- **Alta de Socios:** Los usuarios pueden usar la página con `[biodevas_alta]`.
- **Registro de Horas:** Los voluntarios envían sus horas desde su área privada o el formulario `[biodevas_voluntariado]`.
- **Aprobación de Horas:** El administrador debe revisar y aprobar las horas desde el panel de administración para que computen en el total del socio.

---

## 🔍 Checklist de Verificación Final

Realiza estas pruebas antes de abrir el registro de socios:

- [ ] **Formulario de Alta:** Rellena `[biodevas_alta]`. El socio debe crearse en estado "pendiente" y recibir un email (si está configurado).
- [ ] **Restricción Juvenil (>30 años):** Intenta registrarte con un plan Juvenil pero con una fecha de nacimiento que resulte en más de 30 años. El sistema debe bloquear el registro con un mensaje de error específico.
- [ ] **DNI/NIE:** Prueba a introducir un DNI incorrecto; el sistema debe bloquear el registro gracias a las utilidades de `biodevas-common`.
- [ ] **Código de Acceso:** Genera un código de acceso para un socio de prueba e intenta entrar en `[biodevas_mi_area]`.
- [ ] **Registro de Horas:** Envía un registro de 2 horas de voluntariado. Verifica que aparece como "pendiente" en el admin.
- [ ] **Aprobación de Horas:** Aprueba las horas desde el admin y verifica que el contador del socio se actualiza en su área privada.
- [ ] **Generación de Tarjeta:** Haz clic en "Descargar Tarjeta Digital" desde el panel de socio y verifica que el PDF se genera correctamente con los datos del usuario.
- [ ] **Integración Gateway:** (Si está activo) Al elegir un plan de pago en el alta, el sistema debe redirigir correctamente a la pasarela.

¡Sistema de socios listo para funcionar!
