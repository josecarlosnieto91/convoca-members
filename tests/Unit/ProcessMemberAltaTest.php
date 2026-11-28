<?php
/**
 * El alta de un socio: del formulario a la ficha.
 *
 * Es la puerta por la que entra todo el mundo, y donde un fallo se paga doble. Si el alta queda `activo` sin
 * pagar, el socio tiene beneficios gratis; si el deduplicador falla, dos personas comparten ficha; y si el DNI se
 * guarda con espacios, la siguiente búsqueda por DNI no encuentra nada.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use Convoca\Members\CPT_Miembro;
use Convoca\Members\Process_Member;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Miembro.php';
require_once dirname( __DIR__, 2 ) . '/includes/GDPR_Tools.php';
require_once dirname( __DIR__, 2 ) . '/includes/Process_Member.php';

/**
 * Alta de socio.
 */
final class ProcessMemberAltaTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->limpiar();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$this->limpiar();
	}

	/**
	 * Deja el arnés como estaba.
	 */
	private function limpiar(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['_test_posts'], $GLOBALS['_wp_actions'], $GLOBALS['_wp_stub_next_id'] );

		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'user_meta'  => array(),
			'emails'     => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'locks'      => array(),
			'db_inserts' => array(),
		);
		remove_all_filters( 'convoca_members_plans' );
	}

	/**
	 * Un alta válida.
	 *
	 * @param array $extra Campos que se añaden o pisan.
	 * @return array
	 */
	private function alta( array $extra = array() ): array {
		return array_merge(
			array(
				'nombre'           => 'Ada Lovelace',
				'dni'              => '00000000 t',
				'email'            => 'ada@example.test',
				'fecha_nacimiento' => '1990-01-01',
				'telefono'         => '600000000',
				'direccion'        => 'Calle de prueba 1',
				'municipio'        => 'Oviedo',
				'plan'             => 'bronze',
				'forma_pago'       => 'transferencia',
				'rgpd'             => '1',
				'tareas'           => 'Nada',
			),
			$extra
		);
	}

	/**
	 * Dos altas simultáneas de la misma persona se serializan (#134).
	 */
	public function test_el_alta_simultanea_no_crea_dos_fichas(): void {
		$lock = 'convoca_alta_' . md5( '00000000T|ada@example.test' );
		$this->assertNotFalse( \Convoca\Core\Utils::acquire_lock( $lock, 60 ) );

		$resultado = Process_Member::register( $this->alta() );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'busy', $resultado->get_error_code() );
		$this->assertSame( 409, $resultado->get_error_data()['status'] );
		$this->assertSame( array(), $GLOBALS['_wp_stores']['posts'], 'no se creó ninguna ficha' );

		\Convoca\Core\Utils::release_lock( $lock );
	}

	/**
	 * El cerrojo se libera al terminar, para que el reintento sea posible.
	 */
	public function test_el_cerrojo_del_alta_se_libera(): void {
		$resultado = Process_Member::register( $this->alta() );
		$this->assertIsArray( $resultado );

		$lock = 'convoca_alta_' . md5( '00000000T|ada@example.test' );
		$this->assertNotFalse( \Convoca\Core\Utils::acquire_lock( $lock, 5 ), 'el alta dejó el cerrojo tomado' );
		\Convoca\Core\Utils::release_lock( $lock );
	}

	/**
	 * Sin nombre ni DNI no se crea ficha (#23).
	 */
	public function test_el_alta_rechaza_nombre_y_dni_vacios(): void {
		$resultado = Process_Member::register( $this->alta( array( 'nombre' => '', 'dni' => '' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'validation_error', $resultado->get_error_code() );
	}

	/**
	 * Familiar/juvenil guarda la clave del subplan que se cobra, no la modalidad (#115).
	 */
	public function test_familiar_guarda_el_subplan(): void {
		$resultado = Process_Member::register(
			$this->alta(
				array(
					'plan'     => 'familiar',
					'sub_plan' => 'fam-bronze',
				)
			)
		);

		$this->assertIsArray( $resultado );
		$id = (int) $resultado['id'];
		$this->assertSame( 'fam-bronze', (string) get_post_meta( $id, '_convoca_plan', true ) );
		$this->assertSame( 'familiar', (string) get_post_meta( $id, '_convoca_plan_modalidad', true ) );
	}

	/**
	 * El alta deja su asiento inicial y la fecha de pendiente de pago (#133).
	 */
	public function test_el_alta_deja_historial_y_fecha_pendiente(): void {
		$resultado = Process_Member::register( $this->alta() );
		$this->assertIsArray( $resultado );
		$id = (int) $resultado['id'];

		$historial = \Convoca\Members\Estados::get_history( $id );
		$this->assertCount( 1, $historial );
		$this->assertSame( '', $historial[0]['de'], 'primer estado: sin anterior' );
		$this->assertSame( 'pendiente_pago', $historial[0]['a'] );
		$this->assertStringContainsString( 'Registro inicial', $historial[0]['nota'] );
		$this->assertNotSame( '', (string) get_post_meta( $id, '_convoca_fecha_pendiente_pago', true ) );
	}

	/**
	 * La ficha creada por un alta.
	 *
	 * @param array $resultado Resultado del alta.
	 * @return object|null
	 */
	private function ficha( array $resultado ): ?object {
		$id = (int) ( $resultado['id'] ?? 0 );

		return $id > 0 ? ( $GLOBALS['_wp_stores']['posts'][ $id ] ?? null ) : null;
	}

	/**
	 * El alta básica: ficha creada, datos normalizados y estado **pendiente de pago**.
	 */
	public function test_el_alta_crea_la_ficha(): void {
		$resultado = Process_Member::register( $this->alta() );

		$this->assertIsArray( $resultado, 'sin error' );
		$this->assertSame( 'Ada Lovelace', $resultado['nombre'] );
		$this->assertSame( 'pendiente_pago', $resultado['estado'], 'nadie entra siendo socio sin pagar' );

		$id = (int) $resultado['id'];
		$this->assertGreaterThan( 0, $id );
		$this->assertSame( 'miembro', (string) get_post( $id )->post_type, 'se crea una ficha de socio' );
		$this->assertSame( 'Ada Lovelace', (string) get_post( $id )->post_title );
		// Publicada, no en borrador: el deduplicador busca fichas `publish` (un borrador no se encontraría y
		// dejaría pasar un duplicado) y en el panel no se vería.
		$this->assertSame( 'publish', (string) get_post( $id )->post_status );

		// El DNI se normaliza: en mayúsculas y sin espacios ni guiones (si no, no casa en las búsquedas).
		$this->assertSame( '00000000T', (string) \Convoca\Members\Datos_Socio::leer_meta( $id, 'dni' ) );
		$this->assertSame( 'ada@example.test', (string) get_post_meta( $id, '_convoca_email', true ) );
		$this->assertSame( 'Ada Lovelace', (string) get_post_meta( $id, '_convoca_nombre', true ), 'el nombre también vive en su meta, para el buscador' );
		$this->assertSame( 'bronze', (string) get_post_meta( $id, '_convoca_plan', true ) );
		// Ojo con los nombres, que se cruzan: `forma_pago` es **qué** se paga (cuota o voluntariado) y el método
		// elegido en el alta va en `metodo_pago`. Confundirlos deja la ficha diciendo que paga «transferencia»
		// cuando lo que toca es una cuota.
		$this->assertSame( 'cuota', (string) get_post_meta( $id, '_convoca_forma_pago', true ) );
		$this->assertSame( 'transferencia', (string) get_post_meta( $id, '_convoca_metodo_pago', true ) );
		$this->assertSame( 'pendiente', (string) get_post_meta( $id, '_convoca_estado_cuota', true ), 'la cuota nace pendiente' );
		$this->assertSame( '30', (string) get_post_meta( $id, '_convoca_importe_cuota', true ), 'con el precio de su plan' );
		$this->assertNotSame( '', (string) get_post_meta( $id, '_convoca_access_code', true ), 'con su código de acceso' );
		$this->assertSame( '0', (string) get_post_meta( $id, '_convoca_menor_edad', true ) );
		$this->assertNotSame( '', (string) get_post_meta( $id, '_convoca_rgpd_timestamp', true ), 'con el consentimiento fechado' );

		// Los datos de contacto y de sitio: sin ellos no se puede avisar al socio ni agruparlo por municipio.
		$this->assertSame( '600000000', (string) get_post_meta( $id, '_convoca_telefono', true ) );
		$this->assertSame( 'Oviedo', (string) get_post_meta( $id, '_convoca_municipio', true ) );
		$this->assertSame( 'Calle de prueba 1', (string) \Convoca\Members\Datos_Socio::leer_meta( $id, 'direccion' ) );
		// Y el alias heredado del plan, que todavía leen trozos antiguos del plugin.
		$this->assertSame( 'bronze', (string) get_post_meta( $id, '_convoca_cuota', true ), 'alias heredado de la cuota' );
	}

	/**
	 * Los consentimientos quedan **con su versión**: son la prueba de qué aceptó el socio y con qué texto.
	 *
	 * Y el canal de contacto: por dónde se le puede escribir. Si se pierde, el alta existe pero nadie sabe cómo
	 * localizar a esa persona.
	 */
	public function test_los_consentimientos_quedan_fechados(): void {
		update_option( 'convoca_members_settings', array( 'rgpd_version' => '2026-1' ) );

		$resultado = Process_Member::register(
			$this->alta(
				array(
					'canal_contacto'        => 'telefono',
					'comunicaciones'        => '1',
					'acuerdo_voluntariado'  => '1',
				)
			)
		);
		$id        = (int) $resultado['id'];

		$this->assertSame( 'telefono', (string) get_post_meta( $id, '_convoca_canal_contacto', true ), 'por dónde se le escribe' );
		$this->assertSame( '1', (string) get_post_meta( $id, '_convoca_comunicaciones_ok', true ), 'acepta comunicaciones' );
		$this->assertSame( '1', (string) get_post_meta( $id, '_convoca_es_voluntario', true ), 'quiere ser voluntariado' );
		$this->assertSame(
			'2026-1',
			(string) get_post_meta( $id, '_convoca_rgpd_version', true ),
			'la versión de la política que aceptó: eso es la prueba'
		);
	}

	/**
	 * Y avisa de que hay un alta y de que hay que mandar la solicitud.
	 */
	public function test_el_alta_avisa(): void {
		Process_Member::register( $this->alta() );

		$hooks = array_column( $GLOBALS['_wp_actions'] ?? array(), 'hook' );
		$this->assertContains( 'convoca_members_created', $hooks, 'el ecosistema sabe que hay un socio nuevo' );
		$this->assertContains( 'convoca_members_email_solicitud', $hooks, 'y se manda la solicitud' );
	}

	/**
	 * Un menor de edad entra marcado y con los datos del tutor: sin ellos no se puede tratar con él.
	 */
	public function test_un_menor_queda_marcado(): void {
		$resultado = Process_Member::register(
			$this->alta(
				array(
					'fecha_nacimiento' => gmdate( 'Y-m-d', strtotime( '-15 years' ) ),
					'tutor_nombre'     => 'Tutora Legal',
					'tutor_dni'        => '11111111H',
				)
			)
		);

		$id = (int) $resultado['id'];
		$this->assertSame( '1', (string) get_post_meta( $id, '_convoca_menor_edad', true ), 'queda marcado como menor' );
		$this->assertSame( 'Tutora Legal', (string) get_post_meta( $id, '_convoca_tutor_nombre', true ) );
		$this->assertSame( '1', (string) get_post_meta( $id, '_convoca_tutor_autorizacion', true ) );
	}

	/**
	 * Un DNI o un correo que ya están en otra ficha se rechazan **y no se crea nada**.
	 */
	public function test_el_duplicado_se_rechaza(): void {
		// El deduplicador consulta por SQL propio: se le hace devolver una ficha existente.
		$GLOBALS['_wp_stores']['get_var'] = '77';

		$resultado = Process_Member::register( $this->alta() );

		$this->assertInstanceOf( \WP_Error::class, $resultado, 'no se puede duplicar' );
		$this->assertSame(
			0,
			count(
				array_filter(
					$GLOBALS['_wp_stores']['posts'] ?? array(),
					static fn( $p ) => 'miembro' === ( $p->post_type ?? '' )
				)
			),
			'no se creó ninguna ficha'
		);
	}

	/**
	 * Un plan que no existe no se acepta.
	 */
	public function test_un_plan_inventado(): void {
		$resultado = Process_Member::register( $this->alta( array( 'plan' => 'plan-de-la-luna' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'invalid_plan', $resultado->get_error_code() );
	}

	/**
	 * Un plan desactivado desde Ajustes tampoco: existe, pero no se ofrece.
	 */
	public function test_un_plan_desactivado(): void {
		add_filter(
			'convoca_members_plans',
			static function ( $planes ) {
				$planes['bronze']['active'] = false;

				return $planes;
			}
		);

		$resultado = Process_Member::register( $this->alta() );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'inactive_plan', $resultado->get_error_code() );
	}

	/**
	 * Los datos que no pasan la validación se devuelven tal cual, sin crear ficha.
	 */
	public function test_no_pasa_la_validacion(): void {
		$resultado = Process_Member::register( $this->alta( array( 'nombre' => '   ', 'dni' => '' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertStringContainsString( 'El nombre es obligatorio', $resultado->get_error_message() );
		$this->assertEmpty( $GLOBALS['_wp_stores']['posts'] ?? array() );
	}
}
