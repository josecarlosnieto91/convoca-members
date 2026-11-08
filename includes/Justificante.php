<?php
/**
 * Subida del justificante de pago con enlace firmado de 3 días (members#86).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enlace de un solo uso con caducidad: el socio sube el justificante y la ficha cambia de estado.
 */
class Justificante {

	/**
	 * Shortcode público.
	 */
	public const SHORTCODE = 'convoca_justificante';

	/**
	 * Parámetro del token en la URL.
	 */
	public const PARAM = 'convoca_justificante';

	/**
	 * Acción del formulario.
	 */
	public const ACCION = 'convoca_justificante_subir';

	/**
	 * Vigencia del enlace: 3 días.
	 */
	public const VIDA = 259200;

	/**
	 * Opción con la página que aloja el shortcode.
	 */
	public const OPCION_PAGINA = 'convoca_members_justificante_page';

	/**
	 * Ruta de esa página.
	 */
	public const SLUG = 'subir-justificante';

	/**
	 * Engancha el shortcode y el proceso.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'admin_post_convoca_justificante_subir', array( __CLASS__, 'procesar' ) );
		add_action( 'admin_post_nopriv_convoca_justificante_subir', array( __CLASS__, 'procesar' ) );
	}

	/**
	 * Firma un enlace para un socio.
	 *
	 * @param int $member_id Socio.
	 * @return string Token.
	 */
	public static function token( int $member_id ): string {
		if ( $member_id <= 0 ) {
			return '';
		}

		$expira = time() + self::VIDA;

		return $expira . '.' . hash_hmac( 'sha256', $member_id . '|' . $expira, wp_salt( 'auth' ) );
	}

	/**
	 * Comprueba el token de un socio.
	 *
	 * @param int    $member_id Socio.
	 * @param string $token     Token recibido.
	 * @return bool
	 */
	public static function token_valido( int $member_id, string $token ): bool {
		$partes = explode( '.', $token );

		if ( 2 !== count( $partes ) ) {
			return false;
		}

		$expira = (int) $partes[0];

		if ( $expira < time() || $expira > time() + self::VIDA + 60 ) {
			return false;
		}

		return hash_equals( hash_hmac( 'sha256', $member_id . '|' . $expira, wp_salt( 'auth' ) ), (string) $partes[1] );
	}

	/**
	 * URL firmada para subir el justificante.
	 *
	 * @param int $member_id Socio.
	 * @return string
	 */
	public static function url( int $member_id ): string {
		$pagina = self::pagina();

		// Si el sitio no tiene la página, se crea ahora: el correo no puede prometer un enlace que no
		// existe y la persona no tiene otra forma de llegar (#102).
		if ( $pagina <= 0 ) {
			self::asegurar_pagina();
			$pagina = self::pagina();
		}

		if ( $pagina <= 0 ) {
			return '';
		}

		return add_query_arg(
			array(
				self::PARAM => self::token( $member_id ),
				'id'        => $member_id,
			),
			(string) get_permalink( $pagina )
		);
	}

	/**
	 * Página que aloja el formulario.
	 *
	 * @return int
	 */
	public static function pagina(): int {
		$guardada = (int) get_option( self::OPCION_PAGINA, -1 );

		if ( $guardada > 0 && 'page' === get_post_type( $guardada ) ) {
			return $guardada;
		}

		$existente = get_page_by_path( self::SLUG, 'OBJECT', 'page' );

		if ( $existente instanceof \WP_Post ) {
			update_option( self::OPCION_PAGINA, (int) $existente->ID );

			return (int) $existente->ID;
		}

		// Una página con el shortcode en otro slug también sirve: no se crea una segunda.
		$con_shortcode = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'numberposts'    => 1,
				's'              => '[' . self::SHORTCODE . ']',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! empty( $con_shortcode ) ) {
			update_option( self::OPCION_PAGINA, (int) $con_shortcode[0] );

			return (int) $con_shortcode[0];
		}

		return 0;
	}

	/**
	 * Crea la página si no existe. Idempotente.
	 *
	 * @return void
	 */
	public static function asegurar_pagina(): void {
		if ( self::pagina() > 0 ) {
			return;
		}

		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Subir justificante de pago', 'convoca-members' ),
				'post_name'    => self::SLUG,
				'post_content' => '[' . self::SHORTCODE . ']',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( $id > 0 ) {
			update_option( self::OPCION_PAGINA, (int) $id );
		}
	}

	/**
	 * Pinta el formulario o el resultado.
	 *
	 * @return string
	 */
	public static function render(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- el token va firmado en la URL.
		$member_id = isset( $_GET['id'] ) ? absint( wp_unslash( (string) $_GET['id'] ) ) : 0;
		$token     = isset( $_GET[ self::PARAM ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ self::PARAM ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $member_id <= 0 || ! self::token_valido( $member_id, $token ) ) {
			return '<div class="convoca-alert convoca-alert--warning">'
				. esc_html__( 'Este enlace no es válido o ha caducado. Pide uno nuevo a la asociación.', 'convoca-members' )
				. '</div>';
		}

		if ( isset( $_GET['subido'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '<div class="convoca-alert convoca-alert--success">'
				. esc_html__( 'Hemos recibido tu justificante. Lo revisaremos y te avisaremos cuando esté comprobado; hasta entonces tu alta sigue pendiente.', 'convoca-members' )
				. '</div>';
		}

		$errores = array(
			'fichero'         => __( 'No has adjuntado ningún fichero.', 'convoca-members' ),
			'tamano'          => __( 'El justificante no puede pasar de 5 MB.', 'convoca-members' ),
			'tipo'            => __( 'El justificante tiene que ser un PDF o una imagen (JPG o PNG).', 'convoca-members' ),
			'subida'          => __( 'No se ha podido guardar el fichero. Inténtalo de nuevo.', 'convoca-members' ),
			'demasiado-grande' => __( 'El justificante no puede pasar de 5 MB.', 'convoca-members' ),
		);
		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$clave = sanitize_key( wp_unslash( (string) $_GET['error'] ) );
			if ( isset( $errores[ $clave ] ) ) {
				return '<div class="convoca-alert convoca-alert--warning">' . esc_html( $errores[ $clave ] ) . '</div>';
			}
		}

		$html  = '<form class="convoca-justificante" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( self::ACCION ) . '" />';
		$html .= '<input type="hidden" name="member_id" value="' . esc_attr( (string) $member_id ) . '" />';
		$html .= '<input type="hidden" name="token" value="' . esc_attr( $token ) . '" />';
		$html .= wp_nonce_field( self::ACCION, '_convoca_nonce', true, false );
		$html .= '<p><label for="convoca-justificante">' . esc_html__( 'Justificante de pago (PDF o imagen, máximo 5 MB)', 'convoca-members' ) . '</label>';
		$html .= '<input type="file" id="convoca-justificante" name="justificante" accept="application/pdf,image/jpeg,image/png" required /></p>';
		$html .= '<p><button type="submit" class="convoca-btn convoca-btn-primary">' . esc_html__( 'Enviar justificante', 'convoca-members' ) . '</button></p>';
		$html .= '</form>';

		return $html;
	}

	/**
	 * Recibe el fichero, lo guarda y cambia el estado.
	 *
	 * @return void
	 */

	/**
	 * Tipos que se admiten como justificante de pago.
	 *
	 * @var array<string, string>
	 */
	public const MIMES = array(
		'pdf'      => 'application/pdf',
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
	);

	/**
	 * Tope de tamaño del justificante: 5 MB.
	 */
	public const TAMANO_MAX = 5242880;

	/**
	 * Comprueba tipo y tamaño antes de dejar el fichero en la biblioteca (#108).
	 *
	 * @param array<string, mixed> $fichero Entrada de `$_FILES`.
	 * @return bool|\WP_Error
	 */
	public static function revisar_fichero( array $fichero ): bool|\WP_Error {
		$tamano = isset( $fichero['size'] ) ? (int) $fichero['size'] : 0;
		if ( $tamano <= 0 ) {
			return new \WP_Error( 'vacio', __( 'El fichero está vacío.', 'convoca-members' ) );
		}
		if ( $tamano > self::TAMANO_MAX ) {
			return new \WP_Error( 'demasiado-grande', __( 'El justificante no puede pasar de 5 MB.', 'convoca-members' ) );
		}

		$nombre    = isset( $fichero['name'] ) ? (string) $fichero['name'] : '';
		$extension = strtolower( (string) pathinfo( $nombre, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, array( 'pdf', 'jpg', 'jpeg', 'png' ), true ) ) {
			return new \WP_Error( 'tipo', __( 'El justificante tiene que ser un PDF o una imagen (JPG o PNG).', 'convoca-members' ) );
		}

		// El tipo declarado tiene que ser de los nuestros: un `.pdf` que dice ser otra cosa no pasa.
		$tipo    = isset( $fichero['type'] ) ? strtolower( (string) $fichero['type'] ) : '';
		$validos = array( 'application/pdf', 'image/jpeg', 'image/png', 'image/jpg' );
		if ( '' !== $tipo && ! in_array( $tipo, $validos, true ) ) {
			return new \WP_Error( 'tipo', __( 'El justificante tiene que ser un PDF o una imagen (JPG o PNG).', 'convoca-members' ) );
		}

		return true;
	}

	/**
	 * Avisa a administración de que hay un justificante por revisar.
	 *
	 * @param int $member_id Ficha.
	 * @param int $adjunto   Id del adjunto.
	 * @return void
	 */
	private static function avisar_revision( int $member_id, int $adjunto ): void {
		$ajustes = (array) get_option( 'convoca_members_settings', array() );
		$destino = (string) ( $ajustes['admin_email'] ?? get_option( 'admin_email' ) );
		$nombre  = (string) get_the_title( $member_id );

		wp_mail(
			$destino,
			sprintf(
				/* translators: %s: nombre del sitio. */
				__( '[%s] Justificante de pago pendiente de revisar', 'convoca-members' ),
				wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
			),
			sprintf(
				/* translators: 1: nombre de la persona, 2: enlace a la ficha, 3: enlace al justificante. */
				__( "%1\$s ha subido un justificante de pago. La ficha sigue pendiente hasta que se compruebe el ingreso.\n\nFicha: %2\$s\nJustificante: %3\$s", 'convoca-members' ),
				$nombre,
				admin_url( 'post.php?post=' . $member_id . '&action=edit' ),
				(string) wp_get_attachment_url( $adjunto )
			),
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	/**
	 * Procesa la subida del justificante.
	 */
	public static function procesar(): void {
		$nonce = isset( $_POST['_convoca_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['_convoca_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::ACCION ) ) {
			wp_die(
				esc_html__( 'La petición ha caducado. Vuelve a intentarlo.', 'convoca-members' ),
				'',
				array( 'response' => 403 )
			);
		}

		$member_id = isset( $_POST['member_id'] ) ? absint( wp_unslash( (string) $_POST['member_id'] ) ) : 0;
		$token     = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['token'] ) ) : '';

		if ( $member_id <= 0 || ! self::token_valido( $member_id, $token ) ) {
			wp_die(
				esc_html__( 'Este enlace no es válido o ha caducado.', 'convoca-members' ),
				'',
				array( 'response' => 403 )
			);
		}

		if ( empty( $_FILES['justificante']['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'fichero', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		// Antes de tocar la biblioteca: tipo y tamaño (#108). Sin esto, cualquiera con un enlace válido
		// subía lo que quisiera, del tamaño que quisiera, a los medios de la asociación.
		$revisado = self::revisar_fichero( (array) $_FILES['justificante'] );
		if ( is_wp_error( $revisado ) ) {
			$codigo = 'demasiado-grande' === $revisado->get_error_code() ? 'tamano' : 'tipo';
			wp_safe_redirect( add_query_arg( 'error', $codigo, wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		// Rutas de la administración: en el arnés de análisis no existen y falsean el informe.
		// @phpstan-ignore-next-line
		require_once ABSPATH . 'wp-admin/includes/file.php';
		// @phpstan-ignore-next-line
		require_once ABSPATH . 'wp-admin/includes/media.php';
		// @phpstan-ignore-next-line
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Nombre propio y saneado: no se mezcla con las imágenes del sitio y se sabe de quién es.
		$renombrar = static function ( array $file ) use ( $member_id ): array {
			$extension    = strtolower( (string) pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
			$file['name'] = 'justificante-' . $member_id . '-' . gmdate( 'Ymd-His' ) . '.' . $extension;
			return $file;
		};
		add_filter( 'wp_handle_upload_prefilter', $renombrar );

		$adjunto = media_handle_upload(
			'justificante',
			0,
			array(),
			array(
				'test_form' => false,
				'mimes'     => self::MIMES,
			)
		);

		remove_filter( 'wp_handle_upload_prefilter', $renombrar );

		if ( is_wp_error( $adjunto ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'subida', wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
			exit;
		}

		update_post_meta( $member_id, '_convoca_justificante_id', (int) $adjunto );
		update_post_meta( $member_id, '_convoca_justificante_fecha', current_time( 'mysql' ) );
		update_post_meta( $member_id, '_convoca_justificante_estado', 'pendiente' );

		// El justificante NO activa la ficha: se queda pendiente de que alguien mire el extracto. La
		// página de pago promete exactamente eso («quedará pendiente hasta que verifiquemos el ingreso»)
		// y antes el código se lo saltaba (#107): subir una foto del gato daba la condición de socio.
		update_post_meta( $adjunto, '_convoca_privado', '1' );

		self::avisar_revision( $member_id, (int) $adjunto );

		$destino = remove_query_arg( array( self::PARAM, 'id' ) );
		$destino = add_query_arg( 'subido', '1', $destino );

		wp_safe_redirect( $destino );
		exit;
	}
}
