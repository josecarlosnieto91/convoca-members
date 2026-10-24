<?php
/**
 * La importación de socios por CSV: deduplicación, validación y alta.
 *
 * Es la puerta por la que entran fichas de personas en masa, así que lo que se prueba es lo que puede salir caro:
 * que una fila sin nombre o sin correo no cree nada, que un DNI mal formado no pase, que **un socio que ya existe no
 * se duplique** (por correo o por DNI), que al actualizar no se le asigne un número de socio nuevo, y que el correo de
 * bienvenida solo salga si se pidió.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Import_CSV;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-import-csv.php';

/**
 * Importación de socios.
 */
final class ImportCsvTest extends TestCase {

	/**
	 * Importador.
	 *
	 * @var Admin_Import_CSV
	 */
	private Admin_Import_CSV $importador;

	/**
	 * Almacenes previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'     => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'post_meta' => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'options'   => $GLOBALS['_wp_stores']['options'] ?? array(),
			'hooks'     => $GLOBALS['_wp_hooks'] ?? array(),
		);

		$GLOBALS['_wp_stores']['posts']      = array();
		$GLOBALS['_wp_stores']['post_meta']  = array();
		$GLOBALS['_wp_stores']['post_meta']  = array();
		$GLOBALS['_wp_stub_next_id']         = 500;
		$GLOBALS['_wp_insert_args']          = array();
		$GLOBALS['_wp_insert_falla']         = false;
		$this->importador                    = new Admin_Import_CSV();
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['post_meta'];
		$GLOBALS['_wp_stores']['options']   = $this->previo['options'];
		$GLOBALS['_wp_hooks']               = $this->previo['hooks'];
		unset( $GLOBALS['_wp_stub_next_id'], $GLOBALS['_wp_insert_args'], $GLOBALS['_wp_insert_falla'] );
	}

	/**
	 * Siembra una ficha de socio con su meta.
	 *
	 * @param int   $id   Identificador.
	 * @param array $meta Meta.
	 * @return void
	 */
	private function sembrar( int $id, array $meta ): void {
		$post              = new \WP_Post();
		$post->ID          = $id;
		$post->post_type   = 'miembro';
		$post->post_status = 'publish';
		$post->post_title  = $meta['_convoca_email'] ?? 'Socio';

		$GLOBALS['_wp_stores']['posts'][ $id ]     = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = $meta;
	}

	/**
	 * Importa una fila y devuelve `[resultado, errores]`.
	 *
	 * @param array $fila     Fila.
	 * @param bool  $bienvenida Enviar bienvenida.
	 * @param bool  $actualizar Actualizar si existe.
	 * @return array{0: mixed, 1: array<int, string>}
	 */
	private function importar( array $fila, bool $bienvenida = false, bool $actualizar = false ): array {
		$errores = array();
		$metodo  = new \ReflectionMethod( Admin_Import_CSV::class, 'import_single_row' );
		$args    = array( $fila, &$errores, 3, $bienvenida, $actualizar );

		return array( $metodo->invokeArgs( $this->importador, $args ), $errores );
	}

	/**
	 * La ficha existente se busca primero por correo y luego por DNI.
	 */
	public function test_encuentra_la_ficha_existente(): void {
		$this->sembrar( 77, array( '_convoca_email' => 'ada@example.test', '_convoca_dni' => '00000000T' ) );
		$metodo = new \ReflectionMethod( Admin_Import_CSV::class, 'find_existing_member' );

		$this->assertSame( 77, $metodo->invoke( $this->importador, 'ada@example.test', '' ) );
		$this->assertSame( 77, $metodo->invoke( $this->importador, 'otra@example.test', '00000000T' ) );
		$this->assertNull( $metodo->invoke( $this->importador, 'nadie@example.test', '' ) );
	}

	/**
	 * Una ficha en borrador no cuenta como existente: solo las publicadas.
	 */
	public function test_la_ficha_en_borrador_no_cuenta(): void {
		$this->sembrar( 78, array( '_convoca_email' => 'borrador@example.test' ) );
		$GLOBALS['_wp_stores']['posts'][78]->post_status = 'draft';

		$metodo = new \ReflectionMethod( Admin_Import_CSV::class, 'find_existing_member' );

		$this->assertNull( $metodo->invoke( $this->importador, 'borrador@example.test', '' ) );
	}

	/**
	 * Sin nombre o sin correo no hay ficha.
	 */
	public function test_faltan_los_obligatorios(): void {
		list( $resultado, $errores ) = $this->importar( array( 'nombre' => 'Ada', 'email' => '' ) );

		$this->assertFalse( $resultado );
		$this->assertStringContainsString( 'nombre y email obligatorios', $errores[0] );
		$this->assertArrayNotHasKey( 'posts', array() );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'], 'no se crea nada' );
	}

	/**
	 * Un correo mal formado no entra.
	 */
	public function test_correo_invalido(): void {
		list( $resultado, $errores ) = $this->importar( array( 'nombre' => 'Ada', 'email' => 'ada-arroba-nada' ) );

		$this->assertFalse( $resultado );
		$this->assertStringContainsString( 'email inválido', $errores[0] );
		$this->assertStringContainsString( 'ada-arroba-nada', $errores[0] );
	}

	/**
	 * Un DNI con la letra mal no entra.
	 */
	public function test_dni_invalido(): void {
		list( $resultado, $errores ) = $this->importar(
			array(
				'nombre' => 'Ada',
				'email'  => 'ada@example.test',
				'dni'    => '12345678A',
			)
		);

		$this->assertFalse( $resultado );
		$this->assertStringContainsString( 'DNI inválido', $errores[0] );
	}

	/**
	 * Un socio que ya existe **no se duplica**: se avisa y no se toca nada.
	 */
	public function test_un_socio_existente_no_se_duplica(): void {
		$this->sembrar( 77, array( '_convoca_email' => 'ada@example.test', '_convoca_dni' => '00000000T' ) );

		list( $resultado, $errores ) = $this->importar(
			array(
				'nombre' => 'Ada Lovelace',
				'email'  => 'ada@example.test',
				'dni'    => '00000000T',
			)
		);

		$this->assertFalse( $resultado );
		$this->assertStringContainsString( 'socio ya existe', $errores[0] );
		$this->assertStringContainsString( '00000000T', $errores[0], 'el aviso dice por qué DNI' );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'], 'sigue habiendo una sola ficha' );
		$this->assertSame( 'ada@example.test', $GLOBALS['_wp_stores']['posts'][77]->post_title, 'no se pisa la ficha existente' );
	}

	/**
	 * Al actualizar, se cambia el título pero **no** se genera número ni código nuevos.
	 */
	public function test_actualizar_no_asigna_numero_nuevo(): void {
		$this->sembrar(
			77,
			array(
				'_convoca_email'       => 'ada@example.test',
				'_convoca_numero_socio' => 'S-0007',
				'_convoca_access_code'  => 'CODIGO-VIEJO',
			)
		);

		list( $resultado ) = $this->importar(
			array(
				'nombre'   => 'Ada Lovelace',
				'email'    => 'ada@example.test',
				'telefono' => '600000000',
			),
			false,
			true
		);

		$this->assertSame( 77, $resultado, 'se actualiza la ficha que ya había' );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'] );
		$this->assertSame( 'Ada Lovelace', $GLOBALS['_wp_stores']['posts'][77]->post_title );
		$this->assertSame( 'S-0007', $GLOBALS['_wp_stores']['post_meta'][77]['_convoca_numero_socio'], 'el número no cambia' );
		$this->assertSame( 'CODIGO-VIEJO', $GLOBALS['_wp_stores']['post_meta'][77]['_convoca_access_code'], 'el código no cambia' );
		$this->assertSame( '600000000', $GLOBALS['_wp_stores']['post_meta'][77]['_convoca_telefono'] );
	}

	/**
	 * Alta nueva: estado activo, código de acceso y meta de la fila.
	 */
	public function test_alta_nueva(): void {
		list( $resultado, $errores ) = $this->importar(
			array(
				'nombre'    => 'Grace Hopper',
				'email'     => 'grace@example.test',
				'dni'       => '00000000T',
				'apellidos' => 'Hopper',
				'plan'      => 'numerario',
			)
		);

		$this->assertIsInt( $resultado );
		$this->assertSame( array(), $errores );
		$meta = $GLOBALS['_wp_stores']['post_meta'][ $resultado ];
		$this->assertSame( 'grace@example.test', $meta['_convoca_email'] );
		$this->assertSame( 'activo', $meta['_convoca_estado_miembro'] );
		$this->assertArrayHasKey( '_convoca_access_code', $meta );
		$this->assertArrayHasKey( '_convoca_numero_socio', $meta );
	}

	/**
	 * Un campo vacío de la fila no pisa lo que ya hubiera.
	 */
	public function test_la_meta_vacia_no_se_escribe(): void {
		list( $id ) = $this->importar(
			array(
				'nombre'   => 'Grace Hopper',
				'email'    => 'grace@example.test',
				'telefono' => '',
			)
		);

		$this->assertArrayNotHasKey( '_convoca_telefono', $GLOBALS['_wp_stores']['post_meta'][ $id ], 'vacío no borra' );
	}

	/**
	 * El aviso de bienvenida solo sale si se pidió.
	 */
	public function test_la_bienvenida_es_opcional(): void {
		$GLOBALS['convoca_bienvenidas'] = 0;
		add_action(
			'convoca_member_created',
			static function () {
				++$GLOBALS['convoca_bienvenidas'];
			}
		);

		$this->importar( array( 'nombre' => 'Sin aviso', 'email' => 'sin@example.test' ) );
		$this->assertSame( 0, $GLOBALS['convoca_bienvenidas'], 'sin pedirla no sale' );

		$this->importar( array( 'nombre' => 'Con aviso', 'email' => 'con@example.test' ), true );
		$this->assertSame( 1, $GLOBALS['convoca_bienvenidas'], 'pedida, sale una vez' );

		unset( $GLOBALS['convoca_bienvenidas'] );
	}
}
