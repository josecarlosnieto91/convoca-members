<?php
/**
 * El alta de voluntariado: el formulario público.
 *
 * Es la puerta por la que entra gente nueva, y por ella pasan **datos personales**: nombre, DNI, fecha de nacimiento,
 * dirección, teléfono, y el consentimiento RGPD. Un fallo aquí no da error en pantalla: crea una cuenta de más, deja a
 * un menor sin tutor, o guarda sin consentimiento.
 *
 * Lo que se mide:
 *
 *  - el **límite de intentos** por persona (3 a la hora) y que responde 429;
 *  - las nueve validaciones obligatorias, **una a una**, con su mensaje;
 *  - que un **menor** exige DNI de tutor y que el del tutor también se valida;
 *  - que un DNI/NIE con espacios o guiones se **normaliza** antes de comparar;
 *  - el **duplicado por correo y por DNI** —incluida la clave heredada `_convoca_shifts_dni`—, que es lo que evita
 *    tener a la misma persona dos veces;
 *  - el camino feliz: usuario de WordPress + ficha de socio enlazada + metas.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\Form_Voluntariado;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/public/class-form-voluntariado.php';

/**
 * Alta de voluntariado.
 */
final class FormVoluntariadoTest extends TestCase {

	/**
	 * Valores previos.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Formulario.
	 *
	 * @var Form_Voluntariado
	 */
	private Form_Voluntariado $form;

	/**
	 * Monta el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->previo = array(
			'posts'     => $GLOBALS['_wp_stores']['posts'] ?? array(),
			'meta'      => $GLOBALS['_wp_stores']['post_meta'] ?? array(),
			'usuarios'  => $GLOBALS['_wp_stores']['usuarios'] ?? array(),
			'user_meta' => $GLOBALS['_wp_stores']['user_meta'] ?? array(),
			'options'   => $GLOBALS['_wp_stores']['options'] ?? array(),
			'transients'=> $GLOBALS['_wp_stores']['transients'] ?? array(),
			'nonces'    => $GLOBALS['_wp_stores']['nonces_validos'] ?? null,
			'die'       => $GLOBALS['_wp_die_lanza'] ?? null,
			'json'      => $GLOBALS['_wp_json'] ?? array(),
			'actions'   => $GLOBALS['_wp_actions'] ?? array(),
			'post'      => $_POST ?? array(),
			'remote'    => $_SERVER['REMOTE_ADDR'] ?? null,
			'limits'    => $GLOBALS['_wp_stores']['rate_limits'] ?? null,
			'cache'     => $GLOBALS['_wp_cache'] ?? null,
		);

		$GLOBALS['_wp_stores']['posts']          = array();
		$GLOBALS['_wp_stores']['post_meta']      = array();
		$GLOBALS['_wp_stores']['usuarios']       = array();
		$GLOBALS['_wp_stores']['user_meta']      = array();
		$GLOBALS['_wp_stores']['options']        = array();
		$GLOBALS['_wp_stores']['transients']     = array();
		$GLOBALS['_wp_stores']['nonces_validos'] = array( 'nonce-bueno' );
		$GLOBALS['_wp_die_lanza']                = true;
		$GLOBALS['_wp_json']                     = array();
		$GLOBALS['_wp_actions']                  = array();
		$_SERVER['REMOTE_ADDR']                  = '203.0.113.9';
		// El cupo de intentos se acumula por persona **y por conexión**, y el arnés ya lo emula: sin limpiarlo, la
		// prueba 17 se comería el cupo que gastaron las 16 anteriores desde la misma IP.
		$GLOBALS['_wp_stores']['rate_limits'] = array();
		$GLOBALS['_wp_cache']                 = array();

		$this->form = ( new \ReflectionClass( Form_Voluntariado::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el escenario.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['posts']      = $this->previo['posts'];
		$GLOBALS['_wp_stores']['post_meta']  = $this->previo['meta'];
		$GLOBALS['_wp_stores']['usuarios']   = $this->previo['usuarios'];
		$GLOBALS['_wp_stores']['user_meta']  = $this->previo['user_meta'];
		$GLOBALS['_wp_stores']['options']    = $this->previo['options'];
		$GLOBALS['_wp_stores']['transients'] = $this->previo['transients'];
		$GLOBALS['_wp_die_lanza']            = $this->previo['die'];
		$GLOBALS['_wp_json']                 = $this->previo['json'];
		$GLOBALS['_wp_actions']              = $this->previo['actions'];
		$_POST                               = $this->previo['post'];
		$_REQUEST                            = array();
		if ( null === $this->previo['remote'] ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->previo['remote'];
		}
		if ( null === $this->previo['limits'] ) {
			unset( $GLOBALS['_wp_stores']['rate_limits'] );
		} else {
			$GLOBALS['_wp_stores']['rate_limits'] = $this->previo['limits'];
		}
		$GLOBALS['_wp_cache'] = $this->previo['cache'] ?? array();
		if ( null === $this->previo['nonces'] ) {
			unset( $GLOBALS['_wp_stores']['nonces_validos'] );
		} else {
			$GLOBALS['_wp_stores']['nonces_validos'] = $this->previo['nonces'];
		}
	}

	/**
	 * Una solicitud completa y válida.
	 *
	 * @param array $extra Campos que se cambian o añaden.
	 * @return array
	 */
	private function solicitud( array $extra = array() ): array {
		return array_merge(
			array(
				'nonce'                    => 'nonce-bueno',
				'nombre'                   => 'Ada Lovelace',
				'dni'                      => '00000000T',
				'fecha_nacimiento'         => '1990-01-01',
				'email'                    => 'ada@example.test',
				'telefono'                 => '600000000',
				'direccion'                => 'Calle de Prueba 1',
				'municipio'                => 'Oviedo',
				'rgpd'                     => '1',
				'declaracion_responsable'  => '1',
				'intereses'                => array( 'medio-ambiente' ),
			),
			$extra
		);
	}

	/**
	 * Ejecuta el envío.
	 *
	 * @param array $datos Campos.
	 * @return void
	 */
	private function enviar( array $datos ): void {
		$_POST    = $datos;
		$_REQUEST = $datos;
		$this->form->handle_submit();
	}

	/**
	 * Errores devueltos en la última respuesta JSON.
	 *
	 * @return string[]
	 */
	private function errores(): array {
		$ultimo = end( $GLOBALS['_wp_json'] );
		if ( ! is_array( $ultimo ) ) {
			return array();
		}
		return (array) ( $ultimo['data']['errors'] ?? array() );
	}

	/**
	 * Ejecuta esperando un rechazo y devuelve los errores.
	 *
	 * @param array $datos Campos.
	 * @return string[]
	 */
	private function rechazo( array $datos ): array {
		try {
			$this->enviar( $datos );
		} catch ( \RuntimeException $e ) {
			if ( false === strpos( $e->getMessage(), 'wp_send_json_error' ) ) {
				throw $e;
			}
			return $this->errores();
		}
		$this->fail( 'el formulario aceptó una solicitud que debía rechazar' );

		return array();
	}

	/**
	 * Sin nonce no se procesa nada.
	 */
	public function test_sin_nonce_valido_se_para(): void {
		$datos = $this->solicitud( array( 'nonce' => 'nonce-malo' ) );

		$this->expectException( \RuntimeException::class );

		$this->enviar( $datos );
	}

	/**
	 * **El límite es por persona**: tres intentos a la hora y al cuarto se responde 429.
	 *
	 * Sin esto, el formulario público es un grifo abierto: alguien puede crear cuentas en bucle.
	 */
	public function test_el_cuarto_intento_de_la_misma_persona_se_bloquea(): void {
		$datos = $this->solicitud();

		// Tres intentos como la misma persona. El primero puede salir bien y los otros dos chocar con el duplicado;
		// lo que importa es que **cuenten**.
		for ( $i = 0; $i < 3; $i++ ) {
			try {
				$this->enviar( $datos );
			} catch ( \Throwable $e ) {
				// Se ignoran a propósito: se está gastando el cupo.
			}
		}

		// Se vacía lo apuntado para leer SOLO lo que responda el cuarto intento.
		$GLOBALS['_wp_json'] = array();

		try {
			$this->enviar( $datos );
			$this->fail( 'el cuarto intento de la misma persona tenía que bloquearse' );
		} catch ( \RuntimeException $e ) {
			if ( false === strpos( $e->getMessage(), 'wp_send_json_error' ) ) {
				throw $e;
			}
		}

		$ultimo = end( $GLOBALS['_wp_json'] );
		$this->assertSame( 429, $ultimo['status'] ?? null, 'responde 429, no un rechazo normal' );
	}

	/**
	 * **El cupo es por persona, no por conexión.**
	 *
	 * En una sede hay una sola conexión y varias personas: si el límite fuera por IP, los errores de una dejarían
	 * fuera a todas (members#46). Se agota el cupo de una y se comprueba que otra, desde la misma conexión, pasa.
	 */
	public function test_el_cupo_de_una_persona_no_bloquea_a_otra_de_la_misma_conexion(): void {
		$de_ana = $this->solicitud( array( 'email' => 'ana@example.test', 'dni' => '11111111H' ) );

		for ( $i = 0; $i < 4; $i++ ) {
			try {
				$this->enviar( $de_ana );
			} catch ( \Throwable $e ) {
				// Se agota el cupo de Ana.
			}
		}

		$GLOBALS['_wp_json'] = array();

		// Otra persona, misma IP.
		$de_bea = $this->solicitud( array( 'email' => 'bea@example.test', 'dni' => '22222222J', 'nombre' => 'Bea' ) );
		try {
			$this->enviar( $de_bea );
		} catch ( \RuntimeException $e ) {
			if ( false !== strpos( $e->getMessage(), 'wp_send_json_error' ) ) {
				$ultimo = end( $GLOBALS['_wp_json'] );
				$this->assertNotSame( 429, $ultimo['status'] ?? null, 'a Bea no la bloquea el cupo de Ana' );
				return;
			}
			throw $e;
		}
		$this->assertTrue( true, 'Bea pasó' );
	}

	/**
	 * Cada campo obligatorio tiene **su** mensaje. Uno a uno, porque un único «faltan datos» no dice qué corregir.
	 */
	public function test_cada_campo_obligatorio_tiene_su_mensaje(): void {
		$casos = array(
			'nombre'                  => '/El nombre es obligatorio/',
			'dni'                     => '/El DNI\/NIE es obligatorio/',
			'fecha_nacimiento'        => '/La fecha de nacimiento es obligatoria/',
			'email'                   => '/El email no es válido/',
			'telefono'                => '/El teléfono es obligatorio/',
			'direccion'               => '/La dirección es obligatoria/',
			'municipio'               => '/El municipio es obligatorio/',
			'rgpd'                    => '/Debes aceptar la política de privacidad/',
			'declaracion_responsable' => '/Debes aceptar la Declaración Responsable/',
		);

		foreach ( $casos as $campo => $patron ) {
			// Cada campo es un intento independiente: se limpia el cupo para que el mensaje que se mide sea el de la
			// validación y no el del límite de intentos.
			$GLOBALS['_wp_stores']['rate_limits'] = array();
			$GLOBALS['_wp_cache']                 = array();

			$datos = $this->solicitud();
			unset( $datos[ $campo ] );

			$errores = $this->rechazo( $datos );

			$this->assertNotEmpty( $errores, "sin '{$campo}' hay que rechazar" );
			$this->assertMatchesRegularExpression(
				$patron,
				implode( ' | ', $errores ),
				"el mensaje de '{$campo}' tiene que decir qué falta"
			);
			$this->assertSame( array(), $GLOBALS['_wp_stores']['usuarios'], "sin '{$campo}' no se crea cuenta" );
		}
	}

	/**
	 * **Un menor exige DNI de tutor.**
	 *
	 * Es la guarda que impide dar de alta a un chaval sin la firma de quien responde por él.
	 */
	public function test_un_menor_sin_dni_de_tutor_se_rechaza(): void {
		$errores = $this->rechazo( $this->solicitud( array( 'fecha_nacimiento' => '2015-01-01' ) ) );

		$this->assertMatchesRegularExpression( '/DNI del tutor es obligatorio/i', implode( ' | ', $errores ) );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['usuarios'], 'no se crea la cuenta' );
	}

	/**
	 * Y el DNI del tutor **también se valida**: no vale cualquier cosa.
	 */
	public function test_un_dni_de_tutor_invalido_se_rechaza(): void {
		$errores = $this->rechazo(
			$this->solicitud(
				array(
					'fecha_nacimiento' => '2015-01-01',
					'tutor_dni'        => '1234',
				)
			)
		);

		$this->assertMatchesRegularExpression( '/DNI del tutor no es válido/i', implode( ' | ', $errores ) );
	}

	/**
	 * Un DNI que no valida se rechaza con su mensaje.
	 */
	public function test_un_dni_invalido_se_rechaza(): void {
		$errores = $this->rechazo( $this->solicitud( array( 'dni' => '12345678A' ) ) );

		$this->assertMatchesRegularExpression( '/DNI\/NIE no es válido/i', implode( ' | ', $errores ) );
	}

	/**
	 * **Un DNI con espacios o guiones se normaliza** antes de validar y de comparar.
	 *
	 * La gente escribe «00000000-T» o «00000000 T». Si no se normalizara, el mismo DNI pasaría dos veces y además
	 * una persona con su DNI bien escrito sería rechazada.
	 */
	public function test_el_dni_con_guiones_o_espacios_se_normaliza(): void {
		try {
			$this->enviar( $this->solicitud( array( 'dni' => '00000000-T' ) ) );
		} catch ( \Throwable $e ) {
			if ( false !== strpos( $e->getMessage(), 'no es válido' ) ) {
				$this->fail( 'un DNI correcto con guion no debe rechazarse' );
			}
			// El resto del flujo puede fallar por los dobles; lo que se mide es la normalización.
		}

		$this->assertNotSame( array(), $GLOBALS['_wp_stores']['usuarios'], 'el alta siguió adelante' );
		$ids = array_keys( $GLOBALS['_wp_stores']['usuarios'] );
		$id  = (int) end( $ids );
		$this->assertSame( '00000000T', $GLOBALS['_wp_stores']['user_meta'][ $id ]['_convoca_dni'] ?? '', 'guardado sin guion' );
	}

	/**
	 * Una fecha con formato raro se rechaza con su mensaje.
	 */
	public function test_una_fecha_mal_formada_se_rechaza(): void {
		$errores = $this->rechazo( $this->solicitud( array( 'fecha_nacimiento' => '01/01/1990' ) ) );

		$this->assertMatchesRegularExpression( '/fecha de nacimiento no es válida/i', implode( ' | ', $errores ) );
	}

	/**
	 * **Un correo ya registrado no crea otra cuenta.**
	 */
	public function test_un_correo_ya_registrado_se_rechaza(): void {
		$GLOBALS['_wp_stores']['usuarios'][55] = (object) array(
			'ID'         => 55,
			'user_email' => 'ada@example.test',
			'roles'      => array( 'suscriptor' ),
		);

		$errores = $this->rechazo( $this->solicitud() );

		$this->assertMatchesRegularExpression( '/correo ya está registrado/i', implode( ' | ', $errores ) );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['usuarios'], 'no se creó una segunda cuenta' );
	}

	/**
	 * **Un DNI ya registrado tampoco**, en la clave canónica.
	 *
	 * Es lo que evita tener a la misma persona dos veces: el correo puede ser distinto, el DNI no.
	 */
	public function test_un_dni_ya_registrado_se_rechaza(): void {
		$GLOBALS['_wp_stores']['usuarios'][55] = (object) array(
			'ID'         => 55,
			'user_email' => 'otra@example.test',
			'roles'      => array( 'suscriptor' ),
		);
		$GLOBALS['_wp_stores']['user_meta'][55] = array( '_convoca_dni' => '00000000T' );

		$errores = $this->rechazo( $this->solicitud() );

		$this->assertMatchesRegularExpression( '/DNI ya está registrado/i', implode( ' | ', $errores ) );
		$this->assertCount( 1, $GLOBALS['_wp_stores']['usuarios'], 'no se creó una segunda cuenta' );
	}

	/**
	 * **Y en la clave heredada** `_convoca_shifts_dni`: durante la migración conviven las dos, y mirar solo una deja
	 * pasar a quien tiene el DNI en la vieja.
	 */
	public function test_un_dni_en_la_clave_heredada_tambien_se_detecta(): void {
		$GLOBALS['_wp_stores']['usuarios'][55] = (object) array(
			'ID'         => 55,
			'user_email' => 'otra@example.test',
			'roles'      => array( 'suscriptor' ),
		);
		$GLOBALS['_wp_stores']['user_meta'][55] = array( '_convoca_shifts_dni' => '00000000T' );

		$errores = $this->rechazo( $this->solicitud() );

		$this->assertMatchesRegularExpression( '/DNI ya está registrado/i', implode( ' | ', $errores ) );
	}

	/**
	 * Un campo dinámico obligatorio se exige con **su** etiqueta.
	 */
	public function test_un_campo_dinamico_obligatorio_se_exige(): void {
		$GLOBALS['_wp_stores']['options']['convoca_volunteer_fields'] = array(
			array(
				'name'     => 'talla',
				'label'    => 'Talla de camiseta',
				'required' => 1,
			),
		);

		$errores = $this->rechazo( $this->solicitud() );

		$this->assertMatchesRegularExpression( '/Talla de camiseta/', implode( ' | ', $errores ) );
	}

	/**
	 * Y si se rellena, se guarda con el prefijo del producto.
	 */
	public function test_un_campo_dinamico_relleno_se_guarda(): void {
		$GLOBALS['_wp_stores']['options']['convoca_volunteer_fields'] = array(
			array(
				'name'     => 'talla',
				'label'    => 'Talla de camiseta',
				'required' => 1,
			),
		);

		try {
			$this->enviar( $this->solicitud( array( 'dyn_talla' => 'L' ) ) );
		} catch ( \Throwable $e ) {
			// La respuesta final lanza.
		}

		$ids = array_keys( $GLOBALS['_wp_stores']['usuarios'] );
		$id  = (int) end( $ids );
		$this->assertSame( 'L', $GLOBALS['_wp_stores']['user_meta'][ $id ]['_convoca_voluntario_talla'] ?? '' );
	}

	/**
	 * **El camino feliz**: cuenta de WordPress, ficha de socio enlazada y el aviso a los administradores.
	 *
	 * La ficha es lo que le da acceso al certificado y a que sus horas se vinculen; sin ella el voluntario existe pero
	 * no puede hacer nada.
	 */
	public function test_el_camino_feliz_crea_cuenta_ficha_y_avisa(): void {
		try {
			$this->enviar( $this->solicitud() );
		} catch ( \RuntimeException $e ) {
			if ( false !== strpos( $e->getMessage(), 'wp_send_json_error' ) ) {
				$this->fail( 'una solicitud válida no debe rechazarse: ' . implode( ' | ', $this->errores() ) );
			}
			// `wp_send_json_success` también lanza en el doble.
		}

		$this->assertCount( 1, $GLOBALS['_wp_stores']['usuarios'], 'se creó la cuenta' );
		$this->assertNotEmpty( $GLOBALS['_wp_stores']['posts'], 'y la ficha de socio' );

		$ficha = null;
		foreach ( $GLOBALS['_wp_stores']['posts'] as $p ) {
			if ( 'miembro' === ( $p->post_type ?? '' ) ) {
				$ficha = $p;
			}
		}
		$this->assertNotNull( $ficha, 'hay ficha de tipo miembro' );
		$this->assertSame( 'Ada Lovelace', $ficha->post_title );

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $ficha->ID ] ?? array();
		$this->assertSame( 'ada@example.test', $meta['_convoca_email'] ?? '' );
		$this->assertSame( '00000000T', $meta['_convoca_dni'] ?? '' );
		$this->assertSame( 'pendiente_documentacion', $meta['_convoca_estado_miembro'] ?? '', 'entra pendiente' );
		$this->assertNotEmpty( $meta['_convoca_access_code'] ?? '', 'con código de acceso' );

		$ids = array_keys( $GLOBALS['_wp_stores']['usuarios'] );
		$id  = (int) end( $ids );
		$this->assertSame( (string) $ficha->ID, (string) ( $GLOBALS['_wp_stores']['user_meta'][ $id ]['_convoca_member_id'] ?? '' ), 'usuario y ficha enlazados' );
		$this->assertSame( '0', (string) ( $GLOBALS['_wp_stores']['user_meta'][ $id ]['_convoca_voluntario_aprobado'] ?? '' ), 'pendiente de aprobación' );

		$this->assertContains( 'convoca_voluntario_pendiente', array_column( (array) $GLOBALS['_wp_actions'], 'hook' ), 'avisa a quien corresponda' );
	}

	/**
	 * **El consentimiento se guarda con su versión y su fecha.**
	 *
	 * Es lo que hay que poder enseñar si alguien pregunta cuándo aceptó qué.
	 */
	public function test_el_consentimiento_se_guarda_con_version_y_fecha(): void {
		$GLOBALS['_wp_stores']['options']['convoca_members_settings'] = array( 'rgpd_version' => '2.1' );

		try {
			$this->enviar( $this->solicitud() );
		} catch ( \Throwable $e ) {
			// La respuesta final lanza.
		}

		$ids = array_keys( $GLOBALS['_wp_stores']['usuarios'] );
		$id  = (int) end( $ids );
		$meta = $GLOBALS['_wp_stores']['user_meta'][ $id ] ?? array();

		$this->assertSame( '2.1', $meta['_convoca_voluntario_rgpd_version'] ?? '', 'la versión de la política aceptada' );
		$this->assertNotEmpty( $meta['_convoca_voluntario_rgpd_timestamp'] ?? '', 'y cuándo' );
		$this->assertSame( '1', $meta['_convoca_voluntario_declaracion_responsable'] ?? '' );
	}

	/**
	 * Sin marcar comunicaciones queda `0`, no vacío: es lo que se consulta para decidir si se puede escribir.
	 */
	public function test_sin_comunicaciones_queda_un_cero_explicito(): void {
		try {
			$this->enviar( $this->solicitud() );
		} catch ( \Throwable $e ) {
			// La respuesta final lanza.
		}

		$ids = array_keys( $GLOBALS['_wp_stores']['usuarios'] );
		$id  = (int) end( $ids );
		$this->assertSame( '0', (string) ( $GLOBALS['_wp_stores']['user_meta'][ $id ]['_convoca_voluntario_comunicaciones_ok'] ?? '' ) );
	}
}
