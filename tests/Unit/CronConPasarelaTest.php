<?php
/**
 * El enlace de renovación **con pasarela de pago activa**.
 *
 * Es el camino donde se crea dinero: si no se reutiliza el pago pendiente, cada aviso de renovación (a 30, 15 y 7
 * días) crea un pago nuevo y el socio ve **tres cargos pendientes de la misma cuota**; si el importe se pasa mal, el
 * TPV cobra otra cosa.
 *
 * Todas las pruebas de este fichero van en **proceso aparte**: `Features::is_gateway_active()` mira `class_exists()`,
 * y la clase del stub no se puede descargar. En el proceso padre, el resto de la suite seguiría viendo la pasarela
 * activa y sus pruebas medirían otra cosa.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Cron_Manager;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Cron_Manager.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * Enlaces con pasarela.
 *
 * La clase entera va en proceso aparte: el stub declara `Convoca\Gateway\Payment_Handler`, y esa clase no se descarga.
 * Si el proceso padre la viera, `Features::is_gateway_active()` sería `true` para toda la suite y las pruebas del camino
 * «sin pasarela» medirían otra cosa.
 *
 * Medido: ni la anotación `@runInSeparateProcess` en el docblock de la clase ni el atributo en la clase sirven —el
 * atributo **solo se puede poner en un método**—, y el stub terminaba viéndose en el proceso padre: cuatro pruebas de
 * otros ficheros se pusieron rojas. Va en cada método.
 */
final class CronConPasarelaTest extends TestCase {

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
	 * Carga la pasarela de mentira y monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		// El stub se carga AQUÍ, no en la cabecera del fichero: así solo existe en el proceso que ejecuta la prueba.
		require_once dirname( __DIR__ ) . '/stubs/gateway-payment-handler.php';

		$this->previo = array(
			'posts'   => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'    => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'options' => $GLOBALS['_wp_stores']['options'] ?? array(),
			'urls'    => $GLOBALS['_gw_urls'] ?? array(),
			'creados' => $GLOBALS['_gw_creados'] ?? array(),
			'errlog'  => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_stores']['options']   = array();
		$GLOBALS['_gw_urls']                = array();
		$GLOBALS['_gw_creados']             = array();

		// El `Logger` del núcleo escribe con `error_log()` porque `WP_DEBUG` está definida. En un proceso aparte PHPUnit
		// trata esa escritura como salida inesperada del test y la convierte en excepción, así que el camino feliz
		// fallaba **por el log**, no por el código. Se manda a un fichero temporal mientras dura la prueba.
		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-cron-test.log';
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
		$GLOBALS['_wp_stores']['options']   = $this->previo['options'];
		$GLOBALS['_gw_urls']                = $this->previo['urls'];
		$GLOBALS['_gw_creados']             = $this->previo['creados'];

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
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
	 * Llama un método privado.
	 *
	 * @param string $metodo Método.
	 * @param array  $args   Argumentos.
	 * @return mixed
	 */
	private function llamar( string $metodo, array $args = array() ) {
		$m = new \ReflectionMethod( Cron_Manager::class, $metodo );

		return $m->invokeArgs( $this->cron, $args );
	}

	/**
	 * La pasarela está activa en este escenario (si no, todo lo de aquí no mide lo que dice medir).
	 *
	 * @return void
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_el_escenario_tiene_la_pasarela_activa(): void {
		$this->assertTrue( \Convoca\Core\Features::is_gateway_active() );
	}

	/**
	 * **El importe se pasa en céntimos y redondeado.** Un `30,50 €` tiene que llegar como `3050`, no como `30`.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_el_importe_se_pasa_en_centimos(): void {
		$id = $this->ficha(
			array(
				'_convoca_forma_pago'    => 'transferencia',
				'_convoca_plan'          => 'plan-que-no-existe',
				'_convoca_importe_cuota' => '30.50',
				'_convoca_email'         => 'socia@example.test',
			)
		);

		$this->llamar( 'get_renewal_link', array( $id ) );

		$this->assertCount( 1, $GLOBALS['_gw_creados'], 'se creó un pago' );
		$this->assertSame( 3050, $GLOBALS['_gw_creados'][0]['amount_cents'], 'en céntimos, sin decimales' );
		$this->assertSame( $id, $GLOBALS['_gw_creados'][0]['origin_id'] );
		$this->assertSame( 'members', $GLOBALS['_gw_creados'][0]['origin'] );
		$this->assertSame( 'socia@example.test', $GLOBALS['_gw_creados'][0]['payer_email'] );
	}

	/**
	 * El importe del **plan** se usa cuando la ficha no tiene uno guardado.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_el_importe_sale_del_plan_si_la_ficha_no_lo_tiene(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array(
			'socio' => array(
				'label'    => 'Socio',
				'price'    => 45,
				'hours'    => 0,
				'modalidad' => 'Numerario',
			),
		);
		$id = $this->ficha(
			array(
				'_convoca_forma_pago' => 'transferencia',
				'_convoca_plan'       => 'socio',
			)
		);

		$this->llamar( 'get_renewal_link', array( $id ) );

		$this->assertSame( 4500, $GLOBALS['_gw_creados'][0]['amount_cents'] ?? null );
	}

	/**
	 * **Un pago pendiente se reutiliza, no se crea otro.**
	 *
	 * Es la guarda que impide que los avisos de 30, 15 y 7 días dejen al socio con tres cobros pendientes de la misma
	 * cuota. `$wpdb->get_var()` devuelve el ID del pago pendiente.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_un_pago_pendiente_se_reutiliza(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SELECT pm.post_id FROM' => '777' );

		$id = $this->ficha(
			array(
				'_convoca_forma_pago'    => 'transferencia',
				'_convoca_importe_cuota' => '30',
			)
		);

		$enlace = (string) $this->llamar( 'get_renewal_link', array( $id ) );

		$this->assertSame( array(), $GLOBALS['_gw_creados'], 'no se crea un pago nuevo' );
		$this->assertStringContainsString( '777', $enlace, 'el enlace es el del pago pendiente' );
		$this->assertSame( 777, $GLOBALS['_gw_urls'][0]['pago_id'] ?? null );
	}

	/**
	 * **El voluntario sigue sin enlace de pago aunque la pasarela esté activa.**
	 *
	 * Sin pasarela este caso se cumple "por accidente"; con pasarela es donde se ve que la guarda existe.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_un_voluntario_no_recibe_enlace_de_pago_ni_con_pasarela(): void {
		$id = $this->ficha(
			array(
				'_convoca_forma_pago'    => 'voluntariado',
				'_convoca_importe_cuota' => '30',
			)
		);

		$enlace = (string) $this->llamar( 'get_renewal_link', array( $id ) );

		$this->assertSame( Cron_Manager::renewal_page_url(), $enlace );
		$this->assertSame( array(), $GLOBALS['_gw_creados'], 'no se crea ningún cobro para quien colabora gratis' );
	}

	/**
	 * **Sin importe no se genera un cobro de 0 €**, ni con la pasarela activa.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_sin_importe_no_se_genera_un_cobro_ni_con_pasarela(): void {
		$id = $this->ficha(
			array(
				'_convoca_forma_pago'    => 'transferencia',
				'_convoca_plan'          => 'plan-que-no-existe',
				'_convoca_importe_cuota' => '0',
			)
		);

		$enlace = (string) $this->llamar( 'get_renewal_link', array( $id ) );

		$this->assertSame( Cron_Manager::renewal_page_url(), $enlace );
		$this->assertSame( array(), $GLOBALS['_gw_creados'], 'nadie cobra 0 €' );
	}

	/**
	 * **El token del enlace se crea una vez y se reutiliza.**
	 *
	 * Se guarda en la ficha del pago (`_convoca_link_key`) para que los enlaces largos sigan valiendo: si se generara
	 * uno nuevo en cada aviso, el enlace del correo de hace una semana dejaría de funcionar.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_el_token_del_enlace_se_crea_una_vez(): void {
		$GLOBALS['_wp_stores']['posts'][777]      = (object) array(
			'ID'          => 777,
			'post_type'   => 'pago',
			'post_status' => 'publish',
		);
		$GLOBALS['_wp_stores']['post_meta'][777] = array();

		$this->llamar( 'signed_payment_url', array( 777 ) );
		$primero = $GLOBALS['_wp_stores']['post_meta'][777]['_convoca_link_key'] ?? '';

		$this->llamar( 'signed_payment_url', array( 777 ) );
		$segundo = $GLOBALS['_wp_stores']['post_meta'][777]['_convoca_link_key'] ?? '';

		$this->assertNotSame( '', $primero, 'se creó el token' );
		$this->assertSame( $primero, $segundo, 'y se reutiliza: el enlace viejo sigue valiendo' );
		$this->assertCount( 2, $GLOBALS['_gw_urls'], 'dos enlaces construidos' );
		$this->assertSame( $primero, $GLOBALS['_gw_urls'][1]['token'], 'el segundo usa el mismo token' );
	}

	/**
	 * Si no se puede crear un pago, se usa el que ya tuviera la ficha; y si no hay ninguno, la página de renovación.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_sin_poder_crear_pago_se_usa_el_que_ya_habia(): void {
		$id = $this->ficha(
			array(
				'_convoca_forma_pago'    => 'transferencia',
				'_convoca_importe_cuota' => '0',
				'_convoca_pago_id'       => '888',
			)
		);
		$GLOBALS['_wp_stores']['posts'][888]      = (object) array(
			'ID'          => 888,
			'post_type'   => 'pago',
			'post_status' => 'publish',
		);
		$GLOBALS['_wp_stores']['post_meta'][888] = array();

		$enlace = (string) $this->llamar( 'get_renewal_link', array( $id ) );

		$this->assertStringContainsString( '888', $enlace, 'cae al pago que ya constaba' );
	}
}
