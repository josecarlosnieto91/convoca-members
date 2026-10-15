<?php
/**
 * Un editor no puede ver ni borrar el censo de socios (issue #15).
 *
 * Lo medido por la auditoría: con rol `editor` —sin ninguna capacidad de este plugin— `edit.php?post_type=miembro`
 * respondía 200 con el listado de socios por nombre y apellidos, y los enlaces de papelera funcionaban. La causa
 * era que `miembro`, `registro_hora` y `proyecto` se registraban sin tipo de capacidad propio y WordPress
 * traducía sus capacidades a las primitivas de `post`, que el editor tiene por defecto.
 *
 * Lo que esta prueba puede demostrar: que cada tipo declara su **propio** par de capacidades, que las trece
 * capacidades derivadas se conceden y que ya no hay ningún tipo colgando de `post`. Lo que **no** puede —el
 * arnés no es WordPress— es resolver `user_can()`: eso se mide en la demo, con un editor de verdad y un
 * administrador de verdad, y está en la evidencia del issue. Aquí se vigila el mecanismo, que es lo que se
 * rompe en silencio.
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;

class CapacidadesSociosTest extends TestCase {

	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Los pares singular/plural declarados en los tipos del plugin, leídos del código.
	 *
	 * @return array<int, array{0:string,1:string}>
	 */
	private function familias(): array {
		$familias = array();
		foreach ( array( 'includes/CPT_Miembro.php', 'includes/CPT_Proyecto.php', 'includes/CPT_Registro_Hora.php' ) as $ruta ) {
			$codigo = (string) file_get_contents( $this->raiz() . '/' . $ruta );
			if ( preg_match( "/'capability_type'\s*=>\s*array\(\s*'(convoca_\w+)',\s*'(\w+)'/", $codigo, $m ) ) {
				$familias[] = array( $m[1], $m[2] );
			}
		}

		return $familias;
	}

	/**
	 * Las trece capacidades que WordPress compone de un par singular/plural.
	 *
	 * @param array{0:string,1:string} $par Par singular/plural.
	 * @return array<int, string>
	 */
	private function derivadas( array $par ): array {
		list( $singular, $plural ) = $par;

		return array(
			"edit_$singular",
			"read_$singular",
			"delete_$singular",
			"edit_$plural",
			"edit_others_$plural",
			"delete_$plural",
			"publish_$plural",
			"read_private_$plural",
			"delete_private_$plural",
			"delete_published_$plural",
			"delete_others_$plural",
			"edit_private_$plural",
			"edit_published_$plural",
		);
	}

	public function test_los_tres_tipos_tienen_tipo_de_capacidad_propio(): void {
		$familias = $this->familias();
		$this->assertCount( 3, $familias, 'Socios, horas y proyectos: cada uno con su par propio' );
		foreach ( $familias as $par ) {
			$this->assertStringStartsWith( 'convoca_', $par[0] );
			$this->assertStringStartsWith( 'convoca_', $par[1] );
		}
	}

	public function test_ningun_tipo_cuelga_ya_de_las_capacidades_de_post(): void {
		foreach ( array( 'includes/CPT_Miembro.php', 'includes/CPT_Proyecto.php', 'includes/CPT_Registro_Hora.php' ) as $ruta ) {
			$codigo = (string) file_get_contents( $this->raiz() . '/' . $ruta );
			$this->assertStringNotContainsString( "'capability_type'     => 'post'", $codigo, "$ruta: `post` es lo que hacía que un editor pudiera listar y borrar socios" );
		}
	}

	public function test_se_conceden_las_trece_de_cada_familia(): void {
		$bootstrap = (string) file_get_contents( $this->raiz() . '/convoca-members.php' );
		$this->assertStringContainsString( 'function convoca_members_capacidades_de_los_tipos', $bootstrap );
		// Y en la función que corre en `init`, no en el gancho de activación: una instalación que se
		// actualiza no pasa por la activación, y ahí el administrador se quedaba fuera (403, medido).
		$roles = (string) file_get_contents( $this->raiz() . '/includes/Roles_Miembro.php' );
		$this->assertStringContainsString( 'foreach ( \\convoca_members_capacidades_de_los_tipos() as $cap_propia )', $roles, 'Las derivadas tienen que concederse, no solo declararse' );
		$this->assertStringContainsString( "add_action( 'init', array( \\Convoca\\Members\\Roles_Miembro::class, 'crear_roles' ) )", $bootstrap, 'Y en `init`: si va en la activación, la actualización no la ve' );
		$this->assertStringNotContainsString( 'as $cap_propia', $bootstrap, 'Y no en el gancho de activación, que una actualización no recorre' );

		// Se concede a administración y a nadie más: es la decisión de producto (solo admins).
		$bloque = substr( $bootstrap, (int) strpos( $bootstrap, 'convoca_members_capacidades_de_los_tipos() as $cap_propia' ), 200 );
		$this->assertStringNotContainsString( 'editor', $bloque );

		// Que el helper componga las trece, una por una: si falta una línea, hay una puerta abierta.
		$plantillas = array(
			'edit_$singular',
			'read_$singular',
			'delete_$singular',
			'edit_$plural',
			'edit_others_$plural',
			'delete_$plural',
			'publish_$plural',
			'read_private_$plural',
			'delete_private_$plural',
			'delete_published_$plural',
			'delete_others_$plural',
			'edit_private_$plural',
			'edit_published_$plural',
		);
		foreach ( $plantillas as $plantilla ) {
			$this->assertStringContainsString( '"' . $plantilla . '"', $bootstrap, "El helper no compone $plantilla" );
		}

		foreach ( $this->familias() as $par ) {
			// Y que derive del mismo par que declara el tipo, no de otro parecido.
			$this->assertStringContainsString( "'{$par[0]}', '{$par[1]}'", $bootstrap, 'El helper tiene que derivar del mismo par que el tipo' );
		}
	}
}
