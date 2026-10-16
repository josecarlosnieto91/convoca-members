# Spec — El circuito del voluntariado: de la solicitud a la desvinculación

**Para quién.** Para la junta que acompaña a las personas voluntarias, y para la persona voluntaria, que debe
saber en qué punto está y qué le toca. Hoy el sistema sabe si alguien está aprobado o no, pero no **en qué
paso del camino está**: eso se lleva en la cabeza de quien coordina, y se pierde cuando esa persona cambia.

**Decidido con JC el 16/10/2026.** El circuito tiene cinco pasos y el sistema tiene que poder contarlos:

1. **Solicitud** de voluntariado.
2. **Entrevista inicial.**
3. **Firma del acuerdo** de voluntariado.
4. **Registro de horas** (con la aprobación de un monitor o de la administración).
5. **Desvinculación.**

Y una regla que manda sobre todas: **no puede existir un voluntario que no sea socio**. El primer año se es
socio abonando la cuota; a partir del segundo se puede elegir entre la cuota o las horas de voluntariado del
ciclo en curso, siempre que se haya llegado al mínimo del plan y las horas estén aprobadas.

---

## 1. Lo que ya existe (medido, no supuesto)

| Pieza | Dónde vive | Estado |
|---|---|---|
| Solicitud de voluntariado | Formulario público → `_convoca_es_voluntario=1` + usuario WP + `estado_miembro=pendiente_documentacion` | **Funciona** (con `do_action('convoca_voluntario_pendiente')` y correo a la junta) |
| Aprobación (habilita horas) | Usuario WP · `voluntario_aprobado` + `_convoca_voluntario_aprobado=1` | **Funciona** (`Admin_Voluntariado::approve_volunteer()`, con correo al interesado) |
| Revocación | `_convoca_voluntario_aprobado=-1` + quitar el rol | **Funciona** (`revoke_volunteer()`) |
| Acuerdo de incorporación | `PDF_Document` con la plantilla `acuerdo_incorporacion` | **Se genera**, pero no queda constancia de que se haya firmado |
| Registro de horas | `Hours_Manager` + pantalla de aprobación | **Funciona** (y desde hoy avisa por correo al responsable de voluntariado) |
| Renovación por horas | `CPT_Miembro::ciclo_voluntariado()` + `renovar_por_horas()` | **Funciona** (`members#45`; cuota del primer año obligatoria, `spec-cuota-…`) |
| Invariante «voluntario ⇒ socio» | `Roles_Miembro::reconciliar()` | **Funciona desde hoy** (`members#39`) |

**Lo que NO existe:** la entrevista, la constancia de la firma del acuerdo, y la desvinculación como paso con
su motivo y su fecha. Tampoco hay una pantalla que diga en qué punto está cada persona.

## 2. El modelo

Un itinerario por persona, en la ficha del socio, con estos estados:

```
solicitud → entrevista → acuerdo → activo → desvinculado
                 ↓            ↓        ↓
             (no sigue)   (no firma)  (causa + fecha)
```

| Estado | Significa | Quién lo mueve |
|---|---|---|
| `solicitud` | Ha pedido ser voluntario y aún no hay nada más | El formulario (automático) |
| `entrevista` | Se le ha citado / se ha hecho la entrevista inicial | La junta |
| `acuerdo` | Falta la firma del acuerdo de voluntariado | La junta (sube el firmado) o el propio voluntario (acepta) |
| `activo` | Puede acreditar horas y renovar por horas | La junta (es la aprobación de hoy) |
| `desvinculado` | Se ha ido, con su motivo y su fecha | La junta o el propio voluntario |

Cada paso guarda **fecha** y **quién** lo movió. En la ficha: `_convoca_voluntariado_estado`,
`_convoca_voluntariado_fechas` (por paso), `_convoca_voluntariado_notas`, `_convoca_voluntariado_motivo_baja`.

**Compatibilidad:** `_convoca_voluntario_aprobado` (0/1/−1) sigue siendo la verdad de «puede acreditar horas»
—la lee Convoca Shifts y no se toca—; el itinerario es el **contexto** (en qué paso está). Los que ya están
aprobados entran como `activo` sin pasar por los pasos anteriores.

## 3. Las pantallas

1. **Panel del socio → Voluntariado** (nuevo bloque): en qué paso está, qué falta por hacer y qué le toca a
   quién. Si es `acuerdo`, puede descargar el PDF y aceptar; si es `activo`, ve su ciclo de horas y su
   renovación; si quiere irse, pide la desvinculación desde ahí.
2. **Miembros → Voluntariado** (la pantalla de hoy, `Admin_Voluntariado`): pasa a ser el tablero del circuito,
   con las personas agrupadas por paso (cuántas esperando entrevista, cuántas sin firmar), y las acciones para
   mover a cada una: *Citar para entrevista*, *Marcar entrevista hecha*, *Registrar acuerdo firmado*, *Aprobar
   como voluntario*, *Desvincular (con motivo)*. El aviso de solicitudes pendientes que ya existe apunta aquí.
3. **Avisos por correo**: al pasar a `entrevista`, al voluntario con su cita; al firmarse el acuerdo, a la
   junta; y desde hoy, cuando hay horas pendientes, al correo del voluntariado.

## 4. Fases (pequeñas, cada una utilizable por sí sola)

| Fase | Qué se construye | Se puede usar para |
|---|---|---|
| **1** | El estado del itinerario en la ficha + el tablero por pasos (sin cambios de flujo) | Ver de un vistazo quién está en cada punto |
| **2** | Acciones para mover de paso, con fecha y quién | Dejar de llevar el circuito en la cabeza |
| **3** | El bloque del panel del socio + aceptación del acuerdo en línea | Que el voluntario sepa qué le toca |
| **4** | Desvinculación con motivo + aviso a la junta + efecto en horas y renovación | Cerrar bien, y saber por qué se va |
| **5** | Correos de cada paso, con plantillas editables | Que no dependa de que alguien se acuerde de escribir |

**La fase 1 no toca el flujo actual:** es leer lo que ya hay y ordenarlo por pasos.

## 5. Respuestas de JC (16/10/2026) y qué se ha construido

> **Las cuatro, confirmadas por JC** («Ok a las decisiones», 16/10/2026). Lo construido se queda como está:
> cita con fecha y hora, acuerdo aceptado en línea, baja pedida por la persona y confirmada por la junta, y
> las horas intactas.

Las cuatro preguntas de esta spec, contestadas por JC, y lo que hay hecho con cada una:

1. **La entrevista: cita con fecha y hora, y además un cuestionario.** «La entrevista se cita con fecha y hora.
   También puede resolverse con un formulario de preguntas y respuestas a cumplimentar (las propias preguntas de
   la entrevista, cada organización podría tener las suyas)». Hecho: se cita desde el tablero con fecha y hora
   (y se avisa por correo), y las preguntas se configuran en los ajustes, una por línea. La persona las contesta
   desde su panel; con eso, o marcándola como hecha desde administración, **la entrevista está resuelta**.
2. **Y desde ese momento es voluntario y/o monitor: «solo voluntario o voluntario y monitor, pero nunca monitor
   solo».** Hecho: la regla vive en `Roles_Miembro::anadir()` —donde nace la condición— y se refuerza al
   escribir los roles de WordPress; quitar el voluntariado quita el monitorado. Con prueba que lo fija.
3. **El acuerdo lo acepta la persona.** «Puede generársele un PDF con sus datos y una firma, como ya se firman
   otros documentos de Convoca». Hecho: se acepta desde el panel con un botón, y el acuerdo se genera **firmado**
   por el camino de siempre (`PDF_Document` + `Convoca\Core\Signature`), quedando la fecha, la huella y el
   documento en la ficha.
4. **La desvinculación: la pide el voluntario, o caduca su membresía, o fuerza mayor de la junta.** Hecho:
   las tres causas, con su fecha y su motivo. La baja que pide la persona **no** la da de baja: la confirma la
   junta. La caducidad se aplica sola en el repaso diario (solo a quien **era** voluntariado aprobado: una
   solicitud no se «desvincula» de nada).
5. **Las horas, intactas.** «Al desvincularse las horas quedan como están, el certificado debe poder
   descargarse y validarse contra la plataforma, y se sigue contando lo aprobado hasta esa fecha». Verificado
   tras una desvinculación real: horas y certificado siguen ahí; el certificado ya tiene sus rutas
   (`/me/certificate`, `/me/certificate/download`) y su página pública de verificación, y ese camino **no mira
   la condición de voluntariado**, solo las horas.

### Estado de las fases

| Fase | Estado |
|---|---|
| 1 · El estado en la ficha y el tablero por pasos | **Hecha** (y verificada en la demo con el recorrido completo) |
| 2 · Mover de paso, con fecha y quién | **Hecha** (citar, entrevista hecha, con monitor, desvincular con causa y confirmar la baja) |
| 3 · El bloque del panel del socio y la aceptación del acuerdo | **Hecha** (cuestionario, acuerdo firmado y petición de baja) |
| 4 · Desvinculación con motivo, aviso y efecto en horas y renovación | **Hecha** (las tres causas, caducidad automática, horas y certificado intactos) |
| 5 · Correos de cada paso | **Hecha**, con las cinco plantillas **editables desde los ajustes** (`convoca_email_templates`, 15 → 20): cita, acuerdo, baja solicitada, desvinculación y horas pendientes, con sus variables (`{fecha_cita}`, `{horas}`, `{enlace_horas}`…). Verificado en la demo y en Lugg: cambiar el texto cambia el correo. |

## 6. Criterios de aceptación

1. En la ficha de una persona voluntaria se ve **en qué paso está** y desde cuándo.
2. La pantalla de Voluntariado agrupa por paso y permite mover a cada persona, con fecha y autor registrados.
3. Una persona `activa` puede acreditar horas, y una `desvinculada` no; su historial de horas **no se pierde**.
4. Al pasar a `acuerdo`, el PDF del acuerdo se puede descargar; al registrarse la firma, queda constancia con
   fecha.
5. El estado del itinerario y `_convoca_voluntario_aprobado` **nunca se contradicen** (una prueba que lo fije,
   como con la cuota y la condición de socio).
6. No se rompe nada de lo que ya funciona: el alta, la aprobación, los turnos, las horas ni la renovación por
   horas.

## 7. Lo que NO se toca

- La verdad de «puede acreditar horas» sigue siendo `_convoca_voluntario_aprobado`, que es lo que lee Convoca
  Shifts. El itinerario no la sustituye: la acompaña.
- El circuito de socios (`Estado_Miembro`) no se toca: el voluntariado no cambia la condición de socio, y la
  membresía se sostiene por cuota o por horas, como está decidido.
- La aprobación de horas sigue siendo de un monitor o de la administración, sin excepciones nuevas.
