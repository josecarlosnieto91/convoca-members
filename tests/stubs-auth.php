<?php
/**
 * Dobles de la autenticación de WordPress y captura de cookies para los tests de Members.
 *
 * `Member_Auth` no existía probado en su parte de sesión: `login()`, `logout()` y las lecturas de sesión
 * estaban a cero porque el arnés no sabía autenticar a nadie ni ver una cookie. Sin esto, lo único que se
 * podía afirmar es que el método existe.
 *
 * La cookie se captura con una función **en el espacio de nombres del plugin**: PHP resuelve primero
 * `Convoca\Members\setcookie()` y sólo después la global, así que esta envuelve la llamada real del código sin
 * tocar `setcookie()` de PHP (que en CLI no puede mandar cabeceras).
 *
 * @package Convoca\Members\Tests
 */

namespace Convoca\Members {

	if ( ! function_exists( 'Convoca\Members\setcookie' ) ) {
		/**
		 * Captura la cookie que manda el plugin.
		 *
		 * @param string $nombre  Nombre.
		 * @param string $valor   Valor.
		 * @param array  $opciones Opciones (expires, path, domain, secure, httponly, samesite).
		 * @return bool
		 */
		function setcookie( $nombre, $valor = '', $opciones = array() ) {
			$GLOBALS['_wp_cookies'][] = array(
				'nombre'   => (string) $nombre,
				'valor'    => (string) $valor,
				'opciones' => (array) $opciones,
			);

			// Como la cookie de verdad: queda disponible para quien la lea después.
			if ( '' === (string) $valor ) {
				unset( $_COOKIE[ $nombre ] );
			} else {
				$_COOKIE[ $nombre ] = (string) $valor;
			}

			return true;
		}
	}
}

namespace {

	// Constantes de cookie de WordPress: `Member_Auth` las usa al mandar la sesión.
	if ( ! \defined( 'COOKIEPATH' ) ) {
		\define( 'COOKIEPATH', '/' );
	}
	if ( ! \defined( 'COOKIE_DOMAIN' ) ) {
		\define( 'COOKIE_DOMAIN', '' );
	}

	// Caché de objetos: la usa el limitador de intentos del núcleo. Cada función con su propia guarda:
	// el bootstrap del núcleo define algunas (y no todas), y agruparlas revienta por redeclaración.
	if ( ! \function_exists( 'wp_cache_get' ) ) {
		/**
		 * Lee de la caché.
		 *
		 * @param string $clave Clave.
		 * @param string $grupo Grupo.
		 * @return mixed
		 */
		function wp_cache_get( $clave, $grupo = '' ) {
			return $GLOBALS['_wp_cache'][ $grupo ][ $clave ] ?? false;
		}
	}

	if ( ! \function_exists( 'wp_cache_set' ) ) {
		/**
		 * Guarda en la caché.
		 *
		 * @param string $clave  Clave.
		 * @param mixed  $valor  Valor.
		 * @param string $grupo  Grupo.
		 * @param int    $expira Caducidad.
		 * @return bool
		 */
		function wp_cache_set( $clave, $valor, $grupo = '', $expira = 0 ) {
			$GLOBALS['_wp_cache'][ $grupo ][ $clave ] = $valor;

			return true;
		}
	}

	if ( ! \function_exists( 'wp_salt' ) ) {
		/**
		 * Sal de firma (doble).
		 *
		 * @param string $scheme Esquema.
		 * @return string
		 */
		function wp_salt( $scheme = 'auth' ) {
			return 'sal-de-pruebas-' . $scheme;
		}
	}

	if ( ! \function_exists( 'wp_cache_delete' ) ) {
		/**
		 * Borra de la caché.
		 *
		 * @param string $clave Clave.
		 * @param string $grupo Grupo.
		 * @return bool
		 */
		function wp_cache_delete( $clave, $grupo = '' ) {
			unset( $GLOBALS['_wp_cache'][ $grupo ][ $clave ] );

			return true;
		}
	}

	if ( ! \function_exists( 'wp_cache_incr' ) ) {
		/**
		 * Suma a un contador de la caché.
		 *
		 * @param string $clave Clave.
		 * @param int    $n     Cuánto.
		 * @param string $grupo Grupo.
		 * @return int
		 */
		function wp_cache_incr( $clave, $n = 1, $grupo = '' ) {
			$GLOBALS['_wp_cache'][ $grupo ][ $clave ] = (int) ( $GLOBALS['_wp_cache'][ $grupo ][ $clave ] ?? 0 ) + (int) $n;

			return (int) $GLOBALS['_wp_cache'][ $grupo ][ $clave ];
		}
	}

	if ( ! \function_exists( 'wp_authenticate' ) ) {
		/**
		 * Autentica contra WordPress.
		 *
		 * La prueba decide el resultado con `$GLOBALS['_wp_auth']`: un objeto usuario o un `WP_Error`.
		 *
		 * @param string $usuario  Usuario.
		 * @param string $password Contraseña.
		 * @return mixed
		 */
		function wp_authenticate( $usuario, $password = '' ) {
			$GLOBALS['_wp_auth_intentos'][] = array( $usuario, $password );

			return $GLOBALS['_wp_auth'] ?? new \WP_Error( 'invalid', 'sin definir' );
		}
	}

	if ( ! \function_exists( 'wp_set_current_user' ) ) {
		/**
		 * Fija el usuario actual.
		 *
		 * @param int $id Usuario.
		 */
		function wp_set_current_user( $id ) {
			$GLOBALS['_wp_usuario_actual'] = (int) $id;

			return new \stdClass();
		}
	}

	if ( ! \function_exists( 'wp_set_auth_cookie' ) ) {
		/**
		 * Abre la sesión de WordPress.
		 *
		 * @param int  $id      Usuario.
		 * @param bool $recordar Recordar.
		 * @param bool $segura   Segura.
		 */
		function wp_set_auth_cookie( $id, $recordar = false, $segura = false ) {
			$GLOBALS['_wp_auth_cookies'][] = array(
				'usuario' => (int) $id,
				'recordar' => (bool) $recordar,
				'segura' => (bool) $segura,
			);
		}
	}

	if ( ! \function_exists( 'maybe_unserialize' ) ) {
		/**
		 * Deserializa si hace falta.
		 *
		 * @param mixed $valor Valor.
		 * @return mixed
		 */
		function maybe_unserialize( $valor ) {
			if ( \is_string( $valor ) && \preg_match( '/^(a|O|s|i|d|b):/', $valor ) ) {
				$deserializado = @\unserialize( $valor ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				if ( false !== $deserializado || 'b:0;' === $valor ) {
					return $deserializado;
				}
			}

			return $valor;
		}
	}

	if ( ! \function_exists( 'sanitize_user' ) ) {
		/**
		 * Limpia un nombre de usuario.
		 *
		 * @param string $usuario Usuario.
		 * @param bool   $estricto Estricto.
		 * @return string
		 */
		function sanitize_user( $usuario, $estricto = false ) {
			$limpio = \preg_replace( '/[^a-zA-Z0-9 _.\-@]/', '', (string) $usuario );

			return \trim( (string) $limpio );
		}
	}
}
