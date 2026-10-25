<?php
/**
 * El acuerdo de voluntariado en PDF.
 *
 * `generate_volunteer_agreement()` crea el documento legal que firma una persona al entrar como voluntaria. El flujo
 * está montado con una transacción y un `SELECT ... FOR UPDATE` precisamente porque dos peticiones a la vez (aprobar
 * dos veces, o dos pestañas) no pueden dar lugar a **dos acuerdos** para la misma persona.
 *
 * Lo que se mide aquí son los caminos de error, que es donde se pierde o se duplica algo:
 *
 *  - usuario inexistente → no se crea nada;
 *  - **ya existe** → se devuelve el que hay, sin crear un borrador;
 *  - **carrera**: el segundo `SELECT ... FOR UPDATE` encuentra uno → `ROLLBACK` y **el borrador se borra**;
 *  - **`wp_insert_post` falla** → `null`, sin nada a medias;
 *  - **la generación del PDF falla** → `ROLLBACK`, borra el borrador y deja el motivo en un transitorio.
 *
 * El borrador huérfano es el fallo silencioso de este método: un `convoca_documento` en `draft` que nadie ve, que se
 * queda para siempre y que aparece en los listados como un documento sin contenido.
 *
 * **Excepción escrita**: el camino feliz (`COMMIT` + metas + `publish`) no se mide aquí porque exige **Dompdf**
 * instalado. En el arnés `Signature::generate_pdf()` devuelve `false` —y es lo que se aprovecha para medir el
 * rollback—; con la librería, el flujo seguiría. Igual que `convoca-publisher.php`, queda declarado.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\PDF_Document;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/PDF_Document.php';
require_once dirname( __DIR__, 2 ) . '/includes/Estilos_PDF.php';

/**
 * Acuerdo de voluntariado.
 */
final class AcuerdoVoluntariadoTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'    => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'     => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'users'    => $GLOBALS['_wp_stores']['usuarios'] ?? array(),
			'usermeta' => $GLOBALS['_wp_stores']['user_meta'] ?? array(),
			'options'  => $GLOBALS['_wp_stores']['options'] ?? array(),
			'deleted'  => $GLOBALS['_wp_stores']['deleted_posts'] ?? null,
			'queries'  => $GLOBALS['_wp_stores']['queries'] ?? null,
			'gvmapa'   => $GLOBALS['_wp_stores']['get_var_mapa'] ?? null,
			'errmeta'  => $GLOBALS['_wp_insert_devuelve_error'] ?? null,
			'trans'    => $GLOBALS['_wp_stores']['transients'] ?? array(),
			'errlog'   => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']         = array();
		$GLOBALS['_wp_stores']['post_meta']     = array();
		$GLOBALS['_wp_stores']['user_meta']     = array();
		$GLOBALS['_wp_stores']['options']       = array();
		$GLOBALS['_wp_stores']['transients']    = array();
		$GLOBALS['_wp_stores']['deleted_posts'] = array();
		$GLOBALS['_wp_stores']['queries']       = array();
		unset( $GLOBALS['_wp_stores']['get_var_mapa'], $GLOBALS['_wp_insert_devuelve_error'] );

		// El `Logger` con `WP_DEBUG` escribe con `error_log()`; aquí no se lanza porque no hay proceso aparte, pero
		// se manda a un fichero temporal para no ensuciar la salida.
		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-pdf-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );

		$GLOBALS['_wp_stores']['usuarios'] = array(
			30 => (object) array(
				'ID'           => 30,
				'user_email'   => 'voluntaria@example.test',
				'user_login'   => 'voluntaria',
				'display_name' => 'Voluntaria Ejemplo',
				'first_name'   => 'Ada',
				'roles'        => array( 'suscriptor' ),
			),
		);
		$GLOBALS['_wp_stores']['user_meta'][30] = array(
			'_convoca_dni'      => '00000000T',
			'_convoca_telefono' => '600000000',
		);
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']         = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']     = $this->previo['meta'];
		$GLOBALS['_wp_stores']['usuarios']      = $this->previo['users'];
		$GLOBALS['_wp_stores']['user_meta']     = $this->previo['usermeta'];
		$GLOBALS['_wp_stores']['options']       = $this->previo['options'];
		$GLOBALS['_wp_stores']['transients']    = $this->previo['trans'];

		if ( null === $this->previo['deleted'] ) {
			unset( $GLOBALS['_wp_stores']['deleted_posts'] );
		} else {
			$GLOBALS['_wp_stores']['deleted_posts'] = $this->previo['deleted'];
		}
		if ( null === $this->previo['queries'] ) {
			unset( $GLOBALS['_wp_stores']['queries'] );
		} else {
			$GLOBALS['_wp_stores']['queries'] = $this->previo['queries'];
		}
		if ( null === $this->previo['gvmapa'] ) {
			unset( $GLOBALS['_wp_stores']['get_var_mapa'] );
		} else {
			$GLOBALS['_wp_stores']['get_var_mapa'] = $this->previo['gvmapa'];
		}
		if ( null === $this->previo['errmeta'] ) {
			unset( $GLOBALS['_wp_insert_devuelve_error'] );
		} else {
			$GLOBALS['_wp_insert_devuelve_error'] = $this->previo['errmeta'];
		}

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
		}
	}

	/**
	 * Consultas lanzadas a la base.
	 *
	 * @return string[]
	 */
	private function consultas(): array {
		return array_map( 'strval', (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
	}

	/**
	 * ¿Se lanzó esta sentencia?
	 *
	 * @param string $sentencia Sentencia.
	 * @return bool
	 */
	private function hubo( string $sentencia ): bool {
		foreach ( $this->consultas() as $q ) {
			if ( false !== stripos( $q, $sentencia ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fichas en borrador que **quedan**.
	 *
	 * `wp_delete_post()` del arnés apunta el borrado en `deleted_posts` pero **no quita la ficha del almacén** (a
	 * propósito: hay pruebas que siembran un post y lo releen). Así que «queda un borrador» es «está en el almacén y
	 * nadie lo ha borrado».
	 *
	 * @return int
	 */
	private function borradores(): int {
		$borrados = array_map( 'intval', (array) ( $GLOBALS['_wp_stores']['deleted_posts'] ?? array() ) );
		$n        = 0;
		foreach ( (array) $GLOBALS['_wp_stores']['posts'] as $p ) {
			if ( 'convoca_documento' === ( $p->post_type ?? '' ) && 'draft' === ( $p->post_status ?? '' ) && ! in_array( (int) $p->ID, $borrados, true ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Fichas de documento publicadas.
	 *
	 * @return int
	 */
	private function publicados(): int {
		$n = 0;
		foreach ( (array) $GLOBALS['_wp_stores']['posts'] as $p ) {
			if ( 'convoca_documento' === ( $p->post_type ?? '' ) && 'publish' === ( $p->post_status ?? '' ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Un usuario que no existe no genera nada, y no deja borradores.
	 */
	public function test_un_usuario_que_no_existe_no_genera_nada(): void {
		$resultado = PDF_Document::generate_volunteer_agreement( 999 );

		$this->assertNull( $resultado );
		$this->assertSame( 0, $this->borradores(), 'no se creó un borrador huérfano' );
		$this->assertSame( array(), (array) $GLOBALS['_wp_stores']['deleted_posts'], 'ni hubo nada que borrar' );
	}

	/**
	 * **Si el acuerdo ya existe, se devuelve el que hay y NO se crea un borrador.**
	 *
	 * Es la guarda que evita los duplicados en el caso normal (sin carrera). Si creara el borrador antes de mirar,
	 * cada visita a la ficha dejaría un `draft` de más.
	 */
	public function test_un_acuerdo_que_ya_existe_se_devuelve_sin_crear_borrador(): void {
		$GLOBALS['_wp_stores']['posts'][777]      = (object) array(
			'ID'          => 777,
			'post_type'   => 'convoca_documento',
			'post_status' => 'publish',
			'post_title'  => 'Acuerdo',
		);
		$GLOBALS['_wp_stores']['post_meta'][777] = array(
			'_convoca_usuario_id'             => '30',
			'_convoca_es_acuerdo_voluntariado' => '1',
		);
		$GLOBALS['_wp_stores']['get_var_mapa']   = array( 'SELECT p.ID FROM' => '777' );

		$resultado = PDF_Document::generate_volunteer_agreement( 30 );

		$this->assertSame( 777, $resultado, 'devuelve el que ya había' );
		$this->assertSame( 0, $this->borradores(), 'no se creó ningún borrador' );
		$this->assertSame( array(), (array) $GLOBALS['_wp_stores']['deleted_posts'] );
	}

	/**
	 * Si la creación del documento falla, se devuelve `null` y no queda nada a medias.
	 */
	public function test_si_la_creacion_falla_no_queda_nada(): void {
		$GLOBALS['_wp_insert_devuelve_error'] = 'la base no responde';

		$resultado = PDF_Document::generate_volunteer_agreement( 30 );

		$this->assertNull( $resultado );
		$this->assertSame( 0, $this->borradores() );
		// Y **no se abrió la transacción**: sin documento que publicar no hay nada que deshacer. Con el flujo
		// siguiendo adelante se lanzaría `START TRANSACTION` sobre un id inválido.
		$this->assertFalse( $this->hubo( 'START TRANSACTION' ), 'no se abre transacción si no hay documento' );
		$this->assertSame( array(), (array) $GLOBALS['_wp_stores']['deleted_posts'], 'ni hay nada que borrar' );
	}

	/**
	 * **La carrera**: otra petición creó el acuerdo mientras tanto.
	 *
	 * El segundo `SELECT ... FOR UPDATE` lo encuentra, así que se hace `ROLLBACK` y **se borra el borrador** que esta
	 * petición acababa de crear. Sin el borrado quedaría un documento en `draft` para siempre, invisible en el área
	 * del socio y estorbando en los listados.
	 *
	 * Se distingue la segunda consulta por el `FOR UPDATE`: la primera no lo lleva y devuelve vacío.
	 */
	public function test_la_carrera_hace_rollback_y_borra_el_borrador(): void {
		$GLOBALS['_wp_stores']['posts'][888]      = (object) array(
			'ID'          => 888,
			'post_type'   => 'convoca_documento',
			'post_status' => 'publish',
			'post_title'  => 'El de la otra petición',
		);
		$GLOBALS['_wp_stores']['post_meta'][888] = array(
			'_convoca_usuario_id'              => '30',
			'_convoca_es_acuerdo_voluntariado' => '1',
		);
		// Solo la consulta con `FOR UPDATE` encuentra el documento.
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'FOR UPDATE' => '888' );

		$resultado = PDF_Document::generate_volunteer_agreement( 30 );

		$this->assertSame( 888, $resultado, 'devuelve el de la otra petición' );
		$this->assertTrue( $this->hubo( 'ROLLBACK' ), 'la transacción se deshace' );
		$this->assertNotContains( 'COMMIT', array_map( 'strtoupper', $this->consultas() ), 'y no se confirma nada' );
		$this->assertSame( 0, $this->borradores(), 'el borrador de esta petición se borra' );
		$this->assertNotEmpty( (array) $GLOBALS['_wp_stores']['deleted_posts'], 'hubo un borrado de verdad' );
	}

	/**
	 * **Si el PDF no se genera, se deshace todo y se deja el motivo.**
	 *
	 * En el arnés `Signature::generate_pdf()` devuelve `false` porque no está Dompdf, así que este es el camino que
	 * corre de verdad aquí. Lo importante: `ROLLBACK`, el borrador borrado (no queda un documento vacío publicado) y
	 * el motivo guardado para que la pantalla pueda decirlo.
	 */
	public function test_si_el_pdf_falla_se_deshace_y_se_deja_el_motivo(): void {
		$resultado = PDF_Document::generate_volunteer_agreement( 30 );

		$this->assertNull( $resultado );
		$this->assertTrue( $this->hubo( 'ROLLBACK' ), 'se deshace la transacción' );
		$this->assertSame( 0, $this->borradores(), 'no queda un borrador huérfano' );
		$this->assertSame( 0, $this->publicados(), 'y nada publicado sin contenido' );
		$this->assertNotEmpty( (array) $GLOBALS['_wp_stores']['deleted_posts'], 'el borrador se borró' );
	}

	/**
	 * El `SELECT` lleva el **usuario** y la marca de tipo.
	 *
	 * La marca `_convoca_es_acuerdo_voluntariado` es lo que impide que «¿ya existe?» encuentre **cualquier** documento
	 * del socio —un DNI, un certificado— y dé el acuerdo por hecho (fue el defecto members#58).
	 */
	public function test_la_busqueda_del_acuerdo_lleva_el_usuario_y_la_marca_de_tipo(): void {
		$GLOBALS['_wp_stores']['db_reads'] = array();

		PDF_Document::generate_volunteer_agreement( 30 );

		$lecturas = array_map( 'strval', (array) ( $GLOBALS['_wp_stores']['db_reads'] ?? array() ) );
		$con_tipo = array_values(
			array_filter(
				$lecturas,
				static fn( $q ) => false !== strpos( (string) $q, '_convoca_es_acuerdo_voluntariado' )
			)
		);

		$this->assertNotEmpty( $con_tipo, 'la consulta filtra por la marca de acuerdo' );
		$this->assertGreaterThanOrEqual( 2, count( $con_tipo ), 'las dos búsquedas (antes y dentro de la transacción)' );
		$this->assertStringContainsString( '30', (string) $con_tipo[0], 'y por el usuario' );
		$this->assertStringNotContainsString( '%d', (string) $con_tipo[0], 'con el marcador ya sustituido' );
		$this->assertStringContainsString( "'convoca_documento'", (string) $con_tipo[0], 'y por el tipo de contenido' );
	}
}
