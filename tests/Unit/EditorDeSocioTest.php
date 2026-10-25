<?php
/**
 * El editor de socio: guardar la ficha.
 *
 * `handle_save()` crea o actualiza un socio. Tres cosas de aquí no dan error si se rompen:
 *
 *  - **el nonce y el permiso son distintos para editar y para crear** (`edit_post` sobre la ficha frente a
 *    `gestionar_miembros`): si se unifican, quien puede editar una ficha puede crear socios nuevos;
 *  - **`estado_miembro` NO se escribe en el bucle de campos** (`E2E-1`): la máquina de estados lee el valor viejo
 *    antes de cambiarlo, y si el bucle lo dejara escrito vería `old === new` y **no numeraría ni activaría** a nadie;
 *  - **al activar se asigna número de socio y cuota antes del `change`**, porque ese `change` dispara el correo de
 *    bienvenida y el correo tiene que llevar ya el número.
 *
 * Todo termina en `exit;`, así que cada prueba va en **proceso aparte** y se lee el redirect.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Admin_Member_Editor;
use Convoca\Members\CPT_Miembro;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/admin/class-admin-member-editor.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';
require_once dirname( __DIR__, 2 ) . '/includes/Roles_Miembro.php';

/**
 * Guardar la ficha.
 */
final class EditorDeSocioTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Editor.
	 *
	 * @var Admin_Member_Editor
	 */
	private Admin_Member_Editor $editor;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'    => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'     => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'caps'     => $GLOBALS['_test_caps'] ?? array(),
			'postcaps' => $GLOBALS['_test_post_caps'] ?? null,
			'nonces'   => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'trans'    => $GLOBALS['_wp_stores']['transients'] ?? array(),
			'post'     => $_POST ?? array(),
			'user'     => $GLOBALS['_test_user_id'] ?? null,
			'die'      => $GLOBALS['_wp_die_lanza'] ?? null,
			'errlog'   => ini_get( 'error_log' ),
		);

		$GLOBALS['_wp_stores']['posts']          = array();
		$GLOBALS['_wp_stores']['post_meta']      = array();
		$GLOBALS['_wp_stores']['transients']     = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'nonce-bueno' );
		$GLOBALS['_test_caps']                   = array(
			'gestionar_miembros' => true,
			'manage_options'     => true,
		);
		$GLOBALS['_test_user_id']                = 10;
		// Sin esto, `wp_die()` **no lanza** y todas las guardas de `handle_save()` pasan de largo: el flujo llega al
		// redirect y la prueba no distingue «se paró» de «siguió».
		$GLOBALS['_wp_die_lanza']                = true;
		unset( $GLOBALS['_test_post_caps'] );

		// El `Logger` con `WP_DEBUG` escribe con `error_log()` y PHPUnit, en proceso aparte, lo trata como salida
		// inesperada: el caso fallaría por el log y no por el código.
		$GLOBALS['_errlog_tmp'] = sys_get_temp_dir() . '/convoca-editor-test.log';
		ini_set( 'error_log', $GLOBALS['_errlog_tmp'] );

		$this->editor = ( new \ReflectionClass( Admin_Member_Editor::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']      = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']  = $this->previo['meta'];
		$GLOBALS['_wp_stores']['transients'] = $this->previo['trans'];
		$GLOBALS['_test_caps']               = $this->previo['caps'];
		$GLOBALS['_wp_die_lanza']            = $this->previo['die'];
		$_POST                               = $this->previo['post'];

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
	 * Siembra una ficha de socio.
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
	 * Ejecuta el guardado.
	 *
	 * @param array $post Campos.
	 * @return string|null Texto del corte (redirect o wp_die), o null si terminó sin cortar.
	 */
	private function guardar( array $post ): ?string {
		$_POST    = $post;
		$_REQUEST = $post;

		try {
			$this->editor->handle_save();
		} catch ( \Throwable $e ) {
			foreach ( array( 'wp_safe_redirect', 'wp_die' ) as $corte ) {
				if ( false !== strpos( $e->getMessage(), $corte ) ) {
					return $e->getMessage();
				}
			}
			throw $e;
		}
		return null;
	}

	/**
	 * Una solicitud de edición completa.
	 *
	 * @param int   $id    Ficha.
	 * @param array $extra Campos de más.
	 * @return array
	 */
	private function edicion( int $id, array $extra = array() ): array {
		return array_merge(
			array(
				'_convoca_nonce'    => 'nonce-bueno',
				'post_id'           => (string) $id,
				'convoca_nombre'    => 'Ada Lovelace',
				'convoca_email'     => 'ada@example.test',
				'convoca_dni'       => '00000000T',
				'convoca_telefono'  => '600000000',
				'convoca_municipio' => 'Oviedo',
			),
			$extra
		);
	}

	/* ── Guardas ─────────────────────────────────────────────────────── */

	/**
	 * Sin el campo del nonce no se guarda nada.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_sin_el_campo_nonce_se_para(): void {
		$id = $this->ficha( array( '_convoca_email' => 'vieja@example.test' ) );

		$corte = (string) $this->guardar(
			array(
				'post_id'        => (string) $id,
				'convoca_nombre' => 'Otra',
			)
		);

		$this->assertStringContainsString( 'Acceso denegado', $corte );
		$this->assertSame( 'Socia 500', $GLOBALS['_wp_stores']['posts'][ $id ]->post_title, 'no se tocó la ficha' );
	}

	/**
	 * **Editar exige `edit_post` sobre la ficha**, no basta con gestionar socios.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_editar_exige_permiso_sobre_la_ficha(): void {
		$id = $this->ficha( array() );
		$GLOBALS['_test_caps']      = array( 'gestionar_miembros' => true );
		$GLOBALS['_test_post_caps'] = array( $id => array( 'edit_post' => false ) );

		$corte = (string) $this->guardar( $this->edicion( $id ) );

		$this->assertStringContainsString( 'No tienes permisos', $corte );
		$this->assertSame( 'Socia 500', $GLOBALS['_wp_stores']['posts'][ $id ]->post_title );
	}

	/**
	 * **Crear exige `gestionar_miembros`**, que es un permiso distinto.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_crear_exige_gestionar_miembros(): void {
		$GLOBALS['_test_caps'] = array( 'gestionar_miembros' => false );

		$corte = (string) $this->guardar(
			array(
				'_convoca_nonce' => 'nonce-bueno',
				'post_id'        => '0',
				'convoca_nombre' => 'Nueva Socia',
			)
		);

		$this->assertStringContainsString( 'No tienes permisos', $corte );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'], 'no se creó nada' );
	}

	/**
	 * Sin nombre no se guarda: es el título de la ficha.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_sin_nombre_no_se_guarda(): void {
		$id = $this->ficha( array() );

		$corte = (string) $this->guardar( $this->edicion( $id, array( 'convoca_nombre' => '   ' ) ) );

		$this->assertStringContainsString( 'nombre es obligatorio', $corte );
		$this->assertSame( 'Socia 500', $GLOBALS['_wp_stores']['posts'][ $id ]->post_title );
	}

	/**
	 * Si la creación falla, se para con su mensaje y no sigue guardando metas de una ficha que no existe.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_si_la_creacion_falla_se_para(): void {
		$GLOBALS['_wp_insert_devuelve_error'] = 'la base no responde';

		try {
			$corte = (string) $this->guardar(
				array(
					'_convoca_nonce' => 'nonce-bueno',
					'post_id'        => '0',
					'convoca_nombre' => 'Nueva Socia',
				)
			);
		} finally {
			unset( $GLOBALS['_wp_insert_devuelve_error'] );
		}

		$this->assertStringContainsString( 'Error al crear', $corte );
	}

	/* ── Guardado ────────────────────────────────────────────────────── */

	/**
	 * Los campos se guardan con su prefijo y saneados.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_los_campos_se_guardan(): void {
		$id = $this->ficha( array() );

		$this->guardar( $this->edicion( $id, array( 'convoca_observaciones' => 'Texto <b>libre</b>' ) ) );

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ] ?? array();
		$this->assertSame( 'Ada Lovelace', $GLOBALS['_wp_stores']['posts'][ $id ]->post_title );
		$this->assertSame( 'ada@example.test', $meta['_convoca_email'] ?? '' );
		$this->assertSame( '00000000T', $meta['_convoca_dni'] ?? '' );
		$this->assertSame( 'Oviedo', $meta['_convoca_municipio'] ?? '' );
		$this->assertStringNotContainsString( '<b>', (string) ( $meta['_convoca_observaciones'] ?? '' ), 'el texto libre va saneado' );
	}

	/**
	 * **Un correo inválido no se guarda como correo.**
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_un_correo_invalido_no_se_guarda(): void {
		$id = $this->ficha( array() );

		$this->guardar( $this->edicion( $id, array( 'convoca_email' => 'no-es-un-correo' ) ) );

		$this->assertSame( '', (string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_email'] ?? '' ) );
	}

	/**
	 * Las casillas se guardan como `1`/`0` **explícitos**, no como «vacío».
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_las_casillas_quedan_en_uno_o_cero(): void {
		$id = $this->ficha( array( '_convoca_pago_recurrente' => '1', '_convoca_es_voluntario' => '1' ) );

		$this->guardar( $this->edicion( $id ) );

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertSame( '0', (string) ( $meta['_convoca_pago_recurrente'] ?? 'x' ), 'sin marcar queda en 0' );
		$this->assertSame( '0', (string) ( $meta['_convoca_es_voluntario'] ?? 'x' ) );
	}

	/**
	 * **`estado_miembro` no se escribe en el bucle de campos.**
	 *
	 * Es el defecto `E2E-1`: si el bucle lo dejara escrito antes de que la máquina de estados lea el valor viejo, vería
	 * `old === new` y **no activaría ni numeraría** a nadie. Se permite expresamente en la lista de campos.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_el_estado_no_se_escribe_antes_de_la_maquina_de_estados(): void {
		$id = $this->ficha( array( '_convoca_estado_miembro' => 'pendiente_documentacion' ) );

		$this->guardar( $this->edicion( $id, array( 'convoca_estado_miembro' => 'pendiente_documentacion' ) ) );

		$this->assertSame(
			'pendiente_documentacion',
			$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado_miembro'] ?? '',
			'el estado no se pierde'
		);
	}

	/**
	 * **Al activar, el socio recibe número y la cuota queda activa.**
	 *
	 * Se asigna **antes** del `change`, porque ese cambio dispara el correo de bienvenida y el correo lleva el número.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_al_activar_se_asigna_numero_y_cuota(): void {
		$id = $this->ficha( array( '_convoca_estado_miembro' => 'pendiente_documentacion' ) );

		$this->guardar(
			$this->edicion(
				$id,
				array(
					'convoca_estado_miembro' => 'activo',
					'convoca_plan'           => 'socio',
				)
			)
		);

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertNotEmpty( (string) ( $meta['_convoca_numero_socio'] ?? '' ), 'tiene número de socio' );
		$this->assertSame( 'activa', (string) ( $meta['_convoca_estado_cuota'] ?? '' ), 'y la cuota queda activa' );

		// **Y la máquina de estados se ejecutó.** Es lo que se rompe si `estado_miembro` se escribe en el bucle de
		// campos (E2E-1): la máquina vería `old === new`, no cambiaría nada y **no saldría el correo de bienvenida**.
		// El número y la cuota los pone el `elseif` de reparación, así que sin este gancho la prueba pasaría igual.
		$this->assertContains(
			'convoca_members_estado_changed',
			array_column( (array) $GLOBALS['_wp_actions'], 'hook' ),
			'la máquina de estados tiene que disparar su aviso'
		);
	}

	/**
	 * **Un socio ya activo sin número se repara al guardar.**
	 *
	 * Es la red para fichas que se activaron por otro camino y quedaron sin numerar: sin número no salen en el
	 * certificado ni se les puede cobrar la cuota.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_un_socio_activo_sin_numero_se_repara(): void {
		$id = $this->ficha(
			array(
				'_convoca_estado_miembro' => 'activo',
			)
		);

		$this->guardar( $this->edicion( $id, array( 'convoca_estado_miembro' => 'activo' ) ) );

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertNotEmpty( (string) ( $meta['_convoca_numero_socio'] ?? '' ), 'se le asignó número' );
		$this->assertSame( 'activa', (string) ( $meta['_convoca_estado_cuota'] ?? '' ) );
	}

	/**
	 * Un socio nuevo se crea como `miembro`, publicado y con su autor.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_un_socio_nuevo_se_crea_bien(): void {
		$this->guardar(
			array(
				'_convoca_nonce' => 'nonce-bueno',
				'post_id'        => '0',
				'convoca_nombre' => 'Nueva Socia',
				'convoca_email'  => 'nueva@example.test',
			)
		);

		$this->assertCount( 1, $GLOBALS['_wp_stores']['posts'], 'se creó una ficha' );
		$nueva = reset( $GLOBALS['_wp_stores']['posts'] );
		$this->assertSame( 'miembro', $nueva->post_type );
		$this->assertSame( 'publish', $nueva->post_status );
		$this->assertSame( 'Nueva Socia', $nueva->post_title );
	}

	/**
	 * El aviso de «guardado» queda en un transitorio, para que la pantalla lo enseñe una vez.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_el_aviso_de_guardado_queda_para_la_pantalla(): void {
		$id = $this->ficha( array() );

		$this->guardar( $this->edicion( $id ) );

		$aviso = (string) ( $GLOBALS['_wp_stores']['transients'][ 'convoca_member_saved_10' ] ?? '' );
		$this->assertStringContainsString( 'actualizado', $aviso );
	}

	/**
	 * Crear y editar dan avisos distintos: «creado» no es lo mismo que «actualizado».
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_el_aviso_distingue_crear_de_actualizar(): void {
		$this->guardar(
			array(
				'_convoca_nonce' => 'nonce-bueno',
				'post_id'        => '0',
				'convoca_nombre' => 'Nueva Socia',
			)
		);

		$aviso = (string) ( $GLOBALS['_wp_stores']['transients'][ 'convoca_member_saved_10' ] ?? '' );
		$this->assertStringContainsString( 'creado', $aviso );
	}

	/* ── Condición de monitor ────────────────────────────────────────── */

	/**
	 * **Solo un administrador concede la condición de monitor**, y solo si la marca de presencia viene en el
	 * formulario.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_solo_un_administrador_concede_la_condicion_de_monitor(): void {
		// Monitor **se apoya en el voluntariado**: sin ser voluntaria aprobada, `Roles_Miembro` no concede nada y la
		// prueba no mediría la guarda del administrador.
		$id = $this->ficha(
			array(
				'_convoca_es_voluntario'       => '1',
				'_convoca_voluntario_aprobado' => '1',
			)
		);
		$GLOBALS['_test_caps'] = array(
			'gestionar_miembros' => true,
			'manage_options'     => false,
		);

		$this->guardar(
			$this->edicion(
				$id,
				array(
					// El voluntariado va en el POST: se guarda como `1`/`0` explícito y sin él la ficha dejaría
					// de ser voluntaria, con lo que la prueba pasaría por el motivo equivocado.
					'convoca_es_voluntario'   => '1',
					'convoca_monitor_control' => '1',
					'convoca_es_monitor'      => '1',
				)
			)
		);

		$this->assertSame(
			'',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_es_monitor'] ?? '' ),
			'un no-admin no reparte la condición de monitor'
		);
	}

	/**
	 * **Sin la marca de presencia no se toca la condición.**
	 *
	 * Un formulario que no traiga la casilla (porque esa parte no se pintó) no puede quitarle el rol a nadie.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_un_administrador_con_la_marca_concede_la_condicion(): void {
		$id = $this->ficha(
			array(
				'_convoca_es_voluntario'       => '1',
				'_convoca_voluntario_aprobado' => '1',
			)
		);

		$this->guardar(
			$this->edicion(
				$id,
				array(
					// El voluntariado va en el mismo POST: las casillas se guardan como `1`/`0` explícitos, así
					// que omitirla dejaría la ficha **sin** voluntariado y `Roles_Miembro` negaría el monitor.
					'convoca_es_voluntario'   => '1',
					'convoca_monitor_control' => '1',
					'convoca_es_monitor'      => '1',
				)
			)
		);

		$this->assertSame( '1', (string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_es_monitor'] ?? '' ) );
	}

	/**
	 * **Monitor sin voluntariado no se concede**, aunque sea un administrador y marque la casilla.
	 *
	 * El monitorado se apoya en el voluntariado: `Roles_Miembro::anadir()` lo rechaza en el sitio donde nace
	 * la condición, así que ninguna vía (pantalla, importación o API) deja una ficha de monitor sin ser
	 * voluntaria.
	 */
	public function test_monitor_sin_voluntariado_no_se_concede(): void {
		$id = $this->ficha(
			array(
				'_convoca_es_voluntario'       => '1',
				'_convoca_voluntario_aprobado' => '1',
			)
		);

		// Se desmarca el voluntariado en el mismo envío en el que se pide el monitorado.
		$this->guardar(
			$this->edicion(
				$id,
				array(
					'convoca_monitor_control' => '1',
					'convoca_es_monitor'      => '1',
				)
			)
		);

		$this->assertSame(
			'',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_es_monitor'] ?? '' ),
			'sin voluntariado no hay monitor'
		);
	}

	/**
	 * **Desmarcar quita la condición de monitor.**
	 *
	 * Un administrador que desmarca la casilla tiene que poder retirarla; si el `quitar()` desapareciera, la
	 * ficha se quedaría de monitor para siempre y seguiría firmando horas como tal.
	 */
	public function test_desmarcar_quita_la_condicion_de_monitor(): void {
		$id = $this->ficha(
			array(
				'_convoca_es_voluntario'       => '1',
				'_convoca_voluntario_aprobado' => '1',
				'_convoca_es_monitor'          => '1',
			)
		);

		$this->guardar(
			$this->edicion(
				$id,
				array(
					'convoca_es_voluntario'   => '1',
					'convoca_monitor_control' => '1',
					// `convoca_es_monitor` ausente = casilla desmarcada.
				)
			)
		);

		// `quitar()` escribe `'0'` y **no borra la meta**, a propósito: así se distingue «nunca se le puso»
		// (fichas antiguas, donde el rol de WordPress es la única prueba) de «se le quitó».
		$this->assertSame(
			'0',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_es_monitor'] ?? '' ),
			'la condición se retira'
		);
	}

	/**
	 * **Sin la marca de presencia no se toca la condición**, aunque la casilla venga marcada.
	 *
	 * Un formulario que no traiga esa parte (porque no se pintó) no puede quitarle el rol a nadie.
	 */
	public function test_sin_la_marca_no_se_toca_la_condicion(): void {
		$id = $this->ficha(
			array(
				'_convoca_es_voluntario'       => '1',
				'_convoca_voluntario_aprobado' => '1',
				'_convoca_es_monitor'          => '1',
			)
		);

		$this->guardar( $this->edicion( $id, array( 'convoca_es_monitor' => '' ) ) );

		$this->assertSame(
			'1',
			(string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_es_monitor'] ?? '' ),
			'sin la marca de presencia, la condición se queda como estaba'
		);
	}
}
