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

## 5. Preguntas para JC (antes de empezar la fase 2)

1. **La entrevista**: ¿se cita con fecha y hora desde el sistema (y el voluntario la ve en su panel), o basta
   con anotar «entrevista hecha»?
2. **El acuerdo**: ¿vale con que el voluntario lo acepte en línea desde su panel, o se sube el PDF firmado a
   mano? (Hoy el PDF se genera y se guarda como documento.)
3. **La desvinculación**: ¿puede pedirla el propio voluntario desde su panel, o solo la junta?
4. **Horas al desvincularse**: se quedan como están, ¿no? (certificado de lo hecho, y el ciclo sigue contando
   lo aprobado hasta esa fecha).

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
