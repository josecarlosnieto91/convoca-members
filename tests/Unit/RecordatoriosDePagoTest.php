<?php
/**
 * El escalado de recordatorios de pago.
 *
 * `process_pending_payments()` escribe a socios que deben dinero, así que sus dos reglas finas son las que
 * importan:
 *
 *  - **no se repite**: a los 30 días de impago no se manda otro correo igual. El orden es `primero` → `segundo` →
 *    `ultimo`, y lo ya enviado no se vuelve a enviar. Sin esto, un socio que no paga recibe el mismo aviso cada
 *    día y el que sí quiere pagar deja de leerlos;
 *  - **se re-chequea el estado dentro del bloqueo**: entre que el cron elige al socio y que manda el correo, el
 *    socio puede haber pagado (o el cron puede estar corriendo dos veces). Después de `acquire_lock()` se vuelve
 *    a leer el estado y se salta si ya no está `pendiente_pago`.
 *
 * Los plazos son 3, 7 y 14 días desde que entró en `pendiente_pago`. Si esa fecha no está guardada, se toma
 * `post_modified` y **se guarda**, para que el cálculo no se mueva en cada pasada.
 *
 * Excepciones escritas, no escondidas:
 *
 *  - **el re-chequeo del estado dentro del bloqueo** no se mide: haría falta que la meta cambiara entre la consulta
 *    y la segunda lectura, es decir simular una carrera con dos procesos. La rama existe y se lee, pero un mutante
 *    que la quite sobrevive.
 *  - **el bloqueo por socio** tampoco: `Utils::acquire_lock()` es de Core y tiene sus propias pruebas; forzarlo
 *    desde aquí sería probar el arnés de otro plugin.
 *
 * El observable que se mide es `_convoca_last_reminder`, que es lo que decide el siguiente envío; el correo en sí
 * depende de plantillas y del `Email_Manager`, y comprobarlo ataría esta prueba a su implementación.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Cron_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Cron_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * Recordatorios de pago.
 */
final class RecordatoriosDePagoTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Cron.
	 *
	 * @var Cron_Manager
	 */
	private Cron_Manager $cron;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'    => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'     => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'locks'    => $GLOBALS['_wp_stores']['locks'] ?? null,
			'opts'     => $GLOBALS['_wp_stores']['options'] ?? array(),
			'errlog'   => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();

		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-recordatorios-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );

		$this->cron = ( new \ReflectionClass( Cron_Manager::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['meta'];
		$GLOBALS['_wp_stores']['options']   = $this->previo['opts'];

		if ( null === $this->previo['locks'] ) {
			unset( $GLOBALS['_wp_stores']['locks'] );
		} else {
			$GLOBALS['_wp_stores']['locks'] = $this->previo['locks'];
		}

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
		}
	}

	/**
	 * Siembra un socio.
	 *
	 * @param int    $id      ID.
	 * @param string $estado  Estado.
	 * @param array  $extra   Metas de más.
	 * @param int    $dias    Días desde que entró en pendiente de pago.
	 * @param string $modif   `post_modified`.
	 * @return void
	 */
	private function socio( int $id, string $estado, array $extra = array(), int $dias = 0, string $modif = '2026-01-01 00:00:00' ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'            => $id,
			'post_type'     => 'miembro',
			'post_status'   => 'publish',
			'post_title'    => 'Socia ' . $id,
			'post_date'     => '2026-01-01 00:00:00',
			'post_modified' => $modif,
		);

		$meta = array_merge(
			array( '_convoca_estado_miembro' => $estado ),
			$extra
		);
		if ( $dias >= 0 && ! array_key_exists( '_convoca_fecha_pendiente_pago', $meta ) ) {
			$meta['_convoca_fecha_pendiente_pago'] = gmdate( 'Y-m-d H:i:s', time() - ( $dias * 86400 ) );
		}

		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;
	}

	/**
	 * Ejecuta la pasada.
	 *
	 * @return void
	 */
	private function pasada(): void {
		$m = new \ReflectionMethod( Cron_Manager::class, 'process_pending_payments' );
		$m->invoke( $this->cron );
	}

	/**
	 * El recordatorio marcado en una ficha.
	 *
	 * @param int $id ID.
	 * @return string
	 */
	private function marcado( int $id ): string {
		return (string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_last_reminder'] ?? '' );
	}

	/* ── Escalado ────────────────────────────────────────────────────── */

	/**
	 * Sin socios pendientes de pago no se hace nada.
	 */
	public function test_sin_pendientes_no_hace_nada(): void {
		$this->socio( 500, 'activo' );

		$this->pasada();

		$this->assertSame( '', $this->marcado( 500 ) );
	}

	/**
	 * **A los tres días empieza el primer aviso.**
	 */
	public function test_a_los_tres_dias_va_el_primero(): void {
		$this->socio( 500, 'pendiente_pago', array(), 3 );

		$this->pasada();

		$this->assertSame( 'primero', $this->marcado( 500 ) );
	}

	/**
	 * Antes de tres días no se molesta a nadie.
	 */
	public function test_antes_de_tres_dias_no_se_avisa(): void {
		$this->socio( 500, 'pendiente_pago', array(), 1 );

		$this->pasada();

		$this->assertSame( '', $this->marcado( 500 ) );
	}

	/**
	 * A los siete días, y con el primero ya enviado, toca el segundo.
	 */
	public function test_a_los_siete_dias_toca_el_segundo(): void {
		$this->socio( 500, 'pendiente_pago', array( '_convoca_last_reminder' => 'primero' ), 7 );

		$this->pasada();

		$this->assertSame( 'segundo', $this->marcado( 500 ) );
	}

	/**
	 * A los siete días sin nada enviado, el escalado va por **antigüedad**: manda el segundo.
	 *
	 * Queda escrito porque no es lo que uno supone: el nivel se decide por los días que lleva en `pendiente_pago`,
	 * no por lo que se le haya enviado antes. Un socio que lleva una semana sin pagar y no recibió el primer aviso
	 * —porque el cron no corrió, o porque el plugin se instaló después— recibe el correo del segundo escalón.
	 *
	 * No se toca aquí: es una decisión de producto (el texto del correo habla de los días, no de «segundo aviso») y
	 * cambiarla es de JC, no mía. Lo que sí queda fijado es que **no se queda sin aviso**.
	 */
	public function test_sin_aviso_previo_a_los_siete_dias_manda_el_segundo_escalon(): void {
		$this->socio( 500, 'pendiente_pago', array(), 7 );

		$this->pasada();

		$this->assertSame( 'segundo', $this->marcado( 500 ) );
	}

	/**
	 * A los catorce días toca el último.
	 */
	public function test_a_los_catorce_dias_toca_el_ultimo(): void {
		$this->socio( 500, 'pendiente_pago', array( '_convoca_last_reminder' => 'segundo' ), 14 );

		$this->pasada();

		$this->assertSame( 'ultimo', $this->marcado( 500 ) );
	}

	/**
	 * **`segundo` no se repite.**
	 *
	 * Con `segundo` ya marcado y solo ocho días, no hay nada nuevo que decir.
	 */
	public function test_el_segundo_no_se_repite(): void {
		$this->socio( 500, 'pendiente_pago', array( '_convoca_last_reminder' => 'segundo' ), 8 );

		$this->pasada();

		$this->assertSame( 'segundo', $this->marcado( 500 ), 'no se vuelve a marcar ni se degrada' );
	}

	/**
	 * **`ultimo` es el final: no se manda nada más.**
	 *
	 * Es lo que impide que un socio que no paga reciba un correo igual cada día durante meses.
	 *
	 * Se mide por el **registro**, no por la meta: la meta ya vale `ultimo` antes de la pasada, así que comprobarla
	 * pasaría aunque el correo se enviara otra vez. `Logger` escribe «Recordatorio de pago escalonado … enviado» con
	 * `error_log()`, y aquí el registro está redirigido a un fichero.
	 */
	public function test_despues_de_ultimo_no_se_manda_nada_mas(): void {
		$this->socio( 500, 'pendiente_pago', array( '_convoca_last_reminder' => 'ultimo' ), 40 );

		$this->pasada();

		$this->assertSame( 'ultimo', $this->marcado( 500 ), 'no hay un «ultimísimo»' );
		// Si no se escribe nada, el fichero no llega a existir: eso es un vacío, no un error.
		$log = (string) ( $GLOBALS['_errlog_tmp'] ?? '' );
		$registro = is_file( $log ) ? (string) file_get_contents( $log ) : '';
		$this->assertStringNotContainsString( 'escalonado', $registro, 'no se envió ningún correo' );
	}

	/**
	 * Y el contraste: cuando sí toca, el registro lo dice.
	 *
	 * Sin esta, la prueba de arriba pasaría también si el registro no se escribiera nunca.
	 */
	public function test_cuando_toca_el_registro_lo_dice(): void {
		$this->socio( 500, 'pendiente_pago', array(), 4 );

		$this->pasada();

		$this->assertStringContainsString(
			'escalonado',
			(string) file_get_contents( (string) $GLOBALS['_errlog_tmp'] ),
			'el envío deja rastro'
		);
	}

	/* ── Guardas ────────────────────────────────────────────────────── */

	/**
	 * **Si el socio ya no está en `pendiente_pago`, no se le avisa.**
	 *
	 * Entre que el cron elige al socio y que manda el correo, el socio puede haber pagado. El re-chequeo va dentro
	 * del bloqueo, que es lo que hace que no se cuele.
	 */
	public function test_si_el_socio_ya_pago_no_se_le_avisa(): void {
		// La ficha entra en la consulta por la meta, pero al re-leer el estado ya es `activo`. Se logra sembrando
		// el estado que la consulta necesita y cambiándolo con el propio recorrido del doble: se prueba con una
		// segunda ficha que ya está activa y no debe marcarse.
		$this->socio( 501, 'activo', array(), 30 );

		$this->pasada();

		$this->assertSame( '', $this->marcado( 501 ), 'un socio al día no recibe recordatorios' );
	}

	/**
	 * **Si no hay fecha de entrada en pendiente, se usa `post_modified` y se guarda.**
	 *
	 * Sin guardarla, el cálculo se movería en cada pasada y el escalado se quedaría atascado.
	 */
	public function test_sin_fecha_de_pendiente_se_toma_la_de_modificacion_y_se_guarda(): void {
		$this->socio(
			500,
			'pendiente_pago',
			array( '_convoca_fecha_pendiente_pago' => '' ),
			0,
			gmdate( 'Y-m-d H:i:s', time() - ( 5 * 86400 ) )
		);

		$this->pasada();

		$meta = $GLOBALS['_wp_stores']['post_meta'][500];
		$this->assertNotSame( '', (string) ( $meta['_convoca_fecha_pendiente_pago'] ?? '' ), 'la fecha queda guardada' );
		$this->assertSame( 'primero', $this->marcado( 500 ), 'y con ella se decide el aviso' );
	}

	/**
	 * Una fecha imparseable se salta sin romper la pasada.
	 */
	public function test_una_fecha_imparseable_no_rompe_la_pasada(): void {
		$this->socio( 500, 'pendiente_pago', array( '_convoca_fecha_pendiente_pago' => 'no-es-una-fecha' ), -1 );
		$this->socio( 501, 'pendiente_pago', array(), 5 );

		$this->pasada();

		$this->assertSame( '', $this->marcado( 500 ), 'la rota se salta' );
		$this->assertSame( 'primero', $this->marcado( 501 ), 'y la buena se atiende igual' );
	}

	/**
	 * **El aviso no se manda dos veces en la misma pasada.**
	 *
	 * Si el bloqueo por socio no funcionara y la pasada se solapara, un socio recibiría el mismo correo dos veces.
	 */
	public function test_el_aviso_no_se_repite_en_la_misma_pasada(): void {
		$this->socio( 500, 'pendiente_pago', array(), 4 );

		$this->pasada();
		$primera = $this->marcado( 500 );
		$this->pasada();

		$this->assertSame( 'primero', $primera );
		$this->assertSame( 'primero', $this->marcado( 500 ), 'la segunda pasada no cambia nada' );
	}
}
