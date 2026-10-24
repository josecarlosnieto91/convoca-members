<?php
/**
 * Los ajustes de la asociación: saneado, guardas y desbloqueo de intentos.
 *
 * Aquí se juega dinero y acceso: lo que entra por el formulario de ajustes acaba en precios, en los días de gracia
 * antes de perder beneficios, en los reintentos de cobro automático y en los niveles de gamificación. Por eso lo que
 * se prueba no es que la pantalla pinte bonito, sino que **nada entre sin sanear** y que las guardas corten.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Settings;
use Convoca\Members\CPT_Miembro;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-settings.php';

/**
 * Ajustes.
 */
final class AdminSettingsTest extends TestCase {

	/**
	 * Ajustes.
	 *
	 * @var Admin_Settings
	 */
	private Admin_Settings $ajustes;

	/**
	 * Opciones previas, para no dejar el almacén sucio.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo              = $GLOBALS['_wp_stores']['options'] ?? array();
		$GLOBALS['_wp_stores']['options'] = array();
		$GLOBALS['convoca_test_settings'] = array();
		$GLOBALS['_test_caps'] = array( 'manage_options' => true );
		$GLOBALS['convoca_test_referer']   = 'https://panel.example.test/wp-admin/admin.php?page=conv-members-settings';
		$_POST                             = array();
		$_GET                              = array();
		$_REQUEST                          = array();
		$this->ajustes                     = new Admin_Settings();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['options'] = $this->previo;
		unset( $GLOBALS['convoca_test_settings'], $GLOBALS['_test_caps'], $GLOBALS['convoca_test_referer'] );
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
	}

	/**
	 * Los tres enganches: guardado, registro y desbloqueo.
	 */
	public function test_engancha_lo_suyo(): void {
		$GLOBALS['_wp_hooks'] = array();
		new Admin_Settings();

		$this->assertArrayHasKey( 'admin_post_convoca_desbloquear_intentos', $GLOBALS['_wp_hooks'] );
	}

	/**
	 * Los cinco ajustes se registran con su saneador.
	 */
	public function test_registra_los_ajustes(): void {
		$this->ajustes->register_settings();

		$this->assertArrayHasKey( 'convoca_members_settings', $GLOBALS['convoca_test_settings'] );
		$this->assertArrayHasKey( 'convoca_members_plans', $GLOBALS['convoca_test_settings'] );
		$this->assertArrayHasKey( 'convoca_volunteer_fields', $GLOBALS['convoca_test_settings'] );
		$this->assertArrayHasKey( 'convoca_volunteer_legal_text', $GLOBALS['convoca_test_settings'] );
		$this->assertArrayHasKey( 'convoca_gamification_tracks', $GLOBALS['convoca_test_settings'] );

		$this->assertSame(
			array( $this->ajustes, 'sanitize_plans' ),
			$GLOBALS['convoca_test_settings']['convoca_members_plans']['args']['sanitize_callback']
		);
	}

	/**
	 * Lo que no es un array no entra: se devuelve vacío y se tira la caché.
	 */
	public function test_lo_que_no_es_array_se_descarta(): void {
		$clave = ( new \ReflectionClassConstant( Admin_Settings::class, 'CACHE_KEY' ) )->getValue();
		$GLOBALS['_wp_stores']['options'][ $clave ] = 'vieja';

		$this->assertSame( array(), $this->ajustes->sanitize_settings( 'basura' ) );
		$this->assertArrayNotHasKey( $clave, $GLOBALS['_wp_stores']['options'], 'la caché se invalida' );
	}

	/**
	 * Los ajustes generales, con los cuatro rangos recortados.
	 */
	public function test_sanea_los_ajustes_generales(): void {
		$salida = $this->ajustes->sanitize_settings(
			array(
				'admin_email'             => '  Admin@Example.TEST  ',
				'copy_all_emails'         => '',
				'iban'                    => 'ES00 0000',
				'entity_name'             => 'Asociación de ejemplo',
				'voluntariado_email'      => 'voluntariado@example.test',
				'sender_name'             => '',
				'email_provider'          => 'MailGun',
				'grace_suspend_days'      => '-5',
				'grace_baja_days'         => '999',
				'auto_renew_max_attempts' => '99',
				'auto_renew_retry_days'   => '0',
			)
		);

		$this->assertSame( 'Admin@Example.TEST', $salida['admin_email'], 'sin espacios alrededor' );
		$this->assertSame( 0, $salida['copy_all_emails'] );
		$this->assertSame( 0, $salida['grace_suspend_days'], 'no hay días de gracia negativos' );
		$this->assertSame( 90, $salida['grace_baja_days'], 'el máximo son 90 días' );
		$this->assertSame( 10, $salida['auto_renew_max_attempts'], 'el máximo son 10 intentos' );
		$this->assertSame( 1, $salida['auto_renew_retry_days'], 'el mínimo es 1 día' );
		$this->assertSame( 'mailgun', $salida['email_provider'], 'el proveedor se normaliza' );
		$this->assertSame( 'voluntariado@example.test', $salida['voluntariado_email'] );
	}

	/**
	 * Los valores por defecto de los rangos cuando no llegan.
	 */
	public function test_los_rangos_traen_su_valor_por_defecto(): void {
		$salida = $this->ajustes->sanitize_settings( array() );

		$this->assertSame( 1, $salida['grace_suspend_days'] );
		$this->assertSame( 30, $salida['grace_baja_days'] );
		$this->assertSame( 3, $salida['auto_renew_max_attempts'] );
		$this->assertSame( 5, $salida['auto_renew_retry_days'] );
	}

	/**
	 * Los planes: una clave vacía no crea un plan y las ventajas van por líneas.
	 */
	public function test_sanea_los_planes(): void {
		$salida = $this->ajustes->sanitize_plans(
			array(
				''          => array( 'label' => 'Sin clave' ),
				'Numerario' => array(
					'label'           => 'Numerario',
					'price'           => '25,50',
					'hours'           => '10',
					'payment_methods' => array( 'bizum', 'tarjeta' ),
					'advantages_raw'  => "Acceso al local\n\nClases\n",
					'active'          => '1',
					'order'           => '20',
				),
				'Juvenil'   => array(
					'label' => 'Juvenil',
					'price' => '12',
					'order' => '5',
				),
			)
		);

		$this->assertArrayNotHasKey( '', $salida, 'sin clave no hay plan' );
		$this->assertSame( array( 'juvenil', 'numerario' ), array_keys( $salida ), 'claves en minúscula y ordenadas por `order`' );
		// Comportamiento actual, anotado en members#67: `array_filter` sin `array_values` deja los huecos, así que
		// la lista sale con claves 0 y 2. Al serializar a JSON eso deja de ser un array y pasa a ser un objeto.
		$this->assertSame( array( 0 => 'Acceso al local', 2 => 'Clases' ), $salida['numerario']['advantages'], 'las líneas vacías se caen, pero los huecos se quedan' );
		// `(float) '25,50'` da 25.0: la coma decimal se pierde al convertir. Anotado en members#67.
		$this->assertSame( 25.0, $salida['numerario']['price'], 'la coma decimal se descarta (defecto anotado)' );
		$this->assertTrue( $salida['numerario']['active'] );
		$this->assertFalse( $salida['juvenil']['active'], 'sin `active` no está activo' );
	}

	/**
	 * Los campos de voluntariado: sin nombre no hay campo.
	 */
	public function test_sanea_los_campos_de_voluntariado(): void {
		$salida = $this->ajustes->sanitize_volunteer_fields(
			array(
				array( 'name' => '', 'label' => 'Sin nombre' ),
				array( 'name' => 'Dieta', 'label' => 'Dieta', 'type' => 'select', 'options' => "Vegana\nNormal", 'required' => '1' ),
			)
		);

		$this->assertCount( 1, $salida );
		$this->assertSame( 'dieta', $salida[0]['name'] );
		$this->assertTrue( $salida[0]['required'] );
	}

	/**
	 * Los niveles de gamificación: solo para planes que existen y con color válido.
	 */
	public function test_sanea_los_niveles_de_gamificacion(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_plans'] = array(
			'numerario' => array( 'label' => 'Numerario' ),
		);

		$salida = $this->ajustes->sanitize_gamification_tracks(
			array(
				'Inventado' => array( 'label' => 'No existe' ),
				'Numerario' => array(
					'label'  => 'Numerario',
					'levels' => array(
						array( 'name' => 'Bronce', 'hours' => '10', 'color' => 'rojo' ),
						array( 'name' => 'Plata', 'hours' => '25', 'color' => '#ffaa00' ),
					),
				),
			)
		);

		$this->assertArrayNotHasKey( 'Inventado', $salida, 'no se guarda un nivel para un plan que no existe' );
		$this->assertArrayHasKey( 'numerario', $salida );
		$this->assertCount( 2, $salida['numerario']['levels'] );
		$this->assertSame( '#000000', $salida['numerario']['levels'][0]['color'], 'un color inválido cae al negro' );
		$this->assertSame( '#ffaa00', $salida['numerario']['levels'][1]['color'] );
		$this->assertSame( 25.0, $salida['numerario']['levels'][1]['hours'] );
	}

	/**
	 * Sin nonce no se guarda nada.
	 */
	public function test_guardar_sin_nonce_no_hace_nada(): void {
		$_POST = array( 'convoca_save_settings' => 'general' );

		$this->ajustes->handle_custom_save();

		$this->assertArrayNotHasKey( 'convoca_members_settings', $GLOBALS['_wp_stores']['options'] );
	}

	/**
	 * Nonce inválido, tampoco.
	 */
	public function test_guardar_con_nonce_invalido_no_hace_nada(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$_POST = array(
			'_convoca_settings_nonce' => 'malo',
			'convoca_save_settings'   => 'general',
		);

		$this->ajustes->handle_custom_save();

		$this->assertArrayNotHasKey( 'convoca_members_settings', $GLOBALS['_wp_stores']['options'] );
		unset( $GLOBALS['_wp_stores']['nonces_validos'] );
	}

	/**
	 * Con nonce válido pero sin capacidad, corta.
	 */
	public function test_guardar_sin_capacidad_muere(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$GLOBALS['_test_caps']['manage_options'] = false;
		$_POST = array(
			'_convoca_settings_nonce' => 'bueno',
			'convoca_save_settings'   => 'general',
		);

		$this->expectException( \RuntimeException::class );
		try {
			$this->ajustes->handle_custom_save();
		} finally {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		}
	}

	/**
	 * Guardado bueno: sanea, recorta los rangos, guarda el tema y vuelve al panel.
	 */
	public function test_guardar_bien(): void {
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'bueno' );
		$GLOBALS['_wp_stores']['options']['convoca_members_settings'] = array( 'iban' => 'VIEJO' );
		$_POST = array(
			'_convoca_settings_nonce'    => 'bueno',
			'convoca_save_settings'      => 'general',
			'convoca_members_settings'   => array( 'iban' => 'ES11 2222', 'grace_baja_days' => '999' ),
			'convoca_document_theme'     => 'oscuro',
		);

		try {
			$this->ajustes->handle_custom_save();
			$this->fail( 'debía redirigir' );
		} catch ( \RuntimeException $r ) {
			$this->assertStringContainsString( 'updated=1', $r->getMessage() );
		}

		$guardado = $GLOBALS['_wp_stores']['options']['convoca_members_settings'];
		$this->assertSame( 'ES11 2222', $guardado['iban'] );
		$this->assertSame( 90, $guardado['grace_baja_days'], 'el recorte también se aplica al guardar' );
		$this->assertSame( 'light', $GLOBALS['_wp_stores']['options']['convoca_document_theme'], 'un tema inventado cae a claro' );

		unset( $GLOBALS['_wp_stores']['nonces_validos'] );
	}

	/**
	 * La pestaña activa: sin `tab` es la general.
	 */
	public function test_la_pestana_activa(): void {
		$this->assertSame( 'general', $this->privado( 'get_active_tab' ) );

		$_GET['tab'] = 'Seguridad';
		$this->assertSame( 'seguridad', $this->privado( 'get_active_tab' ) );
	}

	/**
	 * El desbloqueo de intentos exige capacidad antes que nada.
	 */
	public function test_desbloquear_sin_capacidad_muere(): void {
		$GLOBALS['_test_caps']['manage_options'] = false;

		$this->expectException( \RuntimeException::class );
		$this->ajustes->desbloquear_intentos();
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo Método.
	 * @return mixed
	 */
	private function privado( string $metodo ) {
		$reflexion = new \ReflectionMethod( Admin_Settings::class, $metodo );
		return $reflexion->invoke( $this->ajustes );
	}
}
