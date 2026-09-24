# Spec — Alta y renovación de socios: la cuota del primer año y las dos vías de renovación

> Estado: **propuesta para implementar** · Autor: Mika · Fecha: 2026-09-24
> Plugin: `convoca-members` (2.8.6) · Entorno verificado: Lugg (producción)

## 1. El cambio de concepto

**Antes (modelo actual, verificado en producción):** cuota y voluntariado son **excluyentes y permanentes**. Quien elige «HORAS DE VOLUNTARIADO» en el alta queda como socio con `forma_pago=voluntariado` y **nunca paga**, y renueva cada año acreditando horas.

**Ahora:** el voluntariado deja de ser una forma de no pagar el primer año.

1. **Primer año:** para ser socia/o hay que **abonar la cuota**, siempre. Sin pago no hay condición de socio: la ficha se queda en `pendiente_pago` y no se convierte en activa. El compromiso de voluntariado se puede firmar igualmente, pero **no sustituye a la cuota** ese año.
2. **Años siguientes:** se renueva por una de dos vías:
   - **Pagar** la cuota del plan, o
   - **Acreditar horas de voluntariado** del último año, **solo si alcanza el mínimo** que exige la asociación.

El mínimo **no se inventa ni se duplica**: es el campo `hours` del **plan** de cada persona
(`convoca_members_plans`). Verificado en producción:

| Plan | Cuota | Mínimo de horas |
|---|---|---|
| Lugg (Colaborador) | 50 € | **25 h** |
| Deva | 100 € | **50 h** |
| Bronze (inactivo) | 30 € | 15 h |
| Familiar | 0 € | 0 h → sin vía de horas (solo cuota) |

Un plan con `hours = 0` no ofrece vía de horas: se paga. Así la asociación puede regular el
mínimo por plan sin tocar código.

## 2. Estados y transiciones

Se mantiene la máquina de estados de `Estados` (`pendiente_documentacion`, `pendiente_pago`,
`activo`, `suspendido`, `baja_solicitada`, `baja`).

**Alta (primer ciclo)**

- `forma_pago` ∈ {tarjeta, bizum, transferencia} → estado `pendiente_pago`, cuota `pendiente` *(como hoy)*.
- Compromiso de voluntariado → **también** `pendiente_pago` + `_convoca_es_voluntario = 1` + acuerdo firmado.
  → **Cambio**: ya no cae en `pendiente_documentacion` ni deja la cuota como `activa` sin cobro.
- **Único camino a `activo`**: pago confirmado por el gateway *o* activación manual de la junta.

**Renovación (ciclos siguientes)** — al vencer `_convoca_fecha_renovacion`:

```
¿horas aprobadas del periodo >= hours del plan?
   sí → renueva: fecha_renovacion +1 año, fecha_inicio_periodo = vencimiento, sigue `activo`, cuota `activa`
   no → exige cuota:
        vencimiento + grace_suspend_days (1)  → `suspendido` (solo puede renovar)
        vencimiento + grace_baja_days (30)    → `baja` automática (con reintentos de cobro si aplica)
```
Es decir: **las horas son una alternativa al pago a partir del segundo ciclo**, y el
comportamiento de gracia/suspensión que ya existe se reutiliza tal cual.

## 3. Compatibilidad

- **Alta nueva**: aplica el modelo nuevo desde el primer día.
- **Socios ya activos por voluntariado**: su ciclo en curso **no se altera**; al llegar su
  vencimiento se les aplica la regla de las dos vías (horas o pago). Si sus horas no llegan,
  entran en la gracia existente en lugar de caer de golpe: se les exige cuota, no se les da de baja.
- Nada de esto cambia claves de metadatos ni contratos públicos: se reutilizan `_convoca_es_voluntario`,
  `_convoca_forma_pago`, `_convoca_estado_cuota`, `_convoca_fecha_renovacion`, `_convoca_fecha_inicio_periodo`.

## 4. Cambios previstos (pequeños y localizados)

| Fichero | Cambio |
|---|---|
| `includes/Process_Member.php` | El alta con voluntariado pasa a `pendiente_pago` (cuota `pendiente`), conservando `_convoca_es_voluntario=1` y el acuerdo. |
| `includes/CPT_Miembro.php` | `check_member_status()`: la vía de horas se evalúa **para todos** (no solo si `forma_pago=voluntariado`) y **solo** cuando el ciclo no es el primero (alta del año en curso = cuota obligatoria). |
| `public/class-form-handler.php` + template | Paso 3: el bloque de voluntariado deja de ser «en vez de pagar» y pasa a ser «además, compromiso para el próximo año». Los métodos de pago quedan siempre visibles y obligatorios. |
| `readme.txt` / `CHANGELOG.md` | Regla nueva documentada. |
| `docs/` | Este documento. |

## 5. Pruebas de aceptación (se ejecutan antes de dar por hecho)

Unitarias (PHPUnit, sin WordPress):
1. Alta con voluntariado → `pendiente_pago`, `es_voluntario=1`, cuota `pendiente`.
2. Alta económica → `pendiente_pago` (sin regresión).
3. Renovación sin horas suficientes → exige cuota (no renueva sola).
4. Renovación con horas ≥ mínimo del plan → renueva (`fecha_renovacion` +1 año, sigue `activo`).
5. Plan con `hours=0` → no hay vía de horas.
6. Mínimo tomado del plan, no de un valor fijo en el código.

Navegador en Lugg (producción, con datos de prueba y limpieza posterior):
7. Alta por la vía de voluntariado → comprobar que **exige pago** (ficha en `pendiente_pago`, con orden de pago).
8. La ficha no se convierte en `activo` sin pago confirmado.

## 6. Lo que NO se toca

- El gateway, Redsys y el flujo de pago (funciona: alta → orden de pago → confirmación).
- La gracia, la suspensión y la baja automática por impago.
- Los planes y sus importes (son configuración del sitio).
- Nada de Core ni de otros plugins.
