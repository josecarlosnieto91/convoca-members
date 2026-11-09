<?php
/**
 * El DNI se descifra por una sola puerta, con permiso y auditoría.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Datos_Socio;
use PHPUnit\Framework\TestCase;

/**
 * Helper de DNI.
 */
final class DatosSocioDniTest extends TestCase {

	/**
	 * Estado previo de los stores.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Filtro de llave de la prueba.
	 *
	 * @var callable|null
	 */
	private $filtro_clave = null;

	/**
	 * Prepara stores, llave y clase.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'post_meta'  => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'user_meta'  => $GLOBALS['_wp_stores']['user_meta'] ?? array(),
			'caps'       => $GLOBALS['_test_caps'] ?? array(),
			'user_id'    => $GLOBALS['_test_user_id'] ?? 1,
		);

		require_once dirname( __DIR__, 3 ) . '/convoca-core/includes/Cifrado.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Datos_Socio.php';

		$this->filtro_clave = static fn(): string => base64_encode( str_repeat( 'k', 32 ) );

		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_stores']['user_meta'] = array();
		$GLOBALS['_test_caps']              = array( 'gestionar_miembros' => true );
		$GLOBALS['_test_user_id']           = 7;
		$GLOBALS['_dni_auditados']          = 0;

		remove_all_filters( 'convoca_cifrado_clave' );
		add_filter( 'convoca_cifrado_clave', $this->filtro_clave );
	}

	/**
	 * Limpia y devuelve el estado que había.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['post_meta'];
		$GLOBALS['_wp_stores']['user_meta'] = $this->previo['user_meta'];
		$GLOBALS['_test_caps']              = $this->previo['caps'];
		$GLOBALS['_test_user_id']           = $this->previo['user_id'];

		if ( $this->filtro_clave ) {
			remove_filter( 'convoca_cifrado_clave', $this->filtro_clave );
		}

		unset( $GLOBALS['_dni_auditados'] );
	}

	/**
	 * Lee un DNI en claro.
	 */
	public function test_lee_dni_en_claro_con_permiso(): void {
		$GLOBALS['_wp_stores']['post_meta'][80] = array( '_convoca_dni' => '12345678Z' );

		$this->assertSame( '12345678Z', Datos_Socio::dni( 80, 'seguro' ) );
	}

	/**
	 * Sin capability no hay DNI.
	 */
	public function test_sin_capability_no_devuelve_dni(): void {
		$GLOBALS['_wp_stores']['post_meta'][80] = array( '_convoca_dni' => '12345678Z' );
		$GLOBALS['_test_caps']['gestionar_miembros'] = false;

		$this->assertSame( '', Datos_Socio::dni( 80, 'seguro' ) );
	}

	/**
	 * Descifra un valor cifrado.
	 */
	public function test_descifra_el_dni(): void {
		$GLOBALS['_wp_stores']['post_meta'][81] = array(
			'_convoca_dni' => \Convoca\Core\Cifrado::cifrar( 'X1234567L' ),
		);

		$this->assertSame( 'X1234567L', Datos_Socio::dni( 81, 'accidente' ) );
	}

	/**
	 * Si la ficha no tiene DNI, cae al usuario vinculado.
	 */
	public function test_cae_al_usuario_vinculado(): void {
		$GLOBALS['_wp_stores']['post_meta'][82] = array( '_convoca_user_id' => 55 );
		$GLOBALS['_wp_stores']['user_meta'][55] = array( '_convoca_dni' => '12345678Z' );

		$this->assertSame( '12345678Z', Datos_Socio::dni( 82, 'pdf' ) );
	}

	/**
	 * El socio puede leer su propio DNI; otro usuario sin permiso, no.
	 */
	public function test_dni_de_usuario(): void {
		$GLOBALS['_wp_stores']['user_meta'][7] = array( '_convoca_dni' => '12345678Z' );
		$GLOBALS['_wp_stores']['user_meta'][8] = array( '_convoca_dni' => 'X1234567L' );

		$this->assertSame( '12345678Z', Datos_Socio::dni_de_usuario( 7 ) );

		$GLOBALS['_test_caps']['gestionar_miembros'] = false;
		$this->assertSame( '', Datos_Socio::dni_de_usuario( 8 ) );
	}

	/**
	 * Cada descifrado deja un aviso auditable.
	 */
	public function test_dispara_la_auditoria(): void {
		$GLOBALS['_wp_stores']['post_meta'][83] = array( '_convoca_dni' => '12345678Z' );

		add_action(
			'convoca_members_dni_descifrado',
			static function () {
				++$GLOBALS['_dni_auditados'];
			}
		);

		Datos_Socio::dni( 83, 'export' );

		$this->assertSame( 1, $GLOBALS['_dni_auditados'] );
	}

	/**
	 * Los datos para el formulario de cursos también llevan el DNI descifrado.
	 */
	public function test_datos_de_ficha_para_cursos(): void {
		$GLOBALS['_wp_stores']['post_meta'][84] = array(
			'_convoca_estado_miembro' => 'activo',
			'_convoca_email'          => 'socia@example.test',
			'_convoca_nombre'         => 'Socia',
			'_convoca_dni'            => \Convoca\Core\Cifrado::cifrar( '12345678Z' ),
		);

		$datos = Datos_Socio::datos_de_ficha( 84 );

		$this->assertSame( '12345678Z', $datos['dni'] );
	}

	/**
	 * Guardar cifra el DNI, deja índice y la lectura lo devuelve en claro.
	 */
	public function test_guardar_meta_cifra_el_dni_y_deja_indice(): void {
		Datos_Socio::guardar_meta( 85, 'dni', '12345678Z' );

		$guardado = (string) $GLOBALS['_wp_stores']['post_meta'][85]['_convoca_dni'];

		$this->assertStringStartsWith( 'enc:v1:', $guardado );
		$this->assertNotSame( '12345678Z', $guardado );
		$this->assertSame( '12345678Z', Datos_Socio::leer_meta( 85, 'dni' ) );
		$this->assertSame(
			Datos_Socio::indice( '12345678Z' ),
			$GLOBALS['_wp_stores']['post_meta'][85]['_convoca_dni_idx']
		);
	}

	/**
	 * La dirección también se cifra; el municipio no.
	 */
	public function test_guardar_meta_cifra_la_direccion(): void {
		Datos_Socio::guardar_meta( 86, 'direccion', 'Calle Mayor 1' );
		Datos_Socio::guardar_meta( 86, 'municipio', 'Oviedo' );

		$this->assertStringStartsWith(
			'enc:v1:',
			(string) $GLOBALS['_wp_stores']['post_meta'][86]['_convoca_direccion']
		);
		$this->assertSame( 'Oviedo', $GLOBALS['_wp_stores']['post_meta'][86]['_convoca_municipio'] );
		$this->assertSame( 'Calle Mayor 1', Datos_Socio::leer_meta( 86, 'direccion' ) );
	}

	/**
	 * El DNI del usuario se cifra y su lectura autorizada lo descifra.
	 */
	public function test_guardar_dni_usuario_cifra_y_lee(): void {
		Datos_Socio::guardar_dni_usuario( 9, '12345678Z' );

		$this->assertStringStartsWith(
			'enc:v1:',
			(string) $GLOBALS['_wp_stores']['user_meta'][9]['_convoca_dni']
		);
		$this->assertSame( '12345678Z', Datos_Socio::leer_meta_usuario( 9, 'dni' ) );
	}
}
