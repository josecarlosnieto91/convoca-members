<?php
/**
 * El saneado de los ajustes del plugin.
 *
 * Todo lo que se guarda en `convoca_members_settings` pasa por aquí antes de tocar la base: los datos de la entidad,
 * los plazos de renovación, los **planes de cuota con sus precios** y los campos del formulario de voluntariado.
 *
 * Es el sitio donde un descuido cuesta dinero de verdad: un precio que se guarda en negativo o una cuota a cero no dan
 * error en pantalla, se quedan guardadas y el cobro falla (o no cobra) semanas después.
 *
 * `sanitize_settings()` acota sus números con `max`/`min`. Estas pruebas fijan ese contrato y buscan el contraste donde
 * no lo hay.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Settings;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-settings.php';

/**
 * Saneado.
 */
final class SaneadoDeAjustesTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Pantalla.
	 *
	 * @var Admin_Settings
	 */
	private Admin_Settings $ajustes;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'options'    => $GLOBALS['_wp_stores']['options'] ?? array(),
			'transients' => $GLOBALS['_wp_stores']['transients'] ?? array(),
		);

		$GLOBALS['_wp_stores']['options']    = array();
		$GLOBALS['_wp_stores']['transients'] = array();

		$this->ajustes = ( new \ReflectionClass( Admin_Settings::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['options']    = $this->previo['options'];
		$GLOBALS['_wp_stores']['transients'] = $this->previo['transients'];
	}

	/* ── Ajustes generales ───────────────────────────────────────────── */

	/**
	 * Los plazos de renovación se **acotan**: no se puede guardar una suspensión de 400 días ni una baja de 0.
	 *
	 * Es lo bueno que ya hace el producto, y se fija aquí para que no se pierda: un plazo disparatado deja a los
	 * socios suspendidos para siempre o de baja al día siguiente.
	 */
	public function test_los_plazos_de_renovacion_se_acotan(): void {
		$salida = $this->ajustes->sanitize_settings(
			array(
				'grace_suspend_days'      => '400',
				'grace_baja_days'         => '0',
				'auto_renew_max_attempts' => '99',
				'auto_renew_retry_days'   => '-5',
			)
		);

		$this->assertSame( 30, $salida['grace_suspend_days'], 'la suspensión no pasa de 30 días' );
		$this->assertSame( 1, $salida['grace_baja_days'], 'la baja no baja de 1 día' );
		$this->assertSame( 10, $salida['auto_renew_max_attempts'], 'los reintentos no pasan de 10' );
		$this->assertSame( 1, $salida['auto_renew_retry_days'], 'los días entre reintentos no bajan de 1' );
	}

	/**
	 * Los valores por defecto son sensatos cuando no llega nada: nunca cero días de gracia.
	 */
	public function test_los_plazos_tienen_valores_por_defecto_sensatos(): void {
		$salida = $this->ajustes->sanitize_settings( array() );

		$this->assertSame( 1, $salida['grace_suspend_days'] );
		$this->assertSame( 30, $salida['grace_baja_days'] );
		$this->assertSame( 3, $salida['auto_renew_max_attempts'] );
		$this->assertSame( 5, $salida['auto_renew_retry_days'] );
	}

	/**
	 * Un correo mal escrito no se guarda como si fuera un correo.
	 */
	public function test_un_correo_invalido_no_se_guarda(): void {
		$salida = $this->ajustes->sanitize_settings( array( 'admin_email' => 'no-es-un-correo' ) );

		$this->assertSame( '', $salida['admin_email'], 'un correo inválido se descarta' );
	}

	/**
	 * Los documentos: una URL vacía se queda vacía, **no** se convierte en `href=""`.
	 */
	public function test_un_documento_sin_url_no_inventa_un_enlace(): void {
		$salida = $this->ajustes->sanitize_settings( array( 'doc_codigo_etico' => '' ) );

		$this->assertSame( '', $salida['doc_codigo_etico'] );
	}

	/**
	 * Una URL que no es http(s) se descarta: `javascript:` en un documento de la entidad sería un enlace ejecutable.
	 */
	public function test_una_url_peligrosa_se_descarta(): void {
		$salida = $this->ajustes->sanitize_settings( array( 'doc_voluntariado' => 'javascript:alert(1)' ) );

		$this->assertStringNotContainsString( 'javascript', $salida['doc_voluntariado'] );
	}

	/**
	 * La copia de correos es 0 o 1, nunca «vacío»: se consulta como número.
	 */
	public function test_la_copia_de_correos_es_un_cero_o_un_uno(): void {
		$this->assertSame( 0, $this->ajustes->sanitize_settings( array() )['copy_all_emails'] );
		$this->assertSame( 1, $this->ajustes->sanitize_settings( array( 'copy_all_emails' => '1' ) )['copy_all_emails'] );
	}

	/**
	 * Con algo que no es un array, el saneado no revienta: devuelve vacío.
	 */
	public function test_una_entrada_que_no_es_array_no_revienta(): void {
		$this->assertSame( array(), $this->ajustes->sanitize_settings( 'basura' ) );
		$this->assertSame( array(), $this->ajustes->sanitize_plans( 'basura' ) );
		$this->assertSame( array(), $this->ajustes->sanitize_volunteer_fields( 'basura' ) );
	}

	/* ── Planes de cuota ─────────────────────────────────────────────── */

	/**
	 * Un plan se guarda con sus campos.
	 */
	public function test_un_plan_se_guarda_con_sus_campos(): void {
		$salida = $this->ajustes->sanitize_plans(
			array(
				'socio' => array(
					'label'           => 'Socio',
					'price'           => '30',
					'hours'           => '10',
					'modalidad'       => 'Numerario',
					'payment_methods' => array( 'transferencia', 'tarjeta' ),
					'url_bizum'       => 'https://example.test/bizum',
					'url_tarjeta'     => 'https://example.test/tarjeta',
					'advantages_raw'  => "Ventaja uno\nVentaja dos\n\n",
					'active'          => '1',
					'order'           => '2',
					'emoji'           => '🌱',
					'description'     => 'Cuota anual',
				),
			)
		);

		$plan = $salida['socio'];
		$this->assertSame( 'Socio', $plan['label'] );
		$this->assertSame( 30.0, $plan['price'] );
		$this->assertSame( 10.0, $plan['hours'] );
		$this->assertSame( array( 'transferencia', 'tarjeta' ), $plan['payment_methods'] );
		$this->assertSame( array( 'Ventaja uno', 'Ventaja dos' ), $plan['advantages'], 'las ventajas salen por línea' );
		$this->assertTrue( $plan['active'] );
		$this->assertSame( 2, $plan['order'] );
	}

	/**
	 * Un plan sin clave se descarta: la clave se usa en la URL de cobro.
	 */
	public function test_un_plan_sin_clave_se_descarta(): void {
		$salida = $this->ajustes->sanitize_plans( array( '' => array( 'label' => 'Sin clave', 'price' => '10' ) ) );

		$this->assertSame( array(), $salida );
	}

	/**
	 * Los planes salen **ordenados** por su `order`: es el orden en que se enseñan en el alta y en la web.
	 */
	public function test_los_planes_salen_ordenados(): void {
		$salida = $this->ajustes->sanitize_plans(
			array(
				'c' => array( 'label' => 'C', 'order' => '3' ),
				'a' => array( 'label' => 'A', 'order' => '1' ),
				'b' => array( 'label' => 'B', 'order' => '2' ),
			)
		);

		$this->assertSame( array( 'a', 'b', 'c' ), array_keys( $salida ) );
	}

	/**
	 * **Un precio negativo no se guarda.**
	 *
	 * Un plan de cuota con precio negativo es dinero: en el peor caso el TPV rechaza el cobro y el socio no puede
	 * pagar; en el mejor, alguien cobra al revés. `sanitize_settings()` acota sus números con `max`/`min`; aquí no hay
	 * ninguna cota.
	 */
	public function test_un_precio_negativo_no_se_guarda(): void {
		$salida = $this->ajustes->sanitize_plans(
			array( 'socio' => array( 'label' => 'Socio', 'price' => '-50' ) )
		);

		$this->assertGreaterThanOrEqual( 0, $salida['socio']['price'], 'un precio negativo no tiene sentido' );
	}

	/**
	 * **Unas horas negativas tampoco**: son las horas incluidas en la cuota.
	 */
	public function test_unas_horas_negativas_no_se_guardan(): void {
		$salida = $this->ajustes->sanitize_plans(
			array( 'socio' => array( 'label' => 'Socio', 'price' => '30', 'hours' => '-10' ) )
		);

		$this->assertGreaterThanOrEqual( 0, $salida['socio']['hours'], 'unas horas negativas no tienen sentido' );
	}

	/**
	 * **`payment_methods` tiene que ser una lista.** Si llega una cadena, el producto hace `array_map()` sobre ella y
	 * revienta con un `TypeError` en PHP 8: guardar los ajustes desde un POST manipulado deja la pantalla en fatal.
	 */
	public function test_unos_metodos_de_pago_que_no_son_lista_no_revientan(): void {
		$salida = $this->ajustes->sanitize_plans(
			array(
				'socio' => array(
					'label'           => 'Socio',
					'price'           => '30',
					'payment_methods' => 'transferencia',
				),
			)
		);

		$this->assertIsArray( $salida['socio']['payment_methods'], 'siempre una lista' );
	}

	/**
	 * Una ventaja en blanco no se guarda: dejaría un hueco en la lista del plan.
	 */
	public function test_las_ventajas_en_blanco_no_se_guardan(): void {
		$salida = $this->ajustes->sanitize_plans(
			array(
				'socio' => array(
					'label'          => 'Socio',
					'price'          => '30',
					'advantages_raw' => "Ventaja\n\n   \nOtra",
				),
			)
		);

		$this->assertSame( array( 'Ventaja', 'Otra' ), array_values( $salida['socio']['advantages'] ) );
	}

	/**
	 * Un plan sin marcar `active` queda inactivo, no activo por accidente.
	 */
	public function test_un_plan_sin_marcar_queda_inactivo(): void {
		$salida = $this->ajustes->sanitize_plans( array( 'socio' => array( 'label' => 'Socio', 'price' => '30' ) ) );

		$this->assertFalse( $salida['socio']['active'] );
	}

	/**
	 * La modalidad por defecto no se queda vacía.
	 */
	public function test_la_modalidad_tiene_valor_por_defecto(): void {
		$salida = $this->ajustes->sanitize_plans( array( 'socio' => array( 'label' => 'Socio', 'price' => '30' ) ) );

		$this->assertSame( 'Numerario', $salida['socio']['modalidad'] );
	}

	/* ── Campos del formulario de voluntariado ───────────────────────── */

	/**
	 * Un campo sin nombre se descarta; con nombre, se guarda.
	 */
	public function test_un_campo_sin_nombre_se_descarta(): void {
		$salida = $this->ajustes->sanitize_volunteer_fields(
			array(
				array(
					'name'  => '',
					'label' => 'Sin nombre',
				),
				array(
					'name'  => 'talla',
					'label' => 'Talla',
					'type'  => 'text',
				),
			)
		);

		$this->assertCount( 1, $salida );
		$this->assertSame( 'talla', $salida[0]['name'] );
	}

	/**
	 * El tipo por defecto es texto, y `required` es booleano.
	 */
	public function test_el_tipo_y_el_obligatorio_tienen_valores_claros(): void {
		$salida = $this->ajustes->sanitize_volunteer_fields(
			array(
				array(
					'name'     => 'talla',
					'label'    => 'Talla',
					'required' => 'on',
				),
			)
		);

		$this->assertSame( 'text', $salida[0]['type'] );
		$this->assertTrue( $salida[0]['required'] );
	}

	/**
	 * El nombre del campo se normaliza a clave: va a ir en un `meta_key`.
	 */
	public function test_el_nombre_del_campo_se_normaliza(): void {
		$salida = $this->ajustes->sanitize_volunteer_fields(
			array(
				array(
					'name'  => 'Talla De Camiseta',
					'label' => 'Talla',
				),
			)
		);

		$this->assertSame( 'talladecamiseta', $salida[0]['name'], 'sin espacios ni mayúsculas' );
	}

	/* ── Pista activa ────────────────────────────────────────────────── */

	/**
	 * Sin `tab` en la URL se enseña la general.
	 */
	public function test_sin_pestana_se_ensena_la_general(): void {
		$_GET     = array();
		$_REQUEST = array();

		$metodo = new \ReflectionMethod( Admin_Settings::class, 'get_active_tab' );
		$tab    = $metodo->invoke( $this->ajustes );

		$this->assertSame( 'general', $tab );
	}

	/**
	 * Una pestaña que no existe llega saneada y **la pantalla sigue pintando algo**: el `switch` de `render()` tiene
	 * `case 'general': default:`, así que no se queda en blanco.
	 *
	 * Lo que se fija aquí es que `get_active_tab()` **no inventa**: devuelve la clave saneada y deja la decisión al
	 * despachador. (Se comprobó a mano: `render()` acaba en `default` → general.)
	 */
	public function test_una_pestana_inventada_llega_saneada_y_el_despachador_decide(): void {
		$_GET     = array( 'tab' => 'INVENTADA-<script>' );
		$_REQUEST = $_GET;

		$metodo = new \ReflectionMethod( Admin_Settings::class, 'get_active_tab' );
		$tab    = $metodo->invoke( $this->ajustes );

		$this->assertSame( 'inventada-script', $tab, 'saneada como clave: minúsculas y sin signos' );

		$_GET     = array();
		$_REQUEST = array();
	}

	/**
	 * Y una válida se respeta.
	 */
	public function test_una_pestana_valida_se_respeta(): void {
		$_GET     = array( 'tab' => 'planes' );
		$_REQUEST = $_GET;

		$metodo = new \ReflectionMethod( Admin_Settings::class, 'get_active_tab' );
		$tab    = $metodo->invoke( $this->ajustes );

		$this->assertSame( 'planes', $tab );

		$_GET     = array();
		$_REQUEST = array();
	}
}
