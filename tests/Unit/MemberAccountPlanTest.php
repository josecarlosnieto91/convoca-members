<?php
/**
 * Fija la decisión de qué cuenta de WordPress se le da a un socio.
 *
 * El defecto que esto caza: si el correo del socio ya pertenecía a un usuario del sitio
 * (el propio administrador que se da de alta como socio, un monitor con su correo de
 * trabajo, o un socio al que se le vuelve a dar de alta), el alta intentaba crear OTRO
 * usuario, `wp_create_user` devolvía «Sorry, that email address is already used!», y el
 * reintento usaba el MISMO correo, así que volvía a fallar: el socio quedaba de alta pero
 * **sin cuenta** —no podía entrar en Mi Área— y el log acumulaba dos líneas de ERROR por
 * cada intento.
 *
 * @package ConvocaMembers
 */

use PHPUnit\Framework\TestCase;
use Convoca\Members\Process_Member;

/**
 * Decisión de cuenta de WordPress para un socio.
 */
class MemberAccountPlanTest extends TestCase {

	/**
	 * Si ya hay un usuario enlazado y existe, se reutiliza.
	 *
	 * @return void
	 */
	public function test_con_usuario_enlazado_se_reutiliza(): void {
		$this->assertSame(
			'reuse_linked',
			Process_Member::plan_user_account( 'socio@ejemplo.test', 42, true, 0 )
		);
	}

	/**
	 * El caso que fallaba: el correo ya es de un usuario del sitio.
	 *
	 * @return void
	 */
	public function test_si_el_correo_ya_tiene_usuario_se_enlaza_ese(): void {
		$this->assertSame(
			'link_by_email',
			Process_Member::plan_user_account( 'admin@ejemplo.test', 0, false, 7 ),
			'un correo que ya es de un usuario del sitio tiene que ENLAZARSE, no intentar crearse otra vez'
		);
	}

	/**
	 * El enlace guardado apunta a un usuario que ya no existe: no se reutiliza.
	 *
	 * @return void
	 */
	public function test_el_enlace_muerto_no_se_reutiliza(): void {
		$this->assertSame(
			'create',
			Process_Member::plan_user_account( 'nuevo@ejemplo.test', 99, false, 0 ),
			'un enlace a un usuario borrado no puede darse por bueno'
		);
	}

	/**
	 * Correo nuevo y sin enlace: se crea.
	 *
	 * @return void
	 */
	public function test_sin_nada_previo_se_crea(): void {
		$this->assertSame(
			'create',
			Process_Member::plan_user_account( 'nuevo@ejemplo.test', 0, false, 0 )
		);
	}
}
