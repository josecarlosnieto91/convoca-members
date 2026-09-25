# Spec — Quién puede acreditar horas de voluntariado

**Estado:** aprobada · **Componentes:** convoca-members (regla y aprobación), convoca-enroll (motor de horas)
**Origen:** hallazgo de la E2E de Lugg (2026-09-25): el formulario de alta promete que marcando el
compromiso se podrá renovar sin cuota, pero el motor de horas no lo acepta. Formulario y motor dicen
cosas distintas.

## Modelo de negocio (decidido con el flujo real, no por intuición)

**El compromiso del alta es una SOLICITUD. La acreditación de horas exige estar aprobado como
voluntario por la asociación** (opción B).

Por qué B y no A (el compromiso habilita automáticamente):

- Las horas son la **vía de renovación sin cuota** y alimentan los **certificados**: son un derecho
  que la asociación debe poder verificar. Con A, un checkbox en un formulario público otorgaría horas
  renovables (y certificables) sin ningún control: bastaría marcarlo para no pagar nunca.
- Es el modelo que el producto **ya implementa**: `Admin_Voluntariado` existe con los estados
  pendiente (0) / aprobado (1) / revocado (−1), envía el acuerdo de incorporación al aprobar y notifica
  la aprobación por correo; y `convoca-shifts` ya usa `voluntario_aprobado` para permitir turnos.
- El formulario específico de voluntariado guarda la solicitud como `_convoca_voluntario_aprobado = 0`
  («pending approval»): la propia interfaz declara el compromiso como pendiente de aprobación.

## Los tres estados son distintos y se mantienen separados

| Estado | Dónde vive | Quién lo escribe |
|---|---|---|
| Condición de socio | ficha `miembro` · `_convoca_estado_miembro` | ciclo de vida de Members |
| **Solicitud** de voluntariado | ficha `miembro` · `_convoca_es_voluntario` | alta de socios y formulario de voluntariado |
| **Aprobación** (habilita horas) | **usuario WP** · rol `voluntario_aprobado` + `_convoca_voluntario_aprobado` (0/1/−1) | `Admin_Voluntariado::approve_volunteer()` / `revoke_volunteer()` |

## Fuente de verdad y sincronización

- **Fuente de verdad del permiso**: el **usuario de WordPress** (rol `voluntario_aprobado` o
  `_convoca_voluntario_aprobado === '1'`). Es donde escribe la aprobación y donde ya miran Enroll y
  Shifts.
- La **ficha** refleja la solicitud, y la aprobación **la sincroniza** (`_convoca_es_voluntario = 1`)
  para que la administración vea en el socio lo mismo que concede: si se aprueba a alguien que no lo
  había marcado, la ficha lo refleja. No se duplica el estado de aprobación en la ficha.
- Se retira del motor de horas la clave huérfana `_convoca_es_voluntario` **en el usuario**: no la
  escribe nadie en el flujo real (verificado en el código y en Lugg: 0 usuarios), y su parecido con el
  post meta de la ficha inducía a confundir solicitud con permiso.

## Regla única

`Admin_Voluntariado::puede_acreditar_horas( int $user_id ): bool` — verdadera si el usuario tiene el
rol `voluntario_aprobado` o su `_convoca_voluntario_aprobado` vale `'1'`. El motor de horas de Enroll
aplica la misma condición (delegando en Members si está activo, para no duplicar la regla).

Sin cuenta de WordPress **no hay permiso posible**: no hay dónde registrar la aprobación ni a quién
acreditar (el alta lo deja vinculado por email hasta que exista cuenta).

## Qué se comunica al socio

El texto del formulario de alta deja de prometer renovación sin cuota por el mero hecho de marcar la
casilla: dirá que se **solicita** el voluntariado, que la asociación lo aprueba, y que con las horas
aprobadas se podrá renovar el año siguiente sin cuota. Lo que se le dice y lo que hace el motor pasan
a coincidir.

## Criterios de aceptación

1. Alta sin voluntariado → no puede acreditar horas.
2. Alta con compromiso (sin aprobación) → **no** puede acreditar horas.
3. Voluntario aprobado → puede acreditar horas.
4. Voluntariado revocado → deja de poder (y no acredita nuevas horas).
5. Marcar la asistencia antes de la aprobación no acredita nada; después de aprobar, sí.
6. La renovación por horas cuenta exactamente las horas acreditadas.
7. Baja y re-alta no cambian el permiso de voluntariado por sí solas.
8. Usuario sin cuenta de WordPress → no se acreditan horas (sin errores).
9. Usuario con cuenta pero sin aprobación → no se acreditan horas.
10. `phpunit`, PHPStan, PHPCS y PCP verdes; CI verde; E2E en Lugg.
