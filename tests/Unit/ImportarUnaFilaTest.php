<?php
/**
 * Importar una fila del CSV de socios.
 *
 * `import_single_row()` es el corazón de la importación: decide si la fila entra, si se rechaza y por qué, si se
 * actualiza un socio existente o se crea uno nuevo, y si se le manda el correo de bienvenida. Un error aquí **no da
 * la cara**: el resumen dice «N importados, 0 errores» y el socio no existe, o existe dos veces.
 *
 * Lo que se mide, en el orden en que el código lo hace:
 *
 *  - los tres rechazos por datos (nombre/email, email inválido, DNI inválido);
 *  - el duplicado **sin** `update_existing` (con su mensaje, que distingue si la fila traía DNI);
 *  - el duplicado **con** `update_existing` (renombra, no crea otra ficha);
 *  - la creación, con su **código de acceso** y su **número de socio** (que solo se asignan a los nuevos);
 *  - las metas, y que una celda vacía **no pisa** lo que ya había;
 *  - el aviso de bienvenida, que solo sale si se pidió.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Import_CSV;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-import-csv.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';

/**
 * Importación de una fila.
 */
final class ImportarUnaFilaTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Importador.
	 *
	 * @var Admin_Import_CSV
	 */
	private Admin_Import_CSV $importador;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'    => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'     => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'acciones' => $GLOBALS['_wp_actions'] ?? array(),
			'next'     => $GLOBALS['_wp_stub_next_id'] ?? null,
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_wp_actions']             = array();
		$GLOBALS['_wp_stub_next_id']        = 500;

		$this->importador = ( new \ReflectionClass( Admin_Import_CSV::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']     = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta'] = $this->previo['meta'];
		$GLOBALS['_wp_actions']             = $this->previo['acciones'];
		if ( null === $this->previo['next'] ) {
			unset( $GLOBALS['_wp_stub_next_id'] );
		} else {
			$GLOBALS['_wp_stub_next_id'] = $this->previo['next'];
		}
	}

	/**
	 * Ejecuta la importación de una fila.
	 *
	 * @param array $fila             Fila.
	 * @param bool  $bienvenida       Mandar aviso de bienvenida.
	 * @param bool  $actualizar       Actualizar si existe.
	 * @param bool  $con_dni          Si el mensaje de duplicado debe llevar DNI (se controla por la fila).
	 * @return array{0: mixed, 1: array} Resultado y errores.
	 */
	private function importar( array $fila, bool $bienvenida = false, bool $actualizar = false, bool $con_dni = true ): array {
		unset( $con_dni );
		$errores = array();

		$m = new \ReflectionMethod( Admin_Import_CSV::class, 'import_single_row' );
		$resultado = $m->invokeArgs( $this->importador, array( $fila, &$errores, 7, $bienvenida, $actualizar ) );

		return array( $resultado, $errores );
	}

	/**
	 * Una ficha de socio sembrada.
	 *
	 * @param int    $id    ID.
	 * @param string $email Correo.
	 * @param string $dni   DNI.
	 * @return void
	 */
	private function ficha( int $id, string $email, string $dni = '' ): void {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => 'miembro',
			'post_status' => 'publish',
			'post_title'  => 'Antigua ' . $id,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array( '_convoca_email' => $email );
		if ( '' !== $dni ) {
			$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_dni'] = $dni;
		}
	}

	/**
	 * Sin nombre o sin correo, la fila se rechaza y se dice en qué línea.
	 */
	public function test_nombre_y_email_son_obligatorios(): void {
		list( $r, $e ) = $this->importar( array( 'nombre' => '', 'email' => 'a@example.test' ) );

		$this->assertFalse( $r, 'no se importa' );
		$this->assertStringContainsString( 'Fila 7', $e[0] ?? '', 'el error dice en qué línea' );
		$this->assertStringContainsString( 'nombre y email obligatorios', $e[0] );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'], 'no se creó nada' );
	}

	/**
	 * Un correo mal escrito se rechaza antes de tocar la base.
	 */
	public function test_un_email_invalido_se_rechaza(): void {
		list( $r, $e ) = $this->importar( array( 'nombre' => 'Ada', 'email' => 'ada-arroba-nada' ) );

		$this->assertFalse( $r );
		$this->assertStringContainsString( 'email inválido', $e[0] ?? '' );
		$this->assertStringContainsString( 'ada-arroba-nada', $e[0], 'el mensaje enseña el valor' );
	}

	/**
	 * Un DNI que no valida se rechaza; y el DNI es opcional.
	 */
	public function test_un_dni_invalido_se_rechaza(): void {
		list( $r, $e ) = $this->importar( array( 'nombre' => 'Ada', 'email' => 'ada@example.test', 'dni' => '1234' ) );

		$this->assertFalse( $r );
		$this->assertStringContainsString( 'DNI inválido', $e[0] ?? '' );
	}

	/**
	 * Un socio que ya existe **no se duplica** si no se pidió actualizar, y el mensaje lo dice.
	 */
	public function test_un_duplicado_se_rechaza_con_su_mensaje(): void {
		$this->ficha( 77, 'socia@example.test', '00000000T' );

		list( $r, $e ) = $this->importar( array( 'nombre' => 'Otra', 'email' => 'socia@example.test', 'dni' => '00000000T' ) );

		$this->assertFalse( $r, 'no se crea una segunda ficha' );
		$this->assertStringContainsString( 'ya existe', $e[0] ?? '' );
		$this->assertStringContainsString( '00000000T', $e[0], 'con DNI, el mensaje lo enseña' );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'], 'sigue habiendo una sola ficha' );
	}

	/**
	 * Con `update_existing`, el socio existente se **renombra** y no se crea otro.
	 */
	public function test_un_duplicado_con_actualizar_renueva_la_ficha(): void {
		$this->ficha( 77, 'socia@example.test' );

		list( $r, $e ) = $this->importar( array( 'nombre' => 'Nombre Nuevo', 'email' => 'socia@example.test' ), false, true );

		$this->assertSame( 77, $r, 'devuelve la ficha de siempre' );
		$this->assertSame( array(), $e, 'sin errores' );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'], 'no se duplicó' );
		$this->assertSame( 'Nombre Nuevo', $GLOBALS['_wp_stores']['posts'][77]->post_title );
	}

	/**
	 * Un socio nuevo recibe **código de acceso** y **número de socio**.
	 *
	 * Sin el número, no puede identificarse ni salir en el certificado; sin el código, no puede entrar.
	 */
	public function test_un_socio_nuevo_recibe_codigo_y_numero(): void {
		list( $r, $e ) = $this->importar( array( 'nombre' => 'Ada Lovelace', 'email' => 'ada@example.test' ) );

		$this->assertSame( array(), $e, 'sin errores' );
		$this->assertIsInt( $r );

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $r ] ?? array();
		$this->assertNotEmpty( $meta['_convoca_access_code'] ?? '', 'tiene código de acceso' );
		$this->assertArrayHasKey( '_convoca_numero_socio', $meta, 'tiene número de socio' );
		$this->assertSame( 'activo', $meta['_convoca_estado_miembro'] ?? '', 'entra como activo' );
	}

	/**
	 * Los datos de la fila se guardan como metas.
	 */
	public function test_los_datos_de_la_fila_se_guardan(): void {
		list( $r ) = $this->importar(
			array(
				'nombre'    => 'Ada Lovelace',
				'email'     => 'ada@example.test',
				'dni'       => '00000000T',
				'telefono'  => '600000000',
				'municipio' => 'Oviedo',
			)
		);

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $r ] ?? array();
		$this->assertSame( 'ada@example.test', $meta['_convoca_email'] ?? '' );
		$this->assertSame( '00000000T', $meta['_convoca_dni'] ?? '' );
		$this->assertSame( '600000000', $meta['_convoca_telefono'] ?? '' );
		$this->assertSame( 'Oviedo', $meta['_convoca_municipio'] ?? '' );
	}

	/**
	 * **Una celda vacía no pisa lo que ya había.**
	 *
	 * Importar un CSV con la columna del teléfono en blanco no puede borrar el teléfono que consta: es la
	 * diferencia entre actualizar y arrasar.
	 */
	public function test_una_celda_vacia_no_borra_el_dato_que_ya_estaba(): void {
		$this->ficha( 77, 'socia@example.test' );
		$GLOBALS['_wp_stores']['post_meta'][77]['_convoca_telefono'] = '611111111';

		$this->importar(
			array(
				'nombre'   => 'Socia',
				'email'    => 'socia@example.test',
				'telefono' => '',
			),
			false,
			true
		);

		$this->assertSame( '611111111', $GLOBALS['_wp_stores']['post_meta'][77]['_convoca_telefono'] ?? '', 'no se borró' );
	}

	/**
	 * El aviso de bienvenida solo sale si se pidió.
	 *
	 * Importar 200 socios sin querer mandarles 200 correos es una decisión que se toma en la pantalla; si el aviso
	 * saliera siempre, la opción no serviría de nada.
	 */
	public function test_el_aviso_de_bienvenida_es_opcional(): void {
		list( $r ) = $this->importar( array( 'nombre' => 'Ada', 'email' => 'ada@example.test' ), false );

		$this->assertIsInt( $r, 'la fila se importa igual' );
		$this->assertNotContains( 'convoca_member_created', $this->hooks_disparados(), 'sin pedirlo no se avisa' );
	}

	/**
	 * Y si se pide, sale con el nombre: el correo de bienvenida necesita saber a quién saluda.
	 */
	public function test_con_bienvenida_se_avisa(): void {
		list( $r ) = $this->importar( array( 'nombre' => 'Ada Lovelace', 'email' => 'ada@example.test' ), true );

		$this->assertIsInt( $r );

		$hook = $this->hook_disparado( 'convoca_member_created' );
		$this->assertNotNull( $hook, 'se disparó el aviso' );
		$this->assertSame( $r, $hook['args'][0] ?? null, 'con la ficha recién creada' );
		$this->assertSame( 'Ada Lovelace', $hook['args'][1]['nombre'] ?? null, 'y con el nombre' );
	}

	/**
	 * Ganchos disparados.
	 *
	 * @return string[]
	 */
	private function hooks_disparados(): array {
		return array_column( (array) ( $GLOBALS['_wp_actions'] ?? array() ), 'hook' );
	}

	/**
	 * Un gancho concreto, si se disparó.
	 *
	 * @param string $hook Gancho.
	 * @return array|null
	 */
	private function hook_disparado( string $hook ): ?array {
		foreach ( (array) ( $GLOBALS['_wp_actions'] ?? array() ) as $registro ) {
			if ( ( $registro['hook'] ?? '' ) === $hook ) {
				return $registro;
			}
		}
		return null;
	}
}
