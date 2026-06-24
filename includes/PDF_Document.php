<?php
/**
 * Volunteer PDF Agreement Generator.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PDF_Document {

	private const ERROR_TRANSIENT = 'convoca_pdf_gen_error_';

	public static function init(): void {
		add_action( 'convoca_voluntario_aprobado', array( self::class, 'handle_approval' ) );
		add_filter( 'convoca_voluntario_aprobado_attachments', array( self::class, 'add_pdf_to_email' ), 10, 2 );
		add_action( 'admin_notices', array( self::class, 'show_pdf_error_notice' ) );
	}

	/**
	 * Shows an admin notice if a PDF generation failed in a previous request.
	 */
	public static function show_pdf_error_notice(): void {
		$user_id = get_current_user_id();
		$error   = get_transient( self::ERROR_TRANSIENT . $user_id );

		if ( $error ) {
			delete_transient( self::ERROR_TRANSIENT . $user_id );
			\Convoca\Core\Utils::admin_notice(
				'<strong>' . esc_html__( 'Error en la generación del documento:', 'convoca-members' ) . '</strong><br>' . esc_html( $error ),
				'danger'
			);
		}
	}

	public static function handle_approval( int $user_id ): void {
		self::generate_volunteer_agreement( $user_id );
	}

	public static function add_pdf_to_email( array $attachments, int $user_id ): array {
		$existing = get_posts(
			array(
				'post_type'      => 'convoca_documento',
				'meta_key'       => '_convoca_usuario_id',
				'meta_value'     => $user_id,
				'posts_per_page' => 1,
			)
		);

		if ( ! empty( $existing ) ) {
			$filepath = get_post_meta( $existing[0]->ID, '_convoca_documento_path', true );
			if ( $filepath && file_exists( $filepath ) ) {
				$attachments[] = $filepath;
			}
		}

		return $attachments;
	}

	public static function generate_volunteer_agreement( int $user_id ): ?int {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		global $wpdb;

		// ── 1. Check if already exists WITHOUT locking first to avoid unnecessary draft creation ──
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p 
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'convoca_documento' 
             AND pm.meta_key = '_convoca_usuario_id' AND pm.meta_value = %d 
             AND p.post_status = 'publish' LIMIT 1",
				$user_id
			)
		);

		if ( $existing_id ) {
			return (int) $existing_id;
		}

		// ── 2. Create the document post in 'draft' status OUTSIDE the transaction ──
		$nombre       = $user->first_name ?: $user->display_name;
		$temp_post_id = wp_insert_post(
			array(
				'post_type'   => 'convoca_documento',
				'post_title'  => 'Acuerdo Voluntariado - ' . $nombre,
				'post_status' => 'draft',
				'post_author' => 1, // System.
			)
		);

		if ( is_wp_error( $temp_post_id ) ) {
			return null;
		}

		try {
			$wpdb->query( 'START TRANSACTION' );

			// ── 3. LOCK and check again for existence ──
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p 
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                 WHERE p.post_type = 'convoca_documento' 
                 AND pm.meta_key = '_convoca_usuario_id' AND pm.meta_value = %d 
                 AND p.post_status = 'publish' 
                 FOR UPDATE",
					$user_id
				)
			);

			if ( $existing_id ) {
				$wpdb->query( 'ROLLBACK' );
				wp_delete_post( $temp_post_id, true );
				return (int) $existing_id;
			}

			if ( ! class_exists( '\\Convoca\\Core\\Signature' ) ) {
				$wpdb->query( 'ROLLBACK' );
				wp_delete_post( $temp_post_id, true );
				error_log( 'Convoca: Signature class not found.' );
				return null;
			}

			$signature = new \Convoca\Core\Signature();

			// Collect user data.
			$dni       = get_user_meta( $user_id, '_convoca_shifts_dni', true );
			$email     = $user->user_email;
			$telefono  = get_user_meta( $user_id, '_convoca_shifts_telefono', true );
			$direccion = get_user_meta( $user_id, '_convoca_shifts_direccion', true );
			$municipio = get_user_meta( $user_id, '_convoca_shifts_municipio', true );

			$legal_text = get_option( 'convoca_volunteer_legal_text', '' );
			$date       = wp_date( 'd/m/Y' );

			$timestamp = time();
			$ip        = filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP ) ?: 'Desconocida';

			// Dynamic fields.
			$dynamic_fields = get_option( 'convoca_volunteer_fields', array() );
			$dynamic_html   = '';
			if ( ! empty( $dynamic_fields ) ) {
				$dynamic_html .= '<h3>Información Adicional</h3><table style="width:100%; border-collapse: collapse; margin-bottom: 20px;">';
				foreach ( $dynamic_fields as $field ) {
					$field_name = isset( $field['name'] ) ? $field['name'] : '';
					$val        = get_user_meta( $user_id, '_convoca_shifts_' . $field_name, true );

					if ( $val ) {
						$dynamic_html .= '<tr class="' . esc_attr( 'conv-field-' . $field_name ) . '">';
						$dynamic_html .= '<th class="' . esc_attr( 'conv-label' ) . '" style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd; width: 40%;">' . esc_html( $field['label'] ?? '' ) . '</th>';
						$dynamic_html .= '<td class="' . esc_attr( 'conv-value' ) . '" style="padding: 8px; border-bottom: 1px solid #ddd;">' . esc_html( $val ) . '</td>';
						$dynamic_html .= '</tr>';
					}
				}
				$dynamic_html .= '</table>';
			}

			$templates     = get_option( 'convoca_pdf_templates', array() );
			$template_html = isset( $templates['acuerdo_incorporacion'] ) ? $templates['acuerdo_incorporacion']['content'] : '<h1>Acuerdo de Incorporación</h1><p>Nombre: {{nombre}}</p><p>DNI: {{dni}}</p>{{dynamic_fields}}{{declaracion}}';

			// Append digital stamp.
			$content_for_hash = $user_id . $dni . $email . $timestamp;
			$stamp_html       = $signature->get_acceptance_stamp_html( $nombre, $ip, $timestamp, $content_for_hash );

			if ( str_contains( $template_html, '<!-- FIRMA DIGITAL SERÁ AÑADIDA POR LA CLASE Signature -->' ) ) {
				$template_html = str_replace( '<!-- FIRMA DIGITAL SERÁ AÑADIDA POR LA CLASE Signature -->', $stamp_html, $template_html );
			} else {
				$template_html .= $stamp_html;
			}

			$data = array(
				'nombre'              => $nombre,
				'dni'                 => $dni,
				'email'               => $email,
				'telefono'            => $telefono,
				'direccion'           => $direccion . ', ' . $municipio,
				'fecha_incorporacion' => $date,
				'declaracion'         => wp_kses_post( $legal_text ),
				'dynamic_fields'      => $dynamic_html,
			);

			// Create upload directory.
			$upload_dir = wp_upload_dir();
			$target_dir = $upload_dir['basedir'] . '/convoca-documentos';
			if ( ! file_exists( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}

			$hash     = $signature->create_hash( $content_for_hash, $ip, $timestamp );
			$filename = 'acuerdo-voluntariado-' . $user_id . '-' . substr( $hash, 0, 8 ) . '.pdf';
			$filepath = $target_dir . '/' . $filename;

			$generated_path = $signature->generate_pdf( $template_html, $data, $filepath );

			if ( ! $generated_path ) {
				$wpdb->query( 'ROLLBACK' );
				wp_delete_post( $temp_post_id, true );
				$error = $signature->get_last_error();
				if ( is_admin() ) {
					set_transient( self::ERROR_TRANSIENT . get_current_user_id(), $error, 30 );
				}
				return null;
			}

			// ── 4. Publish and save meta INSIDE transaction ──
			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $temp_post_id ) );

			update_post_meta( $temp_post_id, '_convoca_usuario_id', $user_id );
			update_post_meta( $temp_post_id, '_convoca_tipo_documento', 'acuerdo_voluntariado' );
			update_post_meta( $temp_post_id, '_convoca_hash', $hash );
			update_post_meta( $temp_post_id, '_convoca_documento_url', rest_url( 'convoca-members/v1/documentos/' . $temp_post_id ) );
			update_post_meta( $temp_post_id, '_convoca_documento_path', $generated_path );

			$wpdb->query( 'COMMIT' );
			return $temp_post_id;

		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			if ( isset( $temp_post_id ) ) {
				wp_delete_post( $temp_post_id, true );
			}
			error_log( 'Convoca PDF Gen Exception: ' . $e->getMessage() );
			return null;
		}
	}
}
