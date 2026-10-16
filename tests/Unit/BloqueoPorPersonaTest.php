<?php
/**
 * Los intentos se cuentan por PERSONA y la junta puede levantar el bloqueo (members#46).
 *
 * MEDIDO EN LA DEMO (informe del issue): el contador era SOLO por dirección IP. Varios errores de UNA persona
 * dejaban fuera a todas las demás de su misma conexión durante una hora —una sede con una línea, un
 * telecentro, una familia entrando por el mismo router— y el siguiente leía «Demasiados intentos», sin saber
 * que no era culpa suya. Y para desbloquear había que tocar la base de datos a mano
 * (`convoca_rl_alta_socio_<hash>`): quien administra no tenía ningún botón.
 *
 * Lo que fija esta prueba:
 *  · el alta y el voluntariado cuentan por el **correo** de quien lo intenta;
 *  · el acceso cuenta por **usuario + conexión** (así las erratas de una persona no cierran la puerta a la
 *    sede, y nadie puede dejar fuera la cuenta de otro probando desde su propia conexión);
 *  · el aviso dice qué hacer y a quién escribir, en vez de «inténtalo en una hora»;
 *  · y el panel tiene la pestaña con quién está cortado y el botón para levantarlo, con nonce y permiso.
 *
 * El limitador se prueba **en vivo** (no aquí): su contador vive en `wp_options` con SQL directo
 * (`INSERT ... ON DUPLICATE KEY`) y el arnés unitario no tiene base de datos. La medición está en el issue:
 * una persona agotando sus intentos y otra, desde la MISMA conexión, entrando sin problema.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class BloqueoPorPersonaTest extends TestCase {

	private function leer( string $rel ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	}

	/** El alta cuenta por el correo de quien lo intenta, no solo por su conexión. */
	public function test_el_alta_cuenta_por_persona(): void {
		$alta = $this->leer( 'public/class-form-handler.php' );

		$this->assertStringContainsString( "\$email_intento  = sanitize_email(", $alta );
		$this->assertStringContainsString( "check_rate_limit( 'alta_socio', 3, 3600, \$email_intento )", $alta, 'El REST pasa el correo' );
		$this->assertStringContainsString( "check_rate_limit( 'alta_socio', 3, 3600, sanitize_email( (string) ( \$_POST['email'] ?? '' ) ) )", $alta, 'Y el camino antiguo también' );
	}

	/** El voluntariado, igual. */
	public function test_el_voluntariado_cuenta_por_persona(): void {
		$vol = $this->leer( 'public/class-form-voluntariado.php' );

		$this->assertStringContainsString( "check_rate_limit( 'convoca_voluntariado_submit', 3, 3600, sanitize_email(", $vol );
	}

	/** El acceso, por usuario + conexión (ni la sede entera fuera, ni la cuenta de otro bloqueable). */
	public function test_el_acceso_cuenta_por_usuario_y_conexion(): void {
		$auth = $this->leer( 'includes/Member_Auth.php' );

		$this->assertStringContainsString( '$identidad_intento = $username', $auth );
		$this->assertStringContainsString( "REMOTE_ADDR", $auth );
		$this->assertStringContainsString( "check_rate_limit( 'login', self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCKOUT_SECONDS, \$identidad_intento )", $auth );
	}

	/** El aviso dice qué hacer y a quién escribir; no deja a la persona sin salida. */
	public function test_el_aviso_dice_que_hacer(): void {
		$alta = $this->leer( 'public/class-form-handler.php' );

		$this->assertStringContainsString( 'mensaje_bloqueo()', $alta, 'Los dos caminos del alta usan el mismo aviso' );
		$this->assertStringContainsString( 'Se han hecho varios intentos de registro desde tu conexión', $alta );
		$this->assertStringContainsString( 'Si no eras tú, escríbenos a %s', $alta, 'Con el correo de contacto' );
		$this->assertStringNotContainsString( "__( 'Demasiados intentos de registro. Inténtalo de nuevo en una hora.', 'convoca-members' )", $alta, 'El aviso viejo ya no se usa (el texto se cita en la documentación, pero no en el código)' );
	}

	/** El núcleo cuenta por persona y deja el tope de conexión mucho más alto. */
	public function test_el_nucleo_cuenta_por_persona_y_con_tope_alto(): void {
		$nucleo = (string) file_get_contents( dirname( __DIR__, 2 ) . '/../convoca-core/includes/Utils.php' );

		$this->assertStringContainsString( 'string $identity = ', $nucleo, 'El limitador acepta quién lo intenta' );
		$this->assertStringContainsString( "'convoca_rl_' . \$action . '_id_' . md5( \$quien )", $nucleo, 'Contador por persona' );
		$this->assertStringContainsString( "'convoca_rl_' . \$action . '_ip_' . md5( \$ip )", $nucleo, 'Y por conexión' );
		$this->assertStringContainsString( '$tope_conexion = max( $max * 5, $max + 20 );', $nucleo, 'El tope de conexión es más alto a propósito' );
	}

	/**
	 * El botón devuelve a la pantalla de verdad.
	 *
	 * DEFECTO PROPIO, medido en Lugg y en la demo (16/10/2026): la redirección apuntaba a
	 * `page=convoca-members-settings` y el slug real de la pantalla es `conv-members-settings`, así que al
	 * pulsar «Desbloquear» el bloqueo SÍ se levantaba y acto seguido salía un 403 («No tienes permiso para
	 * acceder a esta página»). Esta prueba compara los dos slugs: si se separan, vuelve el mismo error.
	 */
	public function test_el_boton_vuelve_a_la_pantalla_de_verdad(): void {
		$ajustes = $this->leer( 'admin/class-admin-settings.php' );

		preg_match_all( '/page=([a-z-]*members-settings)/', $ajustes, $enlaces );
		$this->assertNotEmpty( $enlaces[1], 'No se han encontrado los enlaces de las pestañas' );

		preg_match( "/'page'\s*=>\s*'([^']+)'/", $ajustes, $destino );
		$this->assertNotEmpty( $destino[1], 'No se ha encontrado la redirección del desbloqueo' );

		$this->assertContains(
			$destino[1],
			array_unique( $enlaces[1] ),
			"La redirección del botón ($destino[1]) tiene que llevar a la misma pantalla que las pestañas ("
				. implode( ', ', array_unique( $enlaces[1] ) ) . '): si no, el botón termina en un 403.'
		);
	}

	/** La junta lo ve y lo levanta desde el panel, sin tocar la base de datos. */
	public function test_la_junta_lo_ve_y_lo_levanta(): void {
		$ajustes = $this->leer( 'admin/class-admin-settings.php' );

		$this->assertStringContainsString( "tab=seguridad", $ajustes, 'Pestaña propia' );
		$this->assertStringContainsString( 'render_seguridad_tab()', $ajustes, 'Con su contenido' );
		$this->assertStringContainsString( 'rate_limit_blocks()', $ajustes, 'Que lista quién está cortado' );
		$this->assertStringContainsString( "'Desbloquear'", $ajustes, 'Con su botón' );
		$this->assertStringContainsString( 'rate_limit_clear( $clave )', $ajustes, 'Que llama al núcleo para levantarlo' );
		$this->assertStringContainsString( "check_admin_referer( 'convoca_desbloquear_intentos' )", $ajustes, 'Con nonce' );
		$this->assertStringContainsString( "current_user_can( 'manage_options' )", $ajustes, 'Y con permiso' );
	}
}
