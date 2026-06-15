<?php
/**
 * Certificate Generator - Generates PDF certificates for volunteers.
 *
 * Requires Dompdf library ( LGPL license ).
 * Download: https://github.com/dompdf/dompdf/releases.
 * Place dompdf folder in: convoca-members/vendor/dompdf/
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Certificate_Generator {

	private const ERROR_TRANSIENT = 'conv_cert_gen_error_';

	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'show_pdf_error_notice' ) );
	}

	/**
	 * Shows an admin notice if a PDF generation failed.
	 */
	public static function show_pdf_error_notice(): void {
		$user_id = get_current_user_id();
		$error   = get_transient( self::ERROR_TRANSIENT . $user_id );

		if ( $error ) {
			delete_transient( self::ERROR_TRANSIENT . $user_id );
			\Convoca\Core\Utils::admin_notice(
				'<strong>' . esc_html__( 'Error en la generación del Certificado:', 'convoca-members' ) . '</strong><br>' . esc_html( $error ),
				'danger'
			);
		}
	}

	public static function generate( int $miembro_id ): array|\WP_Error {
		$miembro = get_post( $miembro_id );
		if ( ! $miembro || $miembro->post_type !== 'miembro' ) {
			return new \WP_Error( 'invalid_member', 'Miembro no encontrado' );
		}

		$nombre     = $miembro->post_title;
		$email      = get_post_meta( $miembro_id, '_conv_email', true );
		$plan       = get_post_meta( $miembro_id, '_conv_plan', true );
		$plan_data  = CPT_Miembro::get_plan( $plan );
		$plan_label = ( $plan_data && isset( $plan_data['label'] ) ) ? $plan_data['label'] : $plan;

		$total_horas = Voluntariado_Manager::get_horas_aprobadas( $miembro_id );

		$proyectos = self::get_proyectos_con_horas( $miembro_id );

		$cert_id = 'VOL-' . wp_date( 'Y' ) . '-' . strtoupper( wp_generate_password( 5, false, false ) );

		$verify_url = home_url( '/verificar-certificado/?id=' . $cert_id );
		$qr_data    = self::generate_qr_data( $verify_url );

		$html = self::build_html( $nombre, $total_horas, $plan_label, $proyectos, $cert_id, $qr_data, $verify_url );

		$pdf_content = self::render_pdf_to_buffer( $html );

		if ( is_wp_error( $pdf_content ) ) {
			if ( is_admin() ) {
				set_transient( self::ERROR_TRANSIENT . get_current_user_id(), $pdf_content->get_error_message(), 30 );
			}
			return $pdf_content;
		}

		// Update meta only if PDF generated successfully.
		update_post_meta( $miembro_id, '_conv_certificado_id', $cert_id );
		update_post_meta( $miembro_id, '_conv_certificado_emitido', current_time( 'mysql' ) );

		return array(
			'id'     => $cert_id,
			'pdf'    => $pdf_content,
			'nombre' => $nombre,
			'horas'  => $total_horas,
			'plan'   => $plan_label,
			'fecha'  => current_time( 'mysql' ),
			'url'    => $verify_url,
		);
	}

	private static function render_pdf_to_buffer( string $html ): string|\WP_Error {
		if ( ! class_exists( '\\Convoca\\Core\\CONV_Signature' ) ) {
			return new \WP_Error( 'signature_missing', 'El componente de firma/PDF no está disponible.' );
		}

		$signature = new \Convoca\Core\CONV_Signature();

		// Since generate_pdf usually saves to file, but Certificate_Generator::generate() wants binary content,.
		// we use a temporary file.
		$tmp_file = tempnam( sys_get_temp_dir(), 'conv_cert_' );

		try {
			$result = $signature->generate_pdf( $html, array(), $tmp_file );

			if ( ! $result ) {
				$error = $signature->get_last_error();
				return new \WP_Error( 'pdf_error', $error );
			}

			$pdf_content = @file_get_contents( $tmp_file );
			if ( $pdf_content === false ) {
				return new \WP_Error( 'read_error', 'No se pudo leer el archivo temporal generado.' );
			}

			return $pdf_content;
		} finally {
			if ( file_exists( $tmp_file ) ) {
				@unlink( $tmp_file );
			}
		}
	}

	private static function get_proyectos_con_horas( int $miembro_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_content, 
                    SUM(CAST(hm.meta_value AS DECIMAL(10,2))) as horas
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} ph ON ph.post_id = p.ID AND ph.meta_key = '_conv_proyecto_id'
             INNER JOIN {$wpdb->postmeta} hm ON hm.post_id = p.ID AND hm.meta_key = '_conv_horas'
             INNER JOIN {$wpdb->postmeta} he ON he.post_id = p.ID AND he.meta_key = '_conv_estado' AND he.meta_value = 'aprobada'
             INNER JOIN {$wpdb->postmeta} hm2 ON hm2.post_id = p.ID AND hm2.meta_key = '_conv_member_id' AND hm2.meta_value = %d
             WHERE p.post_type = 'registro_hora'
             GROUP BY p.ID
             ORDER BY horas DESC",
				$miembro_id
			),
			ARRAY_A
		);

		$proyectos = array();
		foreach ( $results as $r ) {
			$proyecto_id = get_post_meta( $r['ID'], '_conv_proyecto_id', true );
			$proyecto    = $proyecto_id ? get_post( $proyecto_id ) : null;
			$tareas      = get_post_meta( $r['ID'], '_conv_tareas', true );
			$fecha       = get_post_meta( $r['ID'], '_conv_fecha', true );

			if ( $proyecto ) {
				if ( ! isset( $proyectos[ $proyecto->ID ] ) ) {
					$proyectos[ $proyecto->ID ] = array(
						'titulo' => $proyecto->post_title,
						'horas'  => 0,
						'tareas' => array(),
						'fechas' => array(),
					);
				}
				$proyectos[ $proyecto->ID ]['horas'] += (float) $r['horas'];
				if ( $tareas ) {
					$proyectos[ $proyecto->ID ]['tareas'][] = $tareas;
				}
				if ( $fecha ) {
					$proyectos[ $proyecto->ID ]['fechas'][] = $fecha;
				}
			}
		}

		return array_values( $proyectos );
	}

	private static function generate_qr_data( string $url ): string {
		return $url;
	}

	private static function build_html( string $nombre, float $horas, string $plan, array $proyectos, string $cert_id, string $qr_data, string $verify_url ): string {
		$proyectos_html = '';
		foreach ( $proyectos as $p ) {
			$tareas_resumen  = ! empty( $p['tareas'] ) ? implode( '. ', array_map( 'substr', $p['tareas'], array_fill( 0, count( $p['tareas'] ), 0 ), array_fill( 0, count( $p['tareas'] ), 60 ) ) ) : 'Sin descripción';
			$proyectos_html .= '<div class="proyecto"><strong>' . esc_html( $p['titulo'] ) . '</strong>: ' . number_format( $p['horas'], 1 ) . 'h<br><small>' . esc_html( mb_substr( $tareas_resumen, 0, 100 ) ) . '</small></div>';
		}

		return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .certificado { border: 3px solid #2d5a27; padding: 40px; max-width: 800px; margin: 0 auto; background: #f9fff9; }
        .header { text-align: center; border-bottom: 2px solid #2d5a27; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 48px; }
        h1 { color: #2d5a27; margin: 10px 0; }
        .contenido { font-size: 18px; line-height: 1.8; }
        .nombre { font-size: 24px; font-weight: bold; color: #1a3a15; }
        .horas { font-size: 20px; color: #2d5a27; font-weight: bold; }
        .proyectos { margin: 20px 0; padding: 15px; background: #e8f5e9; border-radius: 8px; }
        .proyecto { margin: 10px 0; }
        .footer { margin-top: 40px; text-align: center; border-top: 1px solid #ccc; padding-top: 20px; }
        .qr { margin: 20px auto; width: 120px; height: 120px; background: #2d5a27; color: white; display: flex; align-items: center; justify-content: center; font-size: 10px; }
        .cert-id { font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="certificado">
        <div class="header">
            <div class="logo">🌿</div>
            <h1>Certificado de Voluntariado</h1>
            <p>" . esc_html(get_bloginfo("name")) . "</p>
        </div>
        <div class="contenido">
            <p>Certificamos que <span class="nombre">' . esc_html( $nombre ) . '</span></p>
            <p>ha completado un total de <span class="horas">' . number_format( $horas, 1 ) . ' horas</span> de voluntariado</p>
            <p>como parte del plan <strong>' . esc_html( $plan ) . '</strong></p>
            
            <h3>Proyectos Participados</h3>
            <div class="proyectos">' . $proyectos_html . '</div>
        </div>
        <div class="footer">
            <p>Fecha de emisión: ' . wp_date( 'd/m/Y' ) . '</p>
            <p class="cert-id">ID: ' . esc_html( $cert_id ) . '</p>
            <div class="qr">QR</div>
            <p><small>Verificar en: ' . esc_html( $verify_url ) . '</small></p>
        </div>
    </div>
</body>
</html>';
	}

	public static function serve_pdf( int $miembro_id ): void {
		$cert_id = get_post_meta( $miembro_id, '_conv_certificado_id', true );

		if ( ! $cert_id ) {
			$result = self::generate( $miembro_id );
			if ( is_wp_error( $result ) ) {
				wp_die( $result->get_error_message() );
			}
			$cert_id = $result['id'];
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="certificado-' . $cert_id . '.pdf"' );

		$result = self::generate( $miembro_id );
		if ( ! is_wp_error( $result ) ) {
			echo $result['pdf'];
		}
		exit;
	}

	public static function verify( string $cert_id ): ?array {
		global $wpdb;

		$miembro_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_conv_certificado_id' AND meta_value = %s LIMIT 1",
				$cert_id
			)
		);

		if ( ! $miembro_id ) {
			return null;
		}

		$miembro = get_post( $miembro_id );

		return array(
			'nombre'         => $miembro->post_title,
			'horas'          => Voluntariado_Manager::get_horas_aprobadas( $miembro_id ),
			'certificado_id' => $cert_id,
			'emitido'        => get_post_meta( $miembro_id, '_conv_certificado_emitido', true ),
		);
	}
}
