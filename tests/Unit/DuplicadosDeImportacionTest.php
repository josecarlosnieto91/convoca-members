<?php
/**
 * La detección de duplicados de la importación de socios.
 *
 * `find_existing_member()` decide si una fila del CSV es un socio que ya existe. De esta función depende que importar
 * dos veces **no duplique fichas**, y ya falló una vez: miraba antes el usuario de WordPress y, como casi todos los
 * socios tienen usuario con su correo, no veía a ninguno — importar un socio existente creaba una ficha duplicada y
 * el resumen decía «1 importados, 0 errores» (members#26).
 *
 * Se siembran fichas de verdad en el almacén y se deja que `get_posts()` del arnés (que filtra por tipo, estado y
 * meta como el core) haga la búsqueda: si la prueba montara la respuesta a mano, mediría mi idea del contrato y no
 * el contrato.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Import_CSV;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-import-csv.php';

/**
 * Detección de duplicados.
 */
final class DuplicadosDeImportacionTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Instancia bajo prueba.
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
			'posts' => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'  => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'users' => $GLOBALS['_test_users'] ?? array(),
		);

		$GLOBALS['_wp_stores']['posts']     = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['_test_users']             = array();

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
		$GLOBALS['_test_users']             = $this->previo['users'];
	}

	/**
	 * Siembra una ficha de socio.
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
			'post_title'  => 'Socia ' . $id,
		);

		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array( '_convoca_email' => $email );
		if ( '' !== $dni ) {
			$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_dni'] = $dni;
		}
	}

	/**
	 * Llama a la función privada.
	 *
	 * @param string $email Correo.
	 * @param string $dni   DNI.
	 * @return int|null
	 */
	private function buscar( string $email, string $dni ): ?int {
		// Sin `setAccessible()`: desde PHP 8.1 no hace nada y desde la 8.5 está deprecado. `invoke()` sobre un
		// método privado funciona igual.
		$m = new \ReflectionMethod( Admin_Import_CSV::class, 'find_existing_member' );

		return $m->invoke( $this->importador, $email, $dni );
	}

	/**
	 * El CORREO es el primer criterio: una ficha con ese correo ya existe.
	 *
	 * Es el caso que falló en members#26. Aquí además hay usuario de WordPress con ese correo, que es lo que antes
	 * hacía que la función contestara «no es duplicado».
	 */
	public function test_una_ficha_con_ese_correo_es_un_duplicado(): void {
		$this->ficha( 77, 'socia@example.test', '00000000T' );

		$u              = new \stdClass();
		$u->ID          = 900;
		$u->user_email  = 'socia@example.test';
		$u->roles       = array( 'suscriptor' );
		$GLOBALS['_test_users'][900] = $u;

		$this->assertSame( 77, $this->buscar( 'socia@example.test', '00000000T' ), 'la ficha manda' );
	}

	/**
	 * El correo se compara **sin distinguir mayúsculas**, como MySQL.
	 *
	 * Un CSV exportado de una hoja de cálculo trae «Socia@Example.Test» y tiene que encontrar a su socio: si no, la
	 * importación duplica media lista.
	 */
	public function test_el_correo_se_compara_sin_mayusculas(): void {
		$this->ficha( 77, 'socia@example.test' );

		$this->assertSame( 77, $this->buscar( 'SOCIA@EXAMPLE.TEST', '' ) );
	}

	/**
	 * Si el correo no está, se busca por DNI.
	 */
	public function test_si_no_hay_por_correo_se_busca_por_dni(): void {
		$this->ficha( 88, 'otra@example.test', '11111111H' );

		$this->assertSame( 88, $this->buscar( 'nueva@example.test', '11111111H' ) );
	}

	/**
	 * **Un DNI vacío no se consulta.**
	 *
	 * Si se buscara por DNI vacío, la primera ficha con el DNI guardado como cadena vacía se tomaría por duplicado de
	 * todas las demás filas sin DNI y la importación se comería media lista.
	 *
	 * La ficha de prueba tiene la meta `_convoca_dni` **puesta y vacía**, que es lo que deja una importación anterior
	 * sin DNI: con la meta ausente la búsqueda por vacío no casaría y la prueba no mediría el defecto.
	 */
	public function test_un_dni_vacio_no_hace_duplicado(): void {
		$this->ficha( 99, 'otra@example.test', '' );
		$GLOBALS['_wp_stores']['post_meta'][99]['_convoca_dni'] = '';

		$this->assertNull( $this->buscar( 'nueva@example.test', '' ), 'sin DNI no hay duplicado por DNI' );
	}

	/**
	 * Sin ficha con ese correo ni ese DNI no hay duplicado.
	 */
	public function test_sin_coincidencias_no_hay_duplicado(): void {
		$this->ficha( 77, 'socia@example.test', '00000000T' );

		$this->assertNull( $this->buscar( 'nueva@example.test', '22222222J' ) );
	}

	/**
	 * Una ficha en **borrador** no cuenta como duplicado: solo se miran las publicadas.
	 */
	public function test_un_borrador_no_es_un_duplicado(): void {
		$GLOBALS['_wp_stores']['posts'][50] = (object) array(
			'ID'          => 50,
			'post_type'   => 'miembro',
			'post_status' => 'draft',
		);
		$GLOBALS['_wp_stores']['post_meta'][50] = array( '_convoca_email' => 'borrador@example.test' );

		$this->assertNull( $this->buscar( 'borrador@example.test', '' ), 'un borrador no es un socio existente' );
	}

	/**
	 * Otro tipo de contenido con la misma meta NO es un duplicado: solo cuentan las fichas `miembro`.
	 */
	public function test_otro_tipo_de_contenido_no_es_duplicado(): void {
		$GLOBALS['_wp_stores']['posts'][60] = (object) array(
			'ID'          => 60,
			'post_type'   => 'proyecto',
			'post_status' => 'publish',
		);
		$GLOBALS['_wp_stores']['post_meta'][60] = array( '_convoca_email' => 'proyecto@example.test' );

		$this->assertNull( $this->buscar( 'proyecto@example.test', '' ) );
	}
}
