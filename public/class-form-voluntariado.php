<?php
/**
 * Volunteer registration form (shortcode [biodevas_voluntariado]).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Voluntariado {


	/** Interest areas matching the static site. */
	public const INTEREST_AREAS = array(
		'educacion'    => '📚 Educación Ambiental',
		'aves'         => '🐦 Ornitología (Paxareando)',
		'marina'       => '🌊 Biodiversidad Marina (GIMPA)',
		'rios'         => '💧 Ciencia Ciudadana Fluvial',
		'botanica'     => '🌿 Botánica (Veget-ando)',
		'meteo'        => '🌦️ Meteorología (Troposfera)',
		'infantil'     => '👶 Actividades Infantiles (Busgosu)',
		'lugg'         => '🏠 Centro Social Los Lugg',
		'comunicacion' => '📢 Comunicación y RRSS',
		'diseno'       => '🎨 Diseño e Ilustración',
		'fotografia'   => '📷 Fotografía de Naturaleza',
		'logistica'    => '🚗 Logística y Coordinación',
	);

	public function __construct() {
		add_shortcode( 'convoca_voluntariado', array( $this, 'render' ) );
		add_action( 'wp_ajax_bdv_voluntariado_submit', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_bdv_voluntariado_submit', array( $this, 'handle_submit' ) );
	}

	public function render(): string {
		wp_enqueue_style(
			'bdv-members-public',
			BDV_MEMBERS_URL . 'assets/css/biodevas-members-public.css',
			array(),
			BDV_MEMBERS_VERSION
		);
		wp_enqueue_script(
			'bdv-members-public',
			BDV_MEMBERS_URL . 'assets/js/biodevas-members-public.js',
			array( 'convoca-common-js' ),
			BDV_MEMBERS_VERSION,
			true
		);
		$config = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'volNonce' => wp_create_nonce( 'bdv_voluntariado_nonce' ),
		);

		ob_start();
		include BDV_MEMBERS_DIR . 'templates/form-voluntariado.php';
		$html = ob_get_clean();

		// Inject data-config into the wrapper
		$html = str_replace(
			'id="bdv-vol-wrapper"',
			'id="bdv-vol-wrapper" data-config=\'' . esc_attr( wp_json_encode( $config ) ) . '\'',
			$html
		);

		return $html;
	}

	public function handle_submit(): void {
		check_ajax_referer( 'bdv_voluntariado_nonce', 'nonce' );

		// Rate limit: max 3 volunteer registrations per hour per IP
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'bdv_voluntariado_submit', 3, 3600 ) ) {
			wp_send_json_error( array( 'errors' => array( 'Demasiados intentos. Inténtalo de nuevo en una hora.' ) ), 429 );
		}

		// Unslash POST data to handle magic_quotes
		$post_data = wp_unslash( $_POST );

		$nombre         = sanitize_text_field( $post_data['nombre'] ?? '' );
		$dni            = strtoupper( trim( $post_data['dni'] ?? '' ) );
		$dni            = str_replace( array( ' ', '-' ), '', $dni );
		$fecha_nac      = sanitize_text_field( $post_data['fecha_nacimiento'] ?? '' );
		$email          = sanitize_email( $post_data['email'] ?? '' );
		$telefono       = sanitize_text_field( $post_data['telefono'] ?? '' );
		$whatsapp       = sanitize_text_field( $post_data['whatsapp'] ?? 'si' );
		$direccion      = sanitize_text_field( $post_data['direccion'] ?? '' );
		$municipio      = sanitize_text_field( $post_data['municipio'] ?? '' );
		$canal          = sanitize_text_field( $post_data['canal_contacto'] ?? 'whatsapp' );
		$disponibilidad = sanitize_text_field( $post_data['disponibilidad'] ?? '' );
		$experiencia    = sanitize_text_field( $post_data['experiencia'] ?? '' );
		$motivacion     = sanitize_textarea_field( $post_data['motivacion'] ?? '' );
		$rgpd           = ! empty( $post_data['rgpd'] );
		$comunicaciones = ! empty( $post_data['comunicaciones'] );

		// Interests.
		$intereses_raw = isset( $post_data['intereses'] ) && is_array( $post_data['intereses'] )
			? array_map( 'sanitize_text_field', $post_data['intereses'] )
			: array();
		$intereses     = implode( ',', $intereses_raw );

		// Validation.
		$errors = array();
		if ( empty( $nombre ) ) {
			$errors[] = 'El nombre es obligatorio.';
		}
		if ( empty( $dni ) ) {
			$errors[] = 'El DNI/NIE es obligatorio.';
		}
		if ( empty( $fecha_nac ) ) {
			$errors[] = 'La fecha de nacimiento es obligatoria.';
		}
		if ( ! is_email( $email ) ) {
			$errors[] = 'El email no es válido.';
		}
		if ( empty( $telefono ) ) {
			$errors[] = 'El teléfono es obligatorio.';
		}
		if ( empty( $direccion ) ) {
			$errors[] = 'La dirección es obligatoria.';
		}
		if ( empty( $municipio ) ) {
			$errors[] = 'El municipio es obligatorio.';
		}
		if ( ! $rgpd ) {
			$errors[] = 'Debes aceptar la política de privacidad.';
		}
		if ( empty( $post_data['declaracion_responsable'] ) ) {
			$errors[] = 'Debes aceptar la Declaración Responsable.';
		}

		// Minor detection.
		try {
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha_nac ) ) {
				throw new \Exception( 'Formato de fecha inválido.' );
			}
			$dob   = new \DateTime( $fecha_nac );
			$today = new \DateTime();
			$age   = $today->diff( $dob )->y;
			$menor = $age < 18;
		} catch ( \Exception $e ) {
			$errors[] = 'La fecha de nacimiento no es válida (Formato esperado: AAAA-MM-DD).';
			$menor    = false;
		}

		if ( $dni && ! self::validar_dni( $dni ) ) {
			$errors[] = 'El DNI/NIE no es válido.';
		}

		if ( $menor ) {
			$tutor_dni = strtoupper( trim( $post_data['tutor_dni'] ?? '' ) );
			$tutor_dni = str_replace( array( ' ', '-' ), '', $tutor_dni );
			if ( empty( $tutor_dni ) ) {
				$errors[] = 'El DNI del tutor es obligatorio para menores de edad.';
			} elseif ( ! self::validar_dni( $tutor_dni ) ) {
				$errors[] = 'El DNI del tutor no es válido.';
			}
		}

		// Dynamic fields validation
		$dynamic_fields = get_option( 'bdv_volunteer_fields', array() );
		$dynamic_data   = array();
		foreach ( $dynamic_fields as $field ) {
			$name = 'dyn_' . $field['name'];
			$val  = isset( $post_data[ $name ] ) ? ( is_array( $post_data[ $name ] ) ? implode( ', ', array_map( 'sanitize_text_field', $post_data[ $name ] ) ) : sanitize_textarea_field( $post_data[ $name ] ) ) : '';
			if ( ! empty( $field['required'] ) && empty( $val ) ) {
				$errors[] = sprintf( 'El campo "%s" es obligatorio.', $field['label'] );
			}
			$dynamic_data[ '_cst_' . $field['name'] ] = $val;
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		// Check for duplicate Email. DNI is handled in meta.
		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'errors' => array( 'Ese correo ya está registrado en el sistema.' ) ) );
		}

		// Check for duplicate DNI in user meta
		$existing_dni = get_users(
			array(
				'meta_key'   => '_cst_dni',
				'meta_value' => $dni,
				'number'     => 1,
			)
		);
		if ( ! empty( $existing_dni ) ) {
			wp_send_json_error( array( 'errors' => array( 'Ese DNI ya está registrado en el sistema.' ) ) );
		}

		// Create WP User
		$username = sanitize_user( current( explode( '@', $email ) ) . random_int( 100, 999 ) );
		$password = wp_generate_password( 12, false );
		$user_id  = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'errors' => array( 'Error al crear el registro: ' . $user_id->get_error_message() ) ) );
		}

		// Update basic user info
		wp_update_user(
			array(
				'ID'         => $user_id,
				'first_name' => $nombre,
			)
		);

		// Crear registro de miembro (CPT) para que el voluntario tenga acceso al certificado
		// y las horas de todas las fuentes se vinculen correctamente.
		$member_post_id = wp_insert_post(
			array(
				'post_type'   => 'miembro',
				'post_title'  => $nombre,
				'post_status' => 'publish',
			)
		);

		if ( ! is_wp_error( $member_post_id ) ) {
			// Guardar metadatos básicos del miembro
			$member_meta = array(
				'estado_miembro'    => 'pendiente_documentacion',
				'dni'               => $dni,
				'email'             => $email,
				'telefono'          => $telefono,
				'fecha_nacimiento'  => $fecha_nac,
				'direccion'         => $direccion,
				'municipio'         => $municipio,
				'whatsapp'          => $whatsapp,
				'es_voluntario'     => '1',
				'forma_pago'        => 'voluntariado',
				'plan'              => 'voluntariado',
				'access_code'       => \Convoca\Core\Utils::generate_access_code(),
				'rgpd_version'      => ( get_option( 'bdv_members_settings', array() )['rgpd_version'] ?? '1.0' ),
				'rgpd_timestamp'    => current_time( 'mysql' ),
				'comunicaciones_ok' => $comunicaciones ? '1' : '0',
			);

			foreach ( $member_meta as $key => $value ) {
				update_post_meta( $member_post_id, '_bdv_' . $key, $value );
			}

			// Vincular WP user con el miembro
			update_post_meta( $member_post_id, '_bdv_user_id', $user_id );
			update_user_meta( $user_id, '_bdv_member_id', $member_post_id );
		}

		$settings = get_option( 'bdv_members_settings', array() );

		$meta_map = array(
			'_cst_aprobado'                => 0, // Pending approval
			'_cst_dni'                     => $dni,
			'_cst_fecha_nacimiento'        => $fecha_nac,
			'_cst_telefono'                => $telefono,
			'_cst_whatsapp'                => $whatsapp,
			'_cst_direccion'               => $direccion,
			'_cst_municipio'               => $municipio,
			'_cst_canal_contacto'          => $canal,
			'_cst_intereses'               => $intereses,
			'_cst_disponibilidad'          => $disponibilidad,
			'_cst_experiencia'             => $experiencia,
			'_cst_motivacion'              => $motivacion,
			'_cst_menor_edad'              => $menor ? '1' : '0',
			'_cst_rgpd_version'            => $settings['rgpd_version'] ?? '1.0',
			'_cst_rgpd_timestamp'          => current_time( 'mysql' ),
			'_cst_comunicaciones_ok'       => $comunicaciones ? '1' : '0',
			'_cst_declaracion_responsable' => '1',
		);

		if ( $menor ) {
			$meta_map['_cst_tutor_nombre'] = sanitize_text_field( $post_data['tutor_nombre'] ?? '' );
			$meta_map['_cst_tutor_dni']    = sanitize_text_field( $post_data['tutor_dni'] ?? '' );
		}

		// Merge dynamic fields
		$meta_map = array_merge( $meta_map, $dynamic_data );

		foreach ( $meta_map as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}

		// Notify admins
		wp_mail(
			$settings['admin_email'] ?? get_option( 'admin_email' ),
			'Nueva solicitud de voluntariado',
			"Se ha recibido una nueva solicitud de voluntariado de {$nombre} ({$email}).\nRevisa la pantalla de 'Gestionar Voluntarios' para aprobarla."
		);

		do_action( 'bdv_voluntario_pendiente', $user_id );

		wp_send_json_success(
			array(
				'nombre' => $nombre,
				'email'  => $email,
			)
		);
	}
	/**
	 * Validate Spanish DNI/NIE checksum using centralized Utils.
	 */
	public static function validar_dni( string $dni ): bool {
		return \Convoca\Core\Utils::validate_dni( $dni );
	}
}
