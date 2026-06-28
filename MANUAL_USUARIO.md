# MANUAL_USUARIO.md — Convoca Members v2.6.2

> Guía para administradores: gestión de socios, membresías, cuotas y voluntariado.

## 1. Introducción

Convoca Members gestiona el ciclo de vida completo de los socios de la asociación: altas, renovaciones, bajas, cuotas, estados de membresía, horas de voluntariado, certificados, y documentación. Ideal para asociaciones, ONGs y entidades sin ánimo de lucro.

**Requiere:** convoca-core activo.

## 2. Configuración inicial

Accede a **Convoca → Members → Ajustes**:

| Ajuste | Descripción |
|--------|-------------|
| **Tipos de membresía** | Define los tipos (Numerario, Juvenil, Familiar…) |
| **Período de membresía** | Duración estándar en meses (por defecto 12) |
| **Tipos de cuota** | Abono de cuota, Voluntariado |
| **Importes por tipo** | Precio de cada tipo de membresía |
| **Email de bienvenida** | Plantilla del email que recibe el nuevo socio |
| **Email de renovación** | Plantilla del recordatorio de renovación |

### Tipos de membresía predefinidos

| Tipo | Características |
|------|----------------|
| **Numerario** | Miembro de pleno derecho con voz y voto |
| **Juvenil** | Para menores de 30 años |
| **Familiar** | Unidad familiar (2+ personas) |

Los tipos son personalizables desde el panel de ajustes.

## 3. Alta de socio

### Desde el formulario público

El futuro socio accede a la página de alta (`/alta-socios/`) y completa:

1. Datos personales (nombre, DNI, email, teléfono, dirección)
2. Tipo de membresía deseada
3. Forma de contribución (cuota o voluntariado)
4. Aceptación de RGPD y comunicaciones
5. El sistema crea el registro y envía email de bienvenida

### Desde el panel de administración

1. Ve a **Socios → Añadir nuevo**
2. Rellena los campos obligatorios en el panel lateral **Datos del socio**:

| Campo | Obligatorio | Notas |
|-------|-------------|-------|
| **DNI/NIE** | ✅ | Validación automática de formato |
| **Email** | ✅ | Se usará para comunicaciones y acceso |
| **Teléfono** | Recomendado | Formato: XXX XXX XXX |
| **Fecha de nacimiento** | Recomendado | Para calcular edad y tipo juvenil |
| **Dirección postal** | Opcional | Calle, ciudad, CP |
| **Tipo de membresía** | ✅ | Numerario, Juvenil, Familiar |
| **Tipo de cuota** | ✅ | Abono de cuota o Voluntariado |
| **Fecha de alta** | ✅ | Se establece automáticamente al publicar |
| **Consentimiento RGPD** | ✅ | Marcar como "Sí" |

3. Haz clic en **Publicar**

### Shortcode para el formulario público

```
[convoca_alta_socio]
```

Coloca este shortcode en la página de alta de socios.

## 4. Estados de membresía

Cada socio tiene un estado visible en el listado **Socios → Todos los socios**:

| Estado | Significado | Acción |
|--------|-------------|--------|
| **Activo** | Membresía vigente | — |
| **Pendiente** | Alta reciente, esperando activación | Activar manualmente o esperar pago |
| **Suspendido** | Temporalmente inactivo (impago, sanción) | Reactivar desde el perfil del socio |
| **Expirado** | La membresía ha vencido | Renovar o dar de baja |
| **Baja** | El socio ha causado baja | Conservar para histórico |

### Cambiar estado

1. Edita el socio
2. En el panel lateral **Estado del socio**, selecciona el nuevo estado
3. **Fecha de renovación** se recalcula automáticamente al activar/reactivar
4. Haz clic en **Actualizar**

## 5. Renovaciones

### Renovación automática

El sistema detecta automáticamente cuando un socio rellena de nuevo el formulario de alta:

1. Si el email/DNI ya existe → se considera renovación
2. La fecha de fin se extiende 12 meses desde la nueva solicitud
3. Se registra en el historial del socio

### Renovación manual

1. Edita el socio
2. Actualiza **Fecha de renovación** a la nueva fecha
3. Cambia el estado a **Activo** si estaba expirado
4. Añade una nota en **Observaciones** explicando el motivo

## 6. Bajas

Las bajas son siempre **lógicas** (el registro se conserva para histórico):

1. Edita el socio
2. Cambia el estado a **Baja**
3. Establece **Fecha de baja** a la fecha actual
4. Añade el motivo en **Observaciones**

⚠️ **Nunca elimines** un socio manualmente. El sistema conserva todo el histórico.

## 7. Gestión de cuotas y pagos

Convoca Members se integra con **Convoca Gateway** para gestionar pagos.

### Registrar un pago manual

1. Ve a **Socios → [nombre] → Pagos**
2. Haz clic en **Añadir pago**
3. Rellena importe, concepto y fecha
4. El estado del socio se actualiza automáticamente

### Ver historial de pagos

El panel **Pagos** muestra todos los pagos del socio con fechas, importes y estados.

## 8. Voluntariado

### Activar a un socio como voluntario

1. Edita el socio
2. En **Tipo de cuota**, selecciona **Voluntariado**
3. Marca **Es voluntario/a** como "Sí"
4. El sistema crea automáticamente:
   - Ficha de voluntariado en **Voluntariado → Fichas**
   - Registro de horas

### Registrar horas de voluntariado

1. Ve a **Voluntariado → Registrar horas**
2. Selecciona el voluntario
3. Introduce fecha, horas y descripción de la actividad
4. Las horas quedan pendientes de aprobación
5. Un administrador las aprueba desde **Voluntariado → Pendientes**

### Gamificación de voluntariado

El sistema asigna **insignias** automáticamente según las horas acumuladas:

| Insignia | Horas |
|----------|-------|
| 🌱 Semilla | 10h |
| 🌿 Brote | 50h |
| 🌳 Guardián | 150h |
| 🏆 Maestro | 500h |

Las insignias son visibles en el perfil público del voluntario.

## 9. Certificados

### Generar certificado de voluntariado

1. Ve a **Voluntariado → [nombre] → Certificados**
2. Haz clic en **Generar certificado**
3. Selecciona el período (desde/hasta)
4. El sistema genera un PDF con:
   - Datos del voluntario
   - Horas totales en el período
   - Actividades realizadas
   - Código único de verificación (formato `VOL-AAAA-XXXXX`)

### Verificar un certificado

Cualquier persona puede verificar un certificado en la página pública:

```
[convoca_verificar_certificado]
```

Introduciendo el código `VOL-AAAA-XXXXX` se muestra la validez del certificado.

## 10. Verificación pública de membresía

Los socios pueden verificar su estado sin iniciar sesión:

```
[convoca_verificar_socio]
```

Introducen el código de socio (recibido por email) y ven:
- Estado de la membresía
- Fecha de alta y vencimiento
- Tipo de socio

## 11. GDPR y protección de datos

### Exportar datos de un socio

1. Edita el socio
2. Ve a **Herramientas GDPR** en el panel lateral
3. Haz clic en **Exportar datos** → descarga un JSON con todos los datos

### Derecho al olvido

1. En **Herramientas GDPR**, haz clic en **Anonimizar**
2. Se conserva el ID y fechas, se eliminan datos personales
3. ⚠️ Esta acción es irreversible

## 12. Email Manager

El sistema envía automáticamente:

| Email | Cuándo se envía |
|-------|----------------|
| **Bienvenida** | Al activar un nuevo socio |
| **Renovación** | 30, 15 y 7 días antes del vencimiento |
| **Recordatorio de pago** | 7 y 3 días después del vencimiento |
| **Certificado** | Al generar un certificado de voluntariado |

Las plantillas se personalizan en **Convoca → Members → Plantillas de email**.

## 13. Auditoría

Cada cambio en un socio genera una entrada de auditoría:

1. Ve a **Convoca → Registros**
2. Filtra por tipo "members"
3. Cada entrada muestra: fecha, usuario que hizo el cambio, campo modificado, valor anterior y nuevo

## 14. Shortcodes públicos

| Shortcode | Descripción |
|-----------|-------------|
| `[convoca_alta_socio]` | Formulario de alta para nuevos socios |
| `[convoca_mi_perfil]` | Perfil del socio logueado (nombre, email, estado, inscripciones) |
| `[convoca_verificar_socio]` | Verificación pública de membresía |
| `[convoca_verificar_certificado]` | Verificación pública de certificados |

## 15. Problemas comunes

| Problema | Solución |
|----------|----------|
| **No se envían emails** | Verifica **Convoca → Registros** para ver errores de envío |
| **Socio duplicado** | Fusiona desde **Socios → Herramientas → Fusionar**. Detecta por DNI/email |
| **Membresía no se renueva** | Verifica que el estado es "Activo" y la fecha de fin es futura |
| **No aparece en listados** | Comprueba que el estado no es "Borrador" |
| **Error al generar PDF** | Verifica que Dompdf está instalado (`composer install` en convoca-core) |
| **Certificado no válido** | Si fue revocado, aparece como "Revocado" en la verificación |
