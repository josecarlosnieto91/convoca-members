<?php
/**
 * El re-alta: quien se fue y vuelve.
 *
 * La ficha histórica se **reactiva**, no se duplica. Tres cosas se prueban aquí, y las tres se rompen calladas:
 * que conserve su número de socio y su antigüedad, que no quede una segunda ficha de la misma persona, y que los
 * restos de la baja (fecha, aviso de vencimiento) desaparezcan — si se quedan, el socio vuelve **y** sigue dado
 * de baja a la vez, y el cron le manda avisos de vencimiento de una cuota que acaba de reanudar.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Process_Member;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php';
require_once dirname( __DIR__, 2 ) . '/includes/Process_Member.php';

/**
 * Re-alta de socio.
 */
final class ProcessMemberReactivacionTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_wp_actions'], $GLOBALS['_wp_stub_next_id'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'emails'     => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'locks'      => array(),
			'db_inserts' => array(),
		);
		remove_all_filters( 'convoca_members_plans' );
	}

	/**
	 * La ficha histórica que vuelve.
	 *
	 * @param int   $id   Id.
	 * @param array $meta Metadatos (se añaden a los de una baja normal).
	 */
	private function ficha_en_baja( int $id = 55, array $meta = array() ): void {
		$post = (object) array(
			'ID'           => $id,
			'post_type'    => 'miembro',
			'post_status'  => 'publish',
			'post_title'   => 'Nombre antiguo',
			'post_date'    => '2024-01-10 10:00:00',
			'post_content' => '',
		);
		$GLOBALS['_test_posts'][ $id ]             = $post;
		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array_merge(
			array(
				'_convoca_estado_miembro'          => 'baja',
				'_convoca_numero_socio'            => '12',
				'_convoca_email'                   => 'ada@example.test',
				'_convoca_dni'                     => '00000000T',
				'_convoca_fecha_baja'              => '2025-06-30',
				'_convoca_ultimo_aviso_vencimiento' => '2025-06-01',
				'_convoca_fecha_alta'              => '2024-01-10',
			),
			$meta
		);
		// El deduplicador tiene que encontrarla: es el `get_var` de la comprobación por DNI/correo.
		$GLOBALS['_wp_stores']['get_var'] = (string) $id;
	}

	/**
	 * Los datos del re-alta.
	 *
	 * @param array $extra Campos que se añaden o pisan.
	 * @return array
	 */
	private function realta( array $extra = array() ): array {
		return array_merge(
			array(
				'nombre'           => 'Ada Lovelace',
				'dni'              => '00000000t',
				'email'            => 'ada@example.test',
				'fecha_nacimiento' => '1990-01-01',
				'telefono'         => '600000000',
				'direccion'        => 'Calle de prueba 1',
				'municipio'        => 'Oviedo',
				'plan'             => 'bronze',
				'forma_pago'       => 'transferencia',
				'rgpd'             => '1',
				'tareas'           => 'Nada',
			),
			$extra
		);
	}

	/**
	 * Vuelve con **su** ficha: mismo número de socio, datos al día y sin rastro de la baja.
	 */
	public function test_la_ficha_reactivada_conserva_su_numero(): void {
		$this->ficha_en_baja( 55 );

		$resultado = Process_Member::register( $this->realta() );

		$this->assertIsArray( $resultado, 'sin error' );
		$this->assertSame( 55, (int) $resultado['id'], 'es la misma ficha, no una nueva' );
		$this->assertSame(
			'12',
			(string) get_post_meta( 55, '_convoca_numero_socio', true ),
			'el número de socio no se vuelve a repartir: es su antigüedad'
		);
		$this->assertSame( 'Ada Lovelace', (string) get_post( 55 )->post_title, 'y el título se pone al día' );
		$this->assertSame( 'bronze', (string) get_post_meta( 55, '_convoca_plan', true ) );

		// Los restos de la baja: si se quedan, el socio está activo y de baja a la vez.
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_fecha_baja', true ), 'sin fecha de baja' );
		$this->assertSame(
			'',
			(string) get_post_meta( 55, '_convoca_ultimo_aviso_vencimiento', true ),
			'y sin el aviso de vencimiento colgando'
		);
		$this->assertSame(
			'',
			(string) get_post_meta( 55, '_convoca_solicitud_renovacion', true ),
			'la marca de «es una renovación» es temporal: se pone para el correo y se quita'
		);
	}

	/**
	 * Y no deja una segunda ficha de la misma persona.
	 */
	public function test_no_se_duplica_la_ficha(): void {
		$this->ficha_en_baja( 55 );
		$antes = count( $GLOBALS['_wp_stores']['posts'] );

		Process_Member::register( $this->realta() );

		$this->assertSame( $antes, count( $GLOBALS['_wp_stores']['posts'] ), 'ni una ficha más' );
		$this->assertArrayNotHasKey( 56, $GLOBALS['_wp_stores']['posts'], 'y desde luego no una con id nuevo' );
	}

	/**
	 * El correo de solicitud se manda **diciendo que es una renovación**, y luego se avisa del re-alta.
	 */
	public function test_avisa_de_la_renovacion_y_del_realta(): void {
		$this->ficha_en_baja( 55 );
		$GLOBALS['_wp_actions'] = array();

		Process_Member::register( $this->realta() );

		$acciones = array_column( (array) $GLOBALS['_wp_actions'], 'hook' );
		$this->assertContains( 'convoca_members_email_solicitud', $acciones, 'el correo de solicitud' );
		$this->assertContains( 'convoca_members_reactivado', $acciones, 'y el aviso de re-alta' );
	}

	/**
	 * Sin pasarela activa no se crea ningún cobro: se le manda a la web, no a pagar.
	 */
	public function test_sin_pasarela_no_hay_cobro(): void {
		$this->ficha_en_baja( 55 );

		$resultado = Process_Member::register( $this->realta() );

		$this->assertArrayNotHasKey( 'gateway_error', $resultado );
		$this->assertStringContainsString( 'members_success=1', (string) $resultado['redirect'] );
		$this->assertSame( '', (string) get_post_meta( 55, '_convoca_pago_id', true ), 'ningún pago creado' );
	}

	/**
	 * Si vuelve por voluntariado, el periodo empieza hoy pero **la antigüedad no se toca**.
	 */
	public function test_voluntariado_empieza_periodo_hoy_y_conserva_el_alta(): void {
		$this->ficha_en_baja( 55 );

		Process_Member::register( $this->realta( array( 'forma_pago' => 'voluntariado' ) ) );

		$this->assertSame(
			current_time( 'Y-m-d' ),
			(string) get_post_meta( 55, '_convoca_fecha_inicio_periodo', true ),
			'un ciclo de voluntariado nuevo empieza hoy'
		);
		$this->assertSame(
			'2024-01-10',
			(string) get_post_meta( 55, '_convoca_fecha_alta', true ),
			'pero la fecha de alta original se conserva: es su antigüedad'
		);
	}

	/**
	 * Y si la ficha histórica desapareció por el camino, se dice —no se crea una nueva a ciegas.
	 */
	public function test_si_la_ficha_no_existe_se_avisa(): void {
		// El metadato dice «baja» pero la ficha ya no está: es el caso de una ficha borrada a mano, y lo que no
		// puede pasar es que se cree otra en silencio con los datos de la persona.
		$GLOBALS['_wp_stores']['get_var']           = '55';
		$GLOBALS['_wp_stores']['post_meta'][55]     = array(
			'_convoca_estado_miembro' => 'baja',
			'_convoca_numero_socio'   => '12',
		);

		$resultado = Process_Member::register( $this->realta() );

		$this->assertInstanceOf( \WP_Error::class, $resultado, 'un error, no un alta silenciosa' );
		$this->assertSame( 'reactivation_failed', $resultado->get_error_code() );
	}

	/**
	 * El panel de socio no se cae si su ficha ya no está.
	 *
	 * Un `_convoca_member_id` que apunta al vacío (ficha borrada a mano) dejaba `get_post()` en `null` y el panel
	 * leía `->post_title` a ciegas: warning de PHP y saludo sin nombre. Se comprueba el **warning**, que es la
	 * única señal que distingue un panel sano de uno que revienta en silencio.
	 */
	public function test_el_panel_aguanta_una_ficha_borrada(): void {
		require_once dirname( __DIR__, 2 ) . '/public/class-mi-area.php';

		$avisos = array();
		set_error_handler(
			static function ( $errno, $errstr ) use ( &$avisos ) {
				$avisos[] = $errstr;

				return true;
			}
		);
		try {
			$area   = new \Convoca\Members\Mi_Area();
			$pintar = new \ReflectionMethod( get_class( $area ), 'render_dashboard' );
			ob_start();
			$pintar->invoke( $area, 999 );
			$html = (string) ob_get_clean();
		} finally {
			restore_error_handler();
		}

		$this->assertSame( array(), $avisos, 'sin warnings de PHP: la ficha ausente no es un error del panel' );
		$this->assertStringContainsString( 'Hola', $html, 'y el panel se pinta igual' );
	}
}
