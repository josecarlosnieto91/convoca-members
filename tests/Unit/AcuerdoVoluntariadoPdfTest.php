<?php
/**
 * El acuerdo de voluntariado en PDF.
 *
 * `generate_volunteer_agreement()` es un documento **legal** y su parte fina no da error si se rompe:
 *
 *  - **idempotencia**: si el acuerdo ya existe, devuelve su ID y no crea otro. Dos acuerdos del mismo voluntario
 *    son dos documentos firmados con el mismo contenido y ninguna forma de saber cuál vale;
 *  - **nada de borradores huérfanos**: el post se crea en `draft` fuera de la transacción y, si algo falla
 *    después, hay que borrarlo. Cada salida tiene su `ROLLBACK` + `wp_delete_post`;
 *  - **la marca `_convoca_es_acuerdo_voluntariado`**: sin ella, «¿ya existe?» encontraba **cualquier** documento
 *    del socio y devolvía el primero —un DNI de otra prueba—, así que el acuerdo no se generaba nunca
 *    (`members#58`).
 *
 * Lo que **no** se mide aquí, y por qué: el camino en el que el PDF se genera de verdad necesita
 * `Convoca\Core\Signature`, que vive en el plugin Core y al instanciarse pide `CONVOCA_COMMON_DIR`, una constante
 * que el arranque de Core define y esta suite no carga. Doblarla sería doblar producto; además, en esta suite la
 * guarda `class_exists( '\Convoca\Core\Signature' )` **sí** pasa (el autoload de Core la encuentra) y lo que
 * ocurre es que la instancia lanza, así que el camino que se ejecuta de verdad es el `catch`: ROLLBACK, borrar el
 * borrador y devolver `null`. Eso sí se mide, y es lo que queda comprobado.
 *
 * Excepción escrita: la rama `if ( ! $generated_path )` —el PDF se genera a medias y devuelve `false`— **no** se
 * mide. Para llegar ahí haría falta un `Signature` que contestara `false` sin lanzar, y el de Core lanza al
 * instanciarse en esta suite; doblarlo sería doblar producto. Lo que sí queda medido es el `catch`, que es la
 * salida que se ejecuta de verdad. Un mutante que quita el `wp_delete_post` de esa rama sobrevive, y se sabe.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\PDF_Document;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/PDF_Document.php';

/**
 * Acuerdo de voluntariado.
 */
final class AcuerdoVoluntariadoPdfTest extends TestCase {

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
			'usermeta' => $GLOBALS['_wp_stores']['user_meta'] ?? array(),
			'borrados' => $GLOBALS['_wp_stores']['deleted_posts'] ?? null,
			'gvmapa'   => $GLOBALS['_wp_stores']['get_var_mapa'] ?? null,
			'trans'    => $GLOBALS['_wp_stores']['transients'] ?? array(),
			'opts'     => $GLOBALS['_wp_stores']['options'] ?? array(),
			'acciones' => $GLOBALS['_wp_actions'] ?? null,
			'hooks'    => $GLOBALS['_wp_hooks'] ?? null,
			'filtros'  => $GLOBALS['_wp_filters'] ?? null,
			'user'     => $GLOBALS['_test_user_id'] ?? null,
			'users'    => $GLOBALS['_test_users'] ?? null,
			'errlog'   => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']         = array();
		$GLOBALS['_wp_stores']['post_meta']     = array();
		$GLOBALS['_wp_stores']['deleted_posts'] = array();
		$GLOBALS['_wp_stores']['transients']    = array();
		$GLOBALS['_test_user_id']               = 7;

		// El `Logger` con `WP_DEBUG` escribe con `error_log()` y PHPUnit lo trata como salida inesperada.
		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-pdf-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']          = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']      = $this->previo['meta'];
		$GLOBALS['_wp_stores']['transients']     = $this->previo['trans'];
		$GLOBALS['_wp_stores']['deleted_posts']  = $this->previo['borrados'] ?? array();
		$GLOBALS['_wp_stores']['user_meta']      = $this->previo['usermeta'];
		$GLOBALS['_wp_actions']                  = $this->previo['acciones'];
		$GLOBALS['_wp_hooks']                    = $this->previo['hooks'] ?? array();
		$GLOBALS['_wp_filters']                  = $this->previo['filtros'];
		$GLOBALS['_test_users']                  = $this->previo['users'];

		if ( null === $this->previo['gvmapa'] ) {
			unset( $GLOBALS['_wp_stores']['get_var_mapa'] );
		} else {
			$GLOBALS['_wp_stores']['get_var_mapa'] = $this->previo['gvmapa'];
		}
		if ( null === $this->previo['user'] ) {
			unset( $GLOBALS['_test_user_id'] );
		} else {
			$GLOBALS['_test_user_id'] = $this->previo['user'];
		}

		ini_set( 'error_log', $this->previo['errlog'] ?: '' );
		if ( is_file( (string) ( $GLOBALS['_errlog_tmp'] ?? '' ) ) ) {
			unlink( (string) $GLOBALS['_errlog_tmp'] );
		}
	}

	/**
	 * Da de alta un usuario del arnés.
	 *
	 * @param int   $id     ID.
	 * @param array $extra  Campos de más.
	 * @return void
	 */
	private function usuario( int $id, array $extra = array() ): void {
		$GLOBALS['_test_users'][ $id ] = (object) array_merge(
			array(
				'ID'           => $id,
				'first_name'   => 'Ada',
				'display_name' => 'Ada Lovelace',
				'user_email'   => 'ada@example.test',
			),
			$extra
		);
	}

	/* ── generate_volunteer_agreement ────────────────────────────────── */

	/**
	 * Sin usuario no hay documento: no se crea nada.
	 */
	public function test_sin_usuario_devuelve_nulo_y_no_crea_nada(): void {
		$this->assertNull( PDF_Document::generate_volunteer_agreement( 999 ) );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'], 'no se creó ningún post' );
	}

	/**
	 * **Si el acuerdo ya existe, se devuelve el suyo y no se crea otro.**
	 *
	 * Es un documento legal: dos ejemplares con el mismo contenido y sin forma de saber cuál vale es peor que no
	 * tener ninguno.
	 */
	public function test_si_el_acuerdo_ya_existe_no_se_crea_otro(): void {
		$this->usuario( 7 );
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			'_convoca_es_acuerdo_voluntariado' => '4242',
		);

		$id = PDF_Document::generate_volunteer_agreement( 7 );

		$this->assertSame( 4242, $id, 'devuelve el documento que ya existía' );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'], 'no se creó un borrador' );
	}

	/**
	 * **Una caída a mitad no deja borradores huérfanos.**
	 *
	 * El post nace en `draft` fuera de la transacción. Si después falla la generación, cada salida tiene que
	 * hacer `ROLLBACK` **y** borrarlo; si no, el socio acumula borradores vacíos en el listado de documentos.
	 */
	public function test_una_caida_a_mitad_no_deja_borradores(): void {
		$this->usuario( 7 );

		$id = PDF_Document::generate_volunteer_agreement( 7 );

		$this->assertNull( $id, 'no se generó' );
		$this->assertNotEmpty( $GLOBALS['_wp_stores']['posts'], 'se creó el borrador de trabajo' );
		$borradores = array_keys( $GLOBALS['_wp_stores']['posts'] );
		$this->assertContains(
			(int) $borradores[0],
			array_map( 'intval', $GLOBALS['_wp_stores']['deleted_posts'] ),
			'el borrador se borra al salir'
		);
	}

	/**
	 * Si la creación del borrador falla, se para ahí.
	 */
	public function test_si_falla_la_creacion_del_borrador_devuelve_nulo(): void {
		$this->usuario( 7 );
		$GLOBALS['_wp_insert_devuelve_error'] = 'la base no responde';

		try {
			$this->assertNull( PDF_Document::generate_volunteer_agreement( 7 ) );
			$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'], 'no se creó nada' );
			// Y **no se intenta borrar** un borrador que nunca existió: si la guarda del `WP_Error` desapareciera,
			// el flujo seguiría con un `WP_Error` en la mano y acabaría llamando a `wp_delete_post()` sobre él.
			$this->assertSame( array(), $GLOBALS['_wp_stores']['deleted_posts'], 'no se borra lo que no se creó' );
		} finally {
			unset( $GLOBALS['_wp_insert_devuelve_error'] );
		}
	}

	/* ── add_pdf_to_email ────────────────────────────────────────────── */

	/**
	 * Sin documento no se adjunta nada.
	 */
	public function test_sin_documento_no_se_adjunta_nada(): void {
		$adjuntos = PDF_Document::add_pdf_to_email( array( 'otra-cosa.pdf' ), 7 );

		$this->assertSame( array( 'otra-cosa.pdf' ), $adjuntos );
	}

	/**
	 * Con documento y fichero en su sitio, se adjunta.
	 */
	public function test_con_documento_y_fichero_se_adjunta(): void {
		$ruta = sys_get_temp_dir() . '/convoca-doc-test.pdf';
		file_put_contents( $ruta, '%PDF-1.4 prueba' );

		$GLOBALS['_wp_stores']['posts'][900]      = (object) array(
			'ID'          => 900,
			'post_type'   => 'convoca_documento',
			'post_status' => 'publish',
			'post_title'  => 'Acuerdo',
		);
		$GLOBALS['_wp_stores']['post_meta'][900] = array(
			'_convoca_usuario_id'    => '7',
			'_convoca_documento_path' => $ruta,
		);

		try {
			$adjuntos = PDF_Document::add_pdf_to_email( array(), 7 );
		} finally {
			unlink( $ruta );
		}

		$this->assertSame( array( $ruta ), $adjuntos );
	}

	/**
	 * **Un fichero que ya no está no se adjunta.**
	 *
	 * Se comprueba con `file_exists()`: adjuntar una ruta muerta hace que el correo salga sin el acuerdo y sin
	 * decir por qué, y el voluntario se queda sin su copia.
	 */
	public function test_un_fichero_que_no_existe_no_se_adjunta(): void {
		$GLOBALS['_wp_stores']['posts'][901]      = (object) array(
			'ID'          => 901,
			'post_type'   => 'convoca_documento',
			'post_status' => 'publish',
			'post_title'  => 'Acuerdo',
		);
		$GLOBALS['_wp_stores']['post_meta'][901] = array(
			'_convoca_usuario_id'     => '7',
			'_convoca_documento_path' => '/tmp/no-existe-nunca-' . PHP_INT_MAX . '.pdf',
		);

		$this->assertSame( array(), PDF_Document::add_pdf_to_email( array(), 7 ) );
	}

	/* ── show_pdf_error_notice ───────────────────────────────────────── */

	/**
	 * El aviso de error se pinta **una vez** y se consume.
	 */
	public function test_el_aviso_de_error_se_pinta_y_se_consume(): void {
		$GLOBALS['_wp_stores']['transients'][ 'convoca_pdf_gen_error_7' ] = 'No such file or directory';

		// `Utils::admin_notice()` **imprime** el aviso, no lo apunta en ningún sitio: hay que capturar la salida.
		ob_start();
		PDF_Document::show_pdf_error_notice();
		$texto = (string) ob_get_clean();

		$this->assertStringContainsString( 'No such file or directory', $texto, 'el motivo llega a la pantalla' );
		$this->assertArrayNotHasKey(
			'convoca_pdf_gen_error_7',
			(array) $GLOBALS['_wp_stores']['transients'],
			'el aviso se borra: no se repite en cada pantalla'
		);
	}

	/**
	 * Sin error guardado no se pinta nada.
	 */
	public function test_sin_error_guardado_no_hay_aviso(): void {
		ob_start();
		PDF_Document::show_pdf_error_notice();
		$texto = (string) ob_get_clean();

		$this->assertSame( '', $texto, 'sin error guardado no se pinta nada' );
	}

	/* ── init ────────────────────────────────────────────────────────── */

	/**
	 * `init()` engancha el aviso y el adjunto: si falta cualquiera de los dos, o el correo sale sin el acuerdo o
	 * el error no se ve nunca.
	 */
	public function test_init_engancha_el_aviso_y_el_adjunto(): void {
		PDF_Document::init();

		// `add_action()` del arnés apunta en `_wp_hooks[nombre]` y `add_filter()` en `_wp_filters[nombre]`, así que
		// los nombres son las claves del mapa, no un campo de una lista.
		$nombres = array_merge( array_keys( (array) $GLOBALS['_wp_hooks'] ), array_keys( (array) $GLOBALS['_wp_filters'] ) );

		$this->assertContains( 'convoca_voluntario_aprobado', $nombres, 'genera el acuerdo al aprobar' );
		$this->assertContains( 'convoca_voluntario_aprobado_attachments', $nombres, 'lo adjunta al correo' );
		$this->assertContains( 'admin_notices', $nombres, 'y avisa si falló' );
	}
}
