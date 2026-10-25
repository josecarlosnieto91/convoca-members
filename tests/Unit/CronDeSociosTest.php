<?php
/**
 * El cron de socios: enlaces de pago y expiraciones.
 *
 * Dos cosas de este fichero mueven dinero y no dan error si se rompen:
 *
 *  - **el enlace de renovación**: si no se reutiliza el pago pendiente, cada aviso crea un pago nuevo y el socio ve
 *    cinco cargos pendientes de la misma cuota; si se genera sin importe, el TPV cobra 0;
 *  - **la expiración**: da de baja a quien tiene la fecha de renovación pasada. Si la fecha se compara mal, se da de
 *    baja a socios al día.
 *
 * El camino «con pasarela» va en proceso aparte: `Features::is_gateway_active()` mira `class_exists()`, y una clase
 * declarada en una prueba **no se descarga**. Sin proceso aparte, el resto de la suite viviría con la pasarela activa.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Cron_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Cron_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * Enlaces y expiraciones.
 */
final class CronDeSociosTest extends TestCase {

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
			'options'  => $GLOBALS['_wp_stores']['options'] ?? array(),
			'col'      => $GLOBALS['_wp_stores']['col'] ?? null,
			'col_cola' => $GLOBALS['_wp_stores']['col_cola'] ?? null,
			'actions'  => $GLOBALS['_wp_actions'] ?? array(),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_stores']['options']   = array();
		$GLOBALS['_wp_actions']             = array();
		unset( $GLOBALS['_wp_stores']['col'], $GLOBALS['_wp_stores']['col_cola'] );

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
		$GLOBALS['_wp_stores']['options']   = $this->previo['options'];
		$GLOBALS['_wp_actions']             = $this->previo['actions'];

		if ( null === $this->previo['col'] ) {
			unset( $GLOBALS['_wp_stores']['col'] );
		} else {
			$GLOBALS['_wp_stores']['col'] = $this->previo['col'];
		}
		if ( null === $this->previo['col_cola'] ) {
			unset( $GLOBALS['_wp_stores']['col_cola'] );
		} else {
			$GLOBALS['_wp_stores']['col_cola'] = $this->previo['col_cola'];
		}
	}

	/**
	 * Siembra una ficha de socio.
	 *
	 * @param array $meta Metas.
	 * @param int   $id   ID.
	 * @return int
	 */
	private function ficha( array $meta, int $id = 500 ): int {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Socia ' . $id,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;

		return $id;
	}

	/**
	 * Llama un método privado con argumentos.
	 *
	 * @param string $metodo Método.
	 * @param array  $args   Argumentos.
	 * @return mixed
	 */
	private function llamar( string $metodo, array $args = array() ) {
		$m = new \ReflectionMethod( Cron_Manager::class, $metodo );

		return $m->invokeArgs( $this->cron, $args );
	}

	/* ── Enlace de renovación, sin pasarela ──────────────────────────── */

	/**
	 * **Un voluntario no recibe enlace de pago**: renueva por horas, no pagando.
	 *
	 * Mandarle un enlace de cobro a quien colabora gratis es, como mínimo, un error de respeto.
	 */
	public function test_un_voluntario_no_recibe_enlace_de_pago(): void {
		$id = $this->ficha(
			array(
				'_convoca_forma_pago' => 'voluntariado',
				'_convoca_importe_cuota' => '30',
			)
		);

		$enlace = (string) $this->llamar( 'get_renewal_link', array( $id ) );

		$this->assertSame( Cron_Manager::renewal_page_url(), $enlace, 'va a la página de renovación, no a pagar' );
	}

	/**
	 * **Sin importe no se genera un cobro de 0 €.**
	 *
	 * Importe 0 se queda en la página de renovación: quien no debe nada no tiene que pasar por el TPV.
	 */
	public function test_sin_importe_no_se_genera_un_cobro(): void {
		$id = $this->ficha(
			array(
				'_convoca_forma_pago'    => 'transferencia',
				'_convoca_plan'          => 'plan-que-no-existe',
				'_convoca_importe_cuota' => '0',
			)
		);

		$enlace = (string) $this->llamar( 'get_renewal_link', array( $id ) );

		$this->assertSame( Cron_Manager::renewal_page_url(), $enlace );
	}

	/**
	 * Sin pasarela cargada, el enlace es la página de pago propia (no un TPV que no existe).
	 */
	public function test_sin_pasarela_va_a_la_pagina_de_pago_propia(): void {
		$this->assertFalse( \Convoca\Core\Features::is_gateway_active(), 'el escenario es sin pasarela' );

		$enlace = (string) $this->llamar( 'signed_payment_url', array( 900 ) );

		$this->assertSame( Cron_Manager::payment_page_url(), $enlace );
	}

	/**
	 * Los dos enlaces públicos no salen vacíos: si salieran, el correo llevaría un `href=""`.
	 */
	public function test_los_enlaces_publicos_no_estan_vacios(): void {
		$this->assertNotSame( '', Cron_Manager::renewal_page_url() );
		$this->assertNotSame( '', Cron_Manager::payment_page_url() );
	}

	/* ── Expiración ──────────────────────────────────────────────────── */

	/**
	 * **Se procesa cada socio vencido, uno a uno, y se avisa.**
	 *
	 * `check_member_status()` es quien decide si suspende o da de baja; el cron solo tiene que pasarle **todos** los
	 * que tocan. Si la lista se cortara, los socios se quedarían activos para siempre sin que nadie lo notara.
	 */
	public function test_se_procesa_cada_socio_vencido_y_se_avisa(): void {
		$GLOBALS['_wp_stores']['col'] = array( 501, '502', 503 );

		$this->llamar( 'process_expirations' );

		$avisados = array();
		foreach ( (array) $GLOBALS['_wp_actions'] as $accion ) {
			if ( 'convoca_members_membership_expired' === ( $accion['hook'] ?? '' ) ) {
				$avisados[] = (int) ( $accion['args'][0] ?? 0 );
			}
		}

		$this->assertSame( array( 501, 502, 503 ), $avisados, 'los tres, en orden y como enteros' );
	}

	/**
	 * Sin socios vencidos no se avisa de nada: un cron que manda eventos vacíos despierta a los webhooks sin motivo.
	 */
	public function test_sin_socios_vencidos_no_se_avisa(): void {
		$GLOBALS['_wp_stores']['col'] = array();

		$this->llamar( 'process_expirations' );

		$this->assertSame( array(), array_column( (array) $GLOBALS['_wp_actions'], 'hook' ) );
	}

	/**
	 * La fecha se compara con **la de hoy**, no con una cadena rara.
	 *
	 * Se comprueba la consulta que se lanza: si el marcador no se sustituye por la fecha, no hay forma de saber qué
	 * socios están vencidos.
	 */
	public function test_la_consulta_lleva_la_fecha_de_hoy(): void {
		$GLOBALS['_wp_stores']['col'] = array();
		$GLOBALS['_wp_stores']['col_reads'] = array();

		$this->llamar( 'process_expirations' );

		$consultas = (array) ( $GLOBALS['_wp_stores']['col_reads'] ?? array() );
		$con_fecha = array_values(
			array_filter(
				$consultas,
				static fn( $q ) => false !== strpos( (string) $q, '_convoca_fecha_renovacion' )
			)
		);

		$this->assertNotEmpty( $con_fecha, 'se consultó por la fecha de renovación' );
		$this->assertStringNotContainsString( '%s', (string) $con_fecha[0], 'el marcador se sustituyó' );
	}
}
