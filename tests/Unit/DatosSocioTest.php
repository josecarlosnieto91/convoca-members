<?php
/**
 * El puente que evita pedir otra vez los datos al socio (members → courses).
 *
 * Solo contesta con una ficha **activa**. Una ficha de baja o pendiente de pago no matricula a
 * nadie: el formulario de `courses` vuelve a pedir los datos.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Datos_Socio;
use PHPUnit\Framework\TestCase;

/**
 * Datos del socio para la matrícula.
 */
final class DatosSocioTest extends TestCase {

	/**
	 * Escenario limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_test_users']             = array();
		unset( $GLOBALS['_wp_posts_prueba'] );
	}

	/**
	 * Una ficha activa devuelve sus datos.
	 */
	public function test_ficha_activa_devuelve_sus_datos(): void {
		$this->sembrar( 77, 'activo' );

		$datos = Datos_Socio::datos_de_ficha( 77 );

		$this->assertSame( 'socia@example.com', $datos['email'] );
		$this->assertSame( 'Socia Activa', $datos['nombre'] );
		$this->assertSame( '12345678Z', $datos['dni'] );
	}

	/**
	 * Una ficha pendiente de pago no devuelve nada.
	 */
	public function test_ficha_no_activa_no_devuelve_nada(): void {
		$this->sembrar( 77, 'pendiente_pago' );

		$this->assertSame( array(), Datos_Socio::datos_de_ficha( 77 ) );
	}

	/**
	 * Una ficha de baja tampoco.
	 */
	public function test_ficha_de_baja_no_devuelve_nada(): void {
		$this->sembrar( 77, 'baja' );

		$this->assertSame( array(), Datos_Socio::datos_de_ficha( 77 ) );
	}

	/**
	 * Una ficha sin correo no sirve como identidad.
	 */
	public function test_ficha_sin_correo_no_devuelve_nada(): void {
		$this->sembrar( 77, 'activo' );
		$GLOBALS['_wp_stores']['post_meta'][77]['_convoca_email'] = '';

		$this->assertSame( array(), Datos_Socio::datos_de_ficha( 77 ) );
	}

	/**
	 * Un identificador vacío no consulta nada.
	 */
	public function test_ficha_cero_no_devuelve_nada(): void {
		$this->assertSame( array(), Datos_Socio::datos_de_ficha( 0 ) );
	}

	/**
	 * Si la ficha no tiene nombre propio, se usa el título.
	 */
	public function test_sin_meta_de_nombre_usa_el_titulo(): void {
		$this->sembrar( 77, 'activo' );
		unset( $GLOBALS['_wp_stores']['post_meta'][77]['_convoca_nombre'] );

		$datos = Datos_Socio::datos_de_ficha( 77 );

		$this->assertNotSame( '', $datos['nombre'] );
	}

	/**
	 * Si el vínculo está escrito, no se molesta en buscar por correo.
	 */
	public function test_con_vinculo_no_busca_por_correo(): void {
		$consultas = array();

		$buscar = static function ( $meta, $valor ) use ( &$consultas ) {
			$consultas[] = $meta;
			return '_convoca_user_id' === $meta ? 55 : 0;
		};

		$this->assertSame( 55, Datos_Socio::ficha_de_usuario( 5, $buscar ) );
		$this->assertSame( array( '_convoca_user_id' ), $consultas );
	}

	/**
	 * Si falta el vínculo, se encuentra la ficha por el correo del usuario.
	 */
	public function test_sin_vinculo_usa_el_correo(): void {
		$GLOBALS['_test_users'][5] = (object) array(
			'ID'         => 5,
			'user_email' => 'socia@example.com',
			'roles'      => array( 'subscriber' ),
		);

		$consultas = array();

		$buscar = static function ( $meta, $valor ) use ( &$consultas ) {
			$consultas[] = $meta . '=' . $valor;
			return ( '_convoca_email' === $meta && 'socia@example.com' === $valor ) ? 88 : 0;
		};

		$this->assertSame( 88, Datos_Socio::ficha_de_usuario( 5, $buscar ) );
		$this->assertSame(
			array( '_convoca_user_id=5', '_convoca_email=socia@example.com' ),
			$consultas
		);
	}

	/**
	 * Si el correo del usuario no está en ninguna ficha, no hay socio.
	 */
	public function test_correo_sin_ficha_no_devuelve_socio(): void {
		$GLOBALS['_test_users'][5] = (object) array(
			'ID'         => 5,
			'user_email' => 'nadie@example.com',
			'roles'      => array( 'subscriber' ),
		);

		$buscar = static function () {
			return 0;
		};

		$this->assertSame( 0, Datos_Socio::ficha_de_usuario( 5, $buscar ) );
	}

	/**
	 * Sin usuario de WordPress no se consulta nada.
	 */
	public function test_usuario_inexistente_no_consulta(): void {
		$consultas = array();

		$buscar = static function ( $meta ) use ( &$consultas ) {
			$consultas[] = $meta;
			return 0;
		};

		$this->assertSame( 0, Datos_Socio::ficha_de_usuario( 5, $buscar ) );
		// Se mira el vínculo (que es barato), pero sin usuario no se busca por correo.
		$this->assertSame( array( '_convoca_user_id' ), $consultas );
	}

	/**
	 * Siembra una ficha con sus metadatos.
	 *
	 * @param int    $ficha  ID.
	 * @param string $estado Estado del socio.
	 * @return void
	 */
	private function sembrar( int $ficha, string $estado ): void {
		$GLOBALS['_wp_stores']['post_meta'][ $ficha ] = array(
			'_convoca_estado_miembro' => $estado,
			'_convoca_email'          => 'socia@example.com',
			'_convoca_nombre'         => 'Socia Activa',
			'_convoca_dni'            => '12345678Z',
			'_convoca_direccion'      => 'Calle Prueba 1',
			'_convoca_municipio'      => 'Oviedo',
		);
	}
}
