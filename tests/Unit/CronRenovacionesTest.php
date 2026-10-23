<?php
/**
 * Los avisos de renovación del cron: a 30, 15 y 7 días.
 *
 * Es el recordatorio que evita que alguien se entere de que su cuota venció cuando ya no puede entrar al centro.
 * Dos cosas se prueban aquí: que **cada escalón salga una sola vez** (si se repite, el socio deja de leerlos) y
 * que el enlace del correo sea el que toca: el pago si se puede cobrar, y si no, la página de renovación.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Cron_Manager;
use Convoca\Members\Email_Manager;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Email_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/Cron_Manager.php';

/**
 * Avisos de renovación.
 */
final class CronRenovacionesTest extends TestCase {

	/**
	 * Gestor del cron.
	 *
	 * @var Cron_Manager
	 */
	private Cron_Manager $cron;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
		Email_Manager::install_defaults();
		$this->cron = new Cron_Manager();
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
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_wp_actions'], $GLOBALS['_wp_site'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'emails'     => array(),
			'posts'      => array(),
			'locks'      => array(),
			'db_inserts' => array(),
		);

		$reflejo = new \ReflectionClass( \Convoca\Core\Logger::class );
		foreach ( array( 'log_depth' => 0, 'log_timestamps' => array() ) as $propiedad => $valor ) {
			if ( $reflejo->hasProperty( $propiedad ) ) {
				$reflejo->getProperty( $propiedad )->setValue( null, $valor );
			}
		}
	}

	/**
	 * Invoca un método privado del cron.
	 *
	 * @param string $metodo Método.
	 */
	private function privado( string $metodo ): void {
		( new \ReflectionMethod( Cron_Manager::class, $metodo ) )->invoke( $this->cron );
	}

	/**
	 * Un socio activo al que le toca renovar.
	 *
	 * @param int    $id       Socio.
	 * @param string $enviado  Último aviso enviado.
	 */
	private function socio( int $id = 55, string $enviado = '' ): void {
		$ficha                             = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Ada Lovelace',
		);
		$GLOBALS['_test_posts'][ $id ]      = $ficha;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $ficha;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_estado_miembro'    => 'activo',
			'_convoca_email'             => 'socia' . $id . '@example.test',
			'_convoca_forma_pago'        => 'cuota',
			'_convoca_plan'              => 'bronze',
			'_convoca_last_renewal_notice' => $enviado,
		);
	}

	/**
	 * Correos enviados.
	 *
	 * @return array<int, array>
	 */
	private function correos(): array {
		return $GLOBALS['_wp_stores']['emails'] ?? array();
	}

	/**
	 * El aviso de 30 días sale una vez y queda anotado.
	 */
	public function test_el_aviso_de_30_dias(): void {
		$this->socio( 55 );
		// Tres consultas (30, 15 y 7 días): sólo la primera encuentra a alguien.
		$GLOBALS['_wp_stores']['col_cola'] = array( array( 55 ), array(), array() );

		$this->privado( 'process_renewals' );

		$this->assertCount( 1, $this->correos(), 'un correo' );
		$this->assertSame( 'socia55@example.test', $this->correos()[0]['to'] );
		$this->assertSame(
			'30',
			(string) get_post_meta( 55, '_convoca_last_renewal_notice', true ),
			'queda anotado cuál se mandó'
		);
	}

	/**
	 * Y no se repite: el mismo escalón no vuelve a salir.
	 */
	public function test_el_aviso_no_se_repite(): void {
		$this->socio( 55, '30' );
		$GLOBALS['_wp_stores']['col_cola'] = array( array( 55 ), array(), array() );

		$this->privado( 'process_renewals' );

		$this->assertSame( array(), $this->correos(), 'ya lo tenía: no se le insiste' );
	}

	/**
	 * Los tres escalones, cada uno con su plantilla y su marca.
	 */
	public function test_los_tres_escalones(): void {
		$this->socio( 55 );
		$GLOBALS['_wp_stores']['col_cola'] = array( array( 55 ), array( 55 ), array( 55 ) );

		$this->privado( 'process_renewals' );

		$this->assertCount( 3, $this->correos(), 'tres avisos: 30, 15 y 7 días' );
		$this->assertSame(
			'7',
			(string) get_post_meta( 55, '_convoca_last_renewal_notice', true ),
			'la marca termina en el último escalón enviado'
		);
	}

	/**
	 * Las dos páginas del plugin, que en el arnés no existían y hacían indistinguibles los dos enlaces.
	 */
	private function paginas(): void {
		$GLOBALS['_wp_stores']['shortcodes']['convoca_renovar'] = static function () {
			return '';
		};
		$GLOBALS['_wp_stores']['shortcodes']['convoca_pago'] = static function () {
			return '';
		};
		$GLOBALS['_wp_stores']['get_var_mapa']                = array(
			'[convoca_renovar]' => 700,
			'[convoca_pago]'    => 701,
		);
	}

	/**
	 * El enlace del correo: sin pasarela activa, a la página de renovación.
	 */
	public function test_el_enlace_sin_pasarela(): void {
		$this->socio( 55 );
		$this->paginas();

		// Las dos URL tienen que ser distintas, o la prueba no distingue una rama de la otra.
		$this->assertNotSame( Cron_Manager::renewal_page_url(), Cron_Manager::payment_page_url() );

		$enlace = ( new \ReflectionMethod( Cron_Manager::class, 'get_renewal_link' ) )->invoke( $this->cron, 55 );

		$this->assertSame(
			Cron_Manager::renewal_page_url(),
			(string) $enlace,
			'sin pasarela no hay nada que cobrar: a la página de renovación'
		);
	}

	/**
	 * Y si había un pago a medias, sí se le lleva a pagar: es el enlace firmado.
	 */
	public function test_el_enlace_con_un_pago_pendiente(): void {
		$this->socio( 55 );
		$this->paginas();
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_pago_id'] = 201;

		$enlace = ( new \ReflectionMethod( Cron_Manager::class, 'get_renewal_link' ) )->invoke( $this->cron, 55 );

		$this->assertSame(
			Cron_Manager::payment_page_url(),
			(string) $enlace,
			'con un pago a medias se le lleva a pagar, no a la página de renovación (sin pasarela, el enlace ' .
			'firmado degrada a la página de pago)'
		);
	}

	/**
	 * Un socio de voluntariado renueva por horas: se le manda a su página, no al pago.
	 */
	public function test_el_enlace_de_voluntariado(): void {
		$this->socio( 55 );
		$this->paginas();
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_forma_pago'] = 'voluntariado';
		// Se le pone un pago a medias a propósito: con `cuota` eso le llevaría al pago firmado (véase la prueba
		// anterior), así que si el enlace sigue yendo a la página de renovación es por la forma de pago.
		$GLOBALS['_wp_stores']['post_meta'][55]['_convoca_pago_id'] = 201;

		$enlace = ( new \ReflectionMethod( Cron_Manager::class, 'get_renewal_link' ) )->invoke( $this->cron, 55 );

		$this->assertSame(
			Cron_Manager::renewal_page_url(),
			(string) $enlace,
			'el voluntariado no paga cuota: a la página de renovación, no al pago'
		);
	}

	/**
	 * Sin nadie a quien avisar no sale ningún correo (y no revienta).
	 */
	public function test_sin_candidatos(): void {
		$GLOBALS['_wp_stores']['col_cola'] = array( array(), array(), array() );

		$this->privado( 'process_renewals' );

		$this->assertSame( array(), $this->correos() );
	}
}
