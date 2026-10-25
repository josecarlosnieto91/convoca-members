<?php
/**
 * Las metacajas del socio: borrado de datos, caducidad de documentos.
 *
 * Dos acciones que tocan datos personales y documentos:
 *
 *  - **`ajax_delete_member_data()`**: borra la ficha del socio, sus inscripciones y sus horas. Es irreversible, así que
 *    las guardas (nonce, `manage_options`, que la ficha sea de tipo `miembro`) son lo único que hay entre un clic y la
 *    pérdida definitiva. Y borra las inscripciones **por correo**: con el correo vacío eso puede arrastrar las de otros.
 *  - **`guardar_caducidad()`**: guarda hasta cuándo vale un documento del voluntariado. Una fecha imposible guardada
 *    ahí deja el documento como vigente para siempre o como caducado desde siempre.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Metaboxes;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-metaboxes.php';

/**
 * Metacajas.
 */
final class MetacajasDeSocioTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Pantalla.
	 *
	 * @var Admin_Metaboxes
	 */
	private Admin_Metaboxes $metacajas;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'   => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'    => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'deleted' => $GLOBALS['_wp_stores']['deleted_posts'] ?? null,
			'caps'    => $GLOBALS['_test_caps'] ?? array(),
			'postcaps'=> $GLOBALS['_test_post_caps'] ?? null,
			'nonces'  => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'json'    => $GLOBALS['_wp_json'] ?? array(),
			'post'    => $_POST ?? array(),
			'host'    => $GLOBALS['_wp_referer'] ?? null,
			'die'     => $GLOBALS['_wp_die_lanza'] ?? null,
		);

		$GLOBALS['_wp_stores']['posts']          = array();
		$GLOBALS['_wp_stores']['post_meta']      = array();
		$GLOBALS['_wp_stores']['deleted_posts']  = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'nonce-bueno' );
		$GLOBALS['_test_caps']                   = array( 'manage_options' => true );
		$GLOBALS['_wp_json']                     = array();
		$GLOBALS['_wp_die_lanza']                = true;
		$_POST                                   = array();
		$_REQUEST                                = array();
		unset( $GLOBALS['_test_post_caps'] );

		$this->metacajas = ( new \ReflectionClass( Admin_Metaboxes::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']         = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']     = $this->previo['meta'];
		$GLOBALS['_test_caps']                  = $this->previo['caps'];
		$GLOBALS['_wp_json']                    = $this->previo['json'];
		$_POST                                  = $this->previo['post'];
		$_REQUEST                               = array();
		$GLOBALS['_wp_die_lanza']               = $this->previo['die'];

		if ( null === $this->previo['deleted'] ) {
			unset( $GLOBALS['_wp_stores']['deleted_posts'] );
		} else {
			$GLOBALS['_wp_stores']['deleted_posts'] = $this->previo['deleted'];
		}
		if ( null === $this->previo['postcaps'] ) {
			unset( $GLOBALS['_test_post_caps'] );
		} else {
			$GLOBALS['_test_post_caps'] = $this->previo['postcaps'];
		}
		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}
	}

	/**
	 * Siembra una ficha de socio y devuelve su ID.
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
	 * Siembra un post de otro tipo.
	 *
	 * @param string $tipo Tipo.
	 * @param int    $id   ID.
	 * @return int
	 */
	private function otro( string $tipo, int $id ): int {
		$GLOBALS['_wp_stores']['posts'][ $id ] = (object) array(
			'ID'          => $id,
			'post_type'   => $tipo,
			'post_status' => 'publish',
			'post_title'  => $tipo . ' ' . $id,
		);
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array();

		return $id;
	}

	/**
	 * Borrados apuntados por el arnés.
	 *
	 * @return int[]
	 */
	private function borrados(): array {
		return array_map( 'intval', (array) ( $GLOBALS['_wp_stores']['deleted_posts'] ?? array() ) );
	}

	/**
	 * Ejecuta y devuelve el mensaje del JSON de error, si lo hubo.
	 *
	 * @param string $metodo Método.
	 * @return string|null
	 */
	private function ejecutar( string $metodo ): ?string {
		try {
			$this->metacajas->$metodo();
		} catch ( \Throwable $e ) {
			$mensaje = $e->getMessage();
			// El flujo de este fichero puede cortarse por tres sitios: un JSON de error, un `wp_die` (nonce o permiso)
			// o un redirect. Los tres son «paró aquí» y se devuelve el texto tal cual.
			foreach ( array( 'wp_send_json', 'wp_die', 'wp_safe_redirect' ) as $corte ) {
				if ( false !== strpos( $mensaje, $corte ) ) {
					return $mensaje;
				}
			}
			throw $e;
		}
		return null;
	}

	/* ── Borrado de datos del socio ──────────────────────────────────── */

	/**
	 * **Sin nonce no se borra nada.** Es irreversible.
	 */
	public function test_el_borrado_exige_nonce(): void {
		$id       = $this->ficha( array( '_convoca_email' => 'socia@example.test' ) );
		$_POST    = array( 'post_id' => (string) $id, 'nonce' => 'nonce-malo' );
		$_REQUEST = $_POST;

		$this->ejecutar( 'ajax_delete_member_data' );

		// Con `expectException` esta aserción **no se ejecutaría** (ya me pasó en otra tanda): se captura y se sigue.
		$this->assertSame( array(), $this->borrados(), 'sin nonce no se borra nada' );
	}

	/**
	 * **Sin `manage_options` no se borra**: borrar el censo es cosa de quien administra, no de quien gestiona.
	 */
	public function test_el_borrado_exige_administrar(): void {
		$id       = $this->ficha( array( '_convoca_email' => 'socia@example.test' ) );
		$_POST    = array(
			'post_id' => (string) $id,
			'nonce'   => 'nonce-bueno',
		);
		$_REQUEST    = $_POST;
		$GLOBALS['_test_caps'] = array( 'manage_options' => false );

		$mensaje = (string) $this->ejecutar( 'ajax_delete_member_data' );

		$this->assertStringContainsString( 'Sin permisos', $mensaje );
		$this->assertSame( array(), $this->borrados(), 'sin permiso no se borra nada' );
	}

	/**
	 * Un post que no es una ficha de socio no se borra, aunque el nonce valga.
	 */
	public function test_no_se_borra_un_post_que_no_es_un_socio(): void {
		$actividad = $this->otro( 'actividad', 600 );
		$_POST     = array(
			'post_id' => (string) $actividad,
			'nonce'   => 'nonce-bueno',
		);
		$_REQUEST = $_POST;

		$mensaje = (string) $this->ejecutar( 'ajax_delete_member_data' );

		$this->assertStringContainsString( 'no encontrado', $mensaje );
		$this->assertSame( array(), $this->borrados(), 'una actividad no se borra desde aquí' );
	}

	/**
	 * Una ficha que no existe tampoco.
	 */
	public function test_no_se_borra_una_ficha_que_no_existe(): void {
		$_POST    = array(
			'post_id' => '999',
			'nonce'   => 'nonce-bueno',
		);
		$_REQUEST = $_POST;

		$mensaje = (string) $this->ejecutar( 'ajax_delete_member_data' );

		$this->assertStringContainsString( 'no encontrado', $mensaje );
		$this->assertSame( array(), $this->borrados() );
	}

	/**
	 * **Se borra lo del socio, y solo lo suyo**: su ficha, sus horas y sus inscripciones.
	 */
	public function test_se_borra_lo_del_socio_y_nada_mas(): void {
		$id   = $this->ficha( array( '_convoca_email' => 'socia@example.test' ) );
		$hora = $this->otro( 'registro_hora', 700 );
		$GLOBALS['_wp_stores']['post_meta'][ $hora ]['_convoca_member_id'] = (string) $id;

		$hora_ajena = $this->otro( 'registro_hora', 701 );
		$GLOBALS['_wp_stores']['post_meta'][ $hora_ajena ]['_convoca_member_id'] = '501';

		$_POST    = array(
			'post_id' => (string) $id,
			'nonce'   => 'nonce-bueno',
		);
		$_REQUEST = $_POST;

		$this->ejecutar( 'ajax_delete_member_data' );

		$this->assertContains( $id, $this->borrados(), 'la ficha' );
		$this->assertContains( $hora, $this->borrados(), 'su hora' );
		$this->assertNotContains( $hora_ajena, $this->borrados(), 'la hora de otra persona NO' );
	}

	/**
	 * **Con el correo vacío no se arrastra a nadie.**
	 *
	 * Las inscripciones se buscan **por correo**. Si la ficha no tiene correo y la búsqueda se hace con la cadena vacía,
	 * lo que casa es cualquier inscripción sin correo — de otras personas. Borrar los datos de una socia no puede
	 * llevarse por delante los de nadie más.
	 */
	public function test_con_el_correo_vacio_no_se_borran_inscripciones_ajenas(): void {
		$id = $this->ficha( array( '_convoca_email' => '' ) );

		// Una inscripción de otra persona, con el correo guardado **vacío** (es lo que deja una importación a medias).
		$ajena = $this->otro( 'inscripcion', 800 );
		$GLOBALS['_wp_stores']['post_meta'][ $ajena ]['_convoca_email'] = '';

		$_POST    = array(
			'post_id' => (string) $id,
			'nonce'   => 'nonce-bueno',
		);
		$_REQUEST = $_POST;

		$this->ejecutar( 'ajax_delete_member_data' );

		$this->assertContains( $id, $this->borrados(), 'la ficha sí' );
		$this->assertNotContains( $ajena, $this->borrados(), 'la inscripción de otra persona NO' );
	}

	/* ── Caducidad de documentos ─────────────────────────────────────── */

	/**
	 * Un post que no es un documento se rechaza antes de tocar nada.
	 */
	public function test_la_caducidad_exige_que_sea_un_documento(): void {
		$id       = $this->ficha( array() );
		$_POST    = array( 'documento_id' => (string) $id );
		$_REQUEST = $_POST;

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Documento no encontrado/i' );

		$this->metacajas->guardar_caducidad();
	}

	/**
	 * **Sin el permiso de documentos del voluntariado no se cambia la vigencia.**
	 *
	 * Un socio no decide cuándo caduca su propio DNI.
	 */
	public function test_la_caducidad_exige_permiso(): void {
		$doc      = $this->otro( 'convoca_documento', 900 );
		$_POST    = array( 'documento_id' => (string) $doc );
		$_REQUEST = $_POST;
		$GLOBALS['_test_caps'] = array( 'gestionar_documentos_voluntariado' => false );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/No tienes permiso/i' );

		$this->metacajas->guardar_caducidad();
	}

	/**
	 * Con permiso y fecha válida, se guarda.
	 */
	public function test_una_fecha_valida_se_guarda(): void {
		$doc      = $this->otro( 'convoca_documento', 900 );
		$_POST    = array(
			'documento_id'    => (string) $doc,
			'fecha_caducidad' => '2027-06-30',
			'_wpnonce'        => 'nonce-bueno',
		);
		$_REQUEST = $_POST;

		try {
			$this->metacajas->guardar_caducidad();
		} catch ( \Throwable $e ) {
			if ( false === strpos( $e->getMessage(), 'wp_safe_redirect' ) ) {
				throw $e;
			}
		}

		$this->assertSame( '2027-06-30', $GLOBALS['_wp_stores']['post_meta'][ $doc ]['_convoca_fecha_caducidad'] ?? '' );
	}

	/**
	 * **Una fecha imposible no se guarda.**
	 *
	 * La comprobación es un patrón `AAAA-MM-DD`, y `2026-13-45` lo cumple: trece meses y cuarenta y cinco días. Esa
	 * fecha deja el documento con una vigencia que no existe, y quien compara contra hoy no sabe qué hacer con ella.
	 */
	public function test_una_fecha_imposible_no_se_guarda(): void {
		$doc      = $this->otro( 'convoca_documento', 900 );
		$_POST    = array(
			'documento_id'    => (string) $doc,
			'fecha_caducidad' => '2026-13-45',
			'_wpnonce'        => 'nonce-bueno',
		);
		$_REQUEST = $_POST;

		try {
			$this->metacajas->guardar_caducidad();
		} catch ( \Throwable $e ) {
			if ( false === strpos( $e->getMessage(), 'wp_safe_redirect' ) ) {
				throw $e;
			}
		}

		$this->assertSame(
			'',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][ $doc ]['_convoca_fecha_caducidad'] ?? '' ),
			'un 45 de mes 13 no puede quedar guardado como caducidad'
		);
	}

	/**
	 * Una fecha vacía **borra** la caducidad: es como se quita.
	 */
	public function test_una_fecha_vacia_borra_la_caducidad(): void {
		$doc = $this->otro( 'convoca_documento', 900 );
		$GLOBALS['_wp_stores']['post_meta'][ $doc ]['_convoca_fecha_caducidad'] = '2027-01-01';

		$_POST    = array(
			'documento_id'    => (string) $doc,
			'fecha_caducidad' => '',
			'_wpnonce'        => 'nonce-bueno',
		);
		$_REQUEST = $_POST;

		try {
			$this->metacajas->guardar_caducidad();
		} catch ( \Throwable $e ) {
			if ( false === strpos( $e->getMessage(), 'wp_safe_redirect' ) ) {
				throw $e;
			}
		}

		$this->assertArrayNotHasKey( '_convoca_fecha_caducidad', $GLOBALS['_wp_stores']['post_meta'][ $doc ] );
	}

	/**
	 * Una fecha con formato de aquí (`30/06/2027`) tampoco se guarda: el producto espera `AAAA-MM-DD`.
	 */
	public function test_una_fecha_con_barras_no_se_guarda(): void {
		$doc      = $this->otro( 'convoca_documento', 900 );
		$_POST    = array(
			'documento_id'    => (string) $doc,
			'fecha_caducidad' => '30/06/2027',
			'_wpnonce'        => 'nonce-bueno',
		);
		$_REQUEST = $_POST;

		try {
			$this->metacajas->guardar_caducidad();
		} catch ( \Throwable $e ) {
			if ( false === strpos( $e->getMessage(), 'wp_safe_redirect' ) ) {
				throw $e;
			}
		}

		$this->assertSame( '', (string) ( $GLOBALS['_wp_stores']['post_meta'][ $doc ]['_convoca_fecha_caducidad'] ?? '' ) );
	}
}
