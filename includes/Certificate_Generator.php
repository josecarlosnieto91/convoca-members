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

	private const ERROR_TRANSIENT = 'convoca_cert_gen_error_';

	/**
	 * Años de validez del certificado desde su emisión (decisión D13 2026-09-09:
	 * 1 año; regeneración al completar nuevas horas). Filtrable por sitio.
	 */
	public static function validity_years(): int {
		return max( 1, (int) apply_filters( 'convoca_members_certificado_validez_anios', 1 ) );
	}

	/**
	 * Registra el hook de regeneración de validez al aprobarse horas nuevas.
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( self::class, 'show_pdf_error_notice' ) );
		add_action( 'convoca_members_hora_aprobada', array( self::class, 'refresh_validity_on_new_hours' ), 10, 2 );
	}

	/**
	 * Al aprobarse una hora nueva, la validez del certificado se extiende desde
	 * hoy (el certificado se considera regenerado: cubre las horas completadas).
	 *
	 * @param int $record_id  ID del registro de horas aprobado.
	 * @param int $miembro_id ID del miembro.
	 */
	public static function refresh_validity_on_new_hours( int $record_id, int $miembro_id ): void {
		if ( get_post_meta( $miembro_id, '_convoca_certificado_id', true ) ) {
			update_post_meta( $miembro_id, '_convoca_certificado_emitido', current_time( 'mysql' ) );
			update_post_meta( $miembro_id, '_convoca_certificado_valido_hasta', self::validity_expiry_mysql() );
		}
	}

	/**
	 * Fecha de caducidad (emisión + validez) en formato MySQL.
	 */
	private static function validity_expiry_mysql(): string {
		return wp_date( 'Y-m-d H:i:s', time() + ( self::validity_years() * YEAR_IN_SECONDS ) );
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

	public static function generate( int $miembro_id, string $theme = '' ): array|\WP_Error {
		$miembro = get_post( $miembro_id );
		if ( ! $miembro || $miembro->post_type !== 'miembro' ) {
			return new \WP_Error( 'invalid_member', __( 'Miembro no encontrado', 'convoca-members' ) );
		}

		$nombre     = $miembro->post_title;
		$email      = get_post_meta( $miembro_id, '_convoca_email', true );
		$plan       = get_post_meta( $miembro_id, '_convoca_plan', true );
		$plan_data  = CPT_Miembro::get_plan( $plan );
		$plan_label = ( $plan_data && isset( $plan_data['label'] ) ) ? $plan_data['label'] : $plan;

		$total_horas = Voluntariado_Manager::get_horas_aprobadas( $miembro_id );

		$proyectos = self::get_proyectos_con_horas( $miembro_id );

		$cert_id = 'VOL-' . wp_date( 'Y' ) . '-' . strtoupper( wp_generate_password( 5, false, false ) );

		$verify_url = home_url( '/verificar-certificado/?id=' . $cert_id );
		$qr_data    = self::generate_qr_data( $verify_url );

		$valido_hasta = self::validity_expiry_mysql();
		$html = self::build_html( $nombre, $total_horas, $plan_label, $proyectos, $cert_id, $qr_data, $verify_url, $valido_hasta, $theme );

		$pdf_content = self::render_pdf_to_buffer( $html );

		if ( is_wp_error( $pdf_content ) ) {
			if ( is_admin() ) {
				set_transient( self::ERROR_TRANSIENT . get_current_user_id(), $pdf_content->get_error_message(), 30 );
			}
			return $pdf_content;
		}

		// Update meta only if PDF generated successfully.
		$expiry_mysql = self::validity_expiry_mysql();
		update_post_meta( $miembro_id, '_convoca_certificado_id', $cert_id );
		update_post_meta( $miembro_id, '_convoca_certificado_emitido', current_time( 'mysql' ) );
		update_post_meta( $miembro_id, '_convoca_certificado_valido_hasta', $expiry_mysql );

		return array(
			'id'     => $cert_id,
			'pdf'    => $pdf_content,
			'nombre' => $nombre,
			'horas'  => $total_horas,
			'plan'   => $plan_label,
			'fecha'  => current_time( 'mysql' ),
			'valido_hasta' => $expiry_mysql,
			'url'    => $verify_url,
		);
	}

	private static function render_pdf_to_buffer( string $html ): string|\WP_Error {
		if ( ! class_exists( '\\Convoca\\Core\\Signature' ) ) {
			return new \WP_Error( 'signature_missing', __( 'El componente de firma/PDF no está disponible.', 'convoca-members' ) );
		}

		$signature = new \Convoca\Core\Signature();

		// Since generate_pdf usually saves to file, but Certificate_Generator::generate() wants binary content,.
		// we use a temporary file.
		$tmp_file = tempnam( sys_get_temp_dir(), 'convoca_cert_' );

		try {
			$result = $signature->generate_pdf( $html, array(), $tmp_file );

			if ( ! $result ) {
				$error = $signature->get_last_error();
				return new \WP_Error( 'pdf_error', $error );
			}

			$pdf_content = @file_get_contents( $tmp_file );
			if ( $pdf_content === false ) {
				return new \WP_Error( 'read_error', __( 'No se pudo leer el archivo temporal generado.', 'convoca-members' ) );
			}

			return $pdf_content;
		} finally {
			if ( file_exists( $tmp_file ) ) {
				wp_delete_file( $tmp_file );
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
             INNER JOIN {$wpdb->postmeta} ph ON ph.post_id = p.ID AND ph.meta_key = '_convoca_proyecto_id'
             INNER JOIN {$wpdb->postmeta} hm ON hm.post_id = p.ID AND hm.meta_key = '_convoca_horas'
             INNER JOIN {$wpdb->postmeta} he ON he.post_id = p.ID AND he.meta_key = '_convoca_estado' AND he.meta_value = 'aprobada'
             INNER JOIN {$wpdb->postmeta} hm2 ON hm2.post_id = p.ID AND hm2.meta_key = '_convoca_member_id' AND hm2.meta_value = %d
             WHERE p.post_type = 'registro_hora'
             GROUP BY p.ID
             ORDER BY horas DESC",
				$miembro_id
			),
			ARRAY_A
		);

		$proyectos = array();
		foreach ( $results as $r ) {
			$proyecto_id = get_post_meta( $r['ID'], '_convoca_proyecto_id', true );
			$proyecto    = $proyecto_id ? get_post( $proyecto_id ) : null;
			$tareas      = get_post_meta( $r['ID'], '_convoca_tareas', true );
			$fecha       = get_post_meta( $r['ID'], '_convoca_fecha', true );

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

	private static function build_qr_svg( string $data ): string {
		// QR generado localmente con chillerlan/php-qrcode (sin API externa).
		// Evita dependencia de terceros y filtración de datos del certificado.
		if ( class_exists( '\\chillerlan\\QRCode\\QRCode' ) && class_exists( '\\chillerlan\\QRCode\\QROptions' ) && class_exists( '\\chillerlan\\QRCode\\Output\\QRGdImagePNG' ) ) {
			try {
				$options = new \chillerlan\QRCode\QROptions(
					array(
						'outputInterface'  => \chillerlan\QRCode\Output\QRGdImagePNG::class,
						'eccLevel'         => \chillerlan\QRCode\Common\EccLevel::M,
						'scale'            => 6,
						'addQuietzone'     => true,
						'quietzoneSize'    => 2,
						'outputBase64'     => false,
						'imageTransparent' => false,
					)
				);
				$qrcode  = new \chillerlan\QRCode\QRCode( $options );
				$png     = $qrcode->render( $data );
				// Sin width/height inline: el CSS contenedor (.qr img{width:100%}) escala el QR.
				return '<img src="data:image/png;base64,' . base64_encode( $png ) . '" alt="QR" />';
			} catch ( \Throwable $e ) {
				\Convoca\Core\Logger::warning( 'QR local falló: ' . $e->getMessage(), 'Members/Certificates' );
			}
		}
		// Fallback: texto legible con la URL de verificación (rellena la caja .qr).
		return '<div style="width:100%;height:100%;background:#fff;color:#320028;font-size:9px;padding:4px;text-align:center;word-break:break-all;box-sizing:border-box;display:table-cell;vertical-align:middle;border-radius:6px;">' . esc_html( $data ) . '</div>';
	}

	private static function build_html( string $nombre, float $horas, string $plan, array $proyectos, string $cert_id, string $qr_data, string $verify_url, string $valido_hasta = '', string $theme = '' ): string {
		$theme = in_array( $theme, array( 'light', 'dark' ), true ) ? $theme : \Convoca\Core\Utils::get_document_theme( 'certificate' );
		$light = 'light' === $theme;
		$proyectos_html = '';
		foreach ( $proyectos as $p ) {
			$tareas_resumen  = ! empty( $p['tareas'] ) ? implode( '. ', array_map( 'substr', $p['tareas'], array_fill( 0, count( $p['tareas'] ), 0 ), array_fill( 0, count( $p['tareas'] ), 60 ) ) ) : 'Sin descripción';
			$proyectos_html .= '<div class="proyecto"><strong>' . esc_html( $p['titulo'] ) . '</strong>: ' . number_format( $p['horas'], 1 ) . 'h<br><small>' . esc_html( mb_substr( $tareas_resumen, 0, 100 ) ) . '</small></div>';
		}

		return '<!DOCTYPE html>
		<html lang="es">
		<head>
		<meta charset="UTF-8">
		<title>Certificado de Voluntariado</title>
		<style>
		@page { margin: 25px; }
		body { font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; color: #333; background: #f4f7f6; }
		.certificado {
			max-width: 750px; margin: 20px auto; background: #fff;
			border: 3px solid #320028; border-radius: 16px; overflow: hidden;
			box-shadow: 0 12px 30px rgba(50, 0, 40, 0.25);
			position: relative;
		}
		.certificado::before {
			content: ""; position: absolute; top: -70px; right: -70px;
			width: 220px; height: 220px; border-radius: 50%;
			background: linear-gradient(135deg, rgba(255, 135, 0, 0.16) 0%, rgba(255, 135, 0, 0) 70%);
			pointer-events: none;
		}
		.header {
			background: #320028; color: #fff; text-align: center;
			padding: 26px 20px 22px; border-bottom: 4px solid #ff8700;
			position: relative;
		}
		.header .logo { font-size: 30px; font-weight: 900; letter-spacing: 3px; text-transform: uppercase; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.25); }
		.header .logo-org { font-size: 12px; letter-spacing: 1.5px; color: #ffc680; text-transform: uppercase; margin-top: 4px; }
		.cert-badge {
			display: inline-block; margin-top: 14px; background: #ff8700; color: #fff;
			padding: 6px 18px; border-radius: 30px; font-size: 12px; font-weight: 800;
			text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 10px rgba(255, 135, 0, 0.35);
		}
		h1 { font-size: 20px; color: #320028; text-align: center; letter-spacing: 0.5px; margin: 26px 0 4px; text-transform: uppercase; }
		.contenido { padding: 0 34px 10px; font-size: 16px; line-height: 1.6; }
		.contenido p { text-align: center; margin: 10px 0; }
		.nombre { font-size: 22px; font-weight: 800; color: #320028; text-transform: uppercase; letter-spacing: 1px; }
		.horas { color: #ff8700; font-weight: 800; font-size: 18px; }
		.plan-line { color: #666; }
		.plan-line strong { color: #320028; }
		h3 { color: #320028; font-size: 13px; letter-spacing: 1.5px; text-transform: uppercase; text-align: center; margin: 24px 0 12px; }
		.proyectos { margin: 0 0 8px; padding: 14px 18px; background: #fdf3e9; border: 1px solid #ffe0c2; border-radius: 10px; }
		.proyecto { margin: 7px 0; color: #4a4a4a; text-align: left; }
		.proyecto strong { color: #320028; }
		.proyecto small { color: #888; }
		.sin-proyectos { color: #888; font-style: italic; text-align: center; }
		.footer { margin-top: 18px; border-top: 1px solid #320028; padding: 16px 0 0; background: #faf6f4; }
		.footer-table { width: 100%; border-collapse: collapse; }
		.footer-table td { padding: 0 34px; vertical-align: middle; }
		.footer-left { font-size: 12px; color: #666; line-height: 1.5; }
		.footer-left strong { color: #320028; }
		.qr-cell { text-align: right; }
		.qr { display: inline-block; width: 85px; height: 85px; background: #fff; padding: 6px; border-radius: 12px; box-shadow: 0 6px 16px rgba(50, 0, 40, 0.25); }
		.qr img, .qr svg { width: 100%; height: 100%; display: block; }
		.cert-id { font-size: 11px; color: #999; margin-top: 4px; }
		.verify-url { font-size: 10px; color: #999; text-align: center; margin: 8px 34px 16px; word-break: break-all; }
		' . ( $light ? '
		/* ── Tema claro: cabecera clara con nombre en púrpura ── */
		.header {
			background: #ffffff;
			border: 1px solid rgba(50, 0, 40, 0.08);
			border-bottom: 4px solid #ff8700;
		}
		.header .logo { color: #320028; text-shadow: none; }
		.header .logo-org { color: #b05a2e; }
		.certificado { box-shadow: 0 12px 30px rgba(50, 0, 40, 0.12); }
		.footer { background: #fbf7f4; }
		' : '' ) . '
		</style>
		</head>
		<body>
		<div class="certificado">
		<div class="header">
		    <div class="logo">' . esc_html( get_bloginfo( 'name' ) ) . '</div>
		    <div class="cert-badge">Certificado de Voluntariado</div>
		</div>
		<h1>Certificado de Voluntariado</h1>
		<div class="contenido">
		    <p>Certificamos que</p>
		    <p><span class="nombre">' . esc_html( $nombre ) . '</span></p>
		    <p>ha completado un total de <span class="horas">' . number_format( $horas, 1 ) . ' horas</span> de voluntariado</p>
		    <p class="plan-line">como parte del plan <strong>' . ( $plan ? esc_html( $plan ) : 'Voluntariado General' ) . '</strong></p>

		    <h3>Proyectos Participados</h3>
		    <div class="proyectos">' . ( $proyectos_html ?: '<p class="sin-proyectos">Voluntariado en diversas actividades</p>' ) . '</div>
		</div>
		<div class="footer">
		    <table class="footer-table"><tr>
		        <td class="footer-left">
		            <div>Fecha de emisión: <strong>' . wp_date( 'd/m/Y' ) . '</strong></div>
		            <div>Válido hasta: <strong>' . esc_html( $valido_hasta ? wp_date( 'd/m/Y', strtotime( $valido_hasta ) ) : '—' ) . '</strong> <span style="color:#999">(' . esc_html( sprintf( _n( '%d año', '%d años', self::validity_years(), 'convoca-members' ), self::validity_years() ) ) . ')</span></div>
		            <div class="cert-id">ID: ' . esc_html( $cert_id ) . '</div>
		        </td>
		        <td class="qr-cell"><div class="qr">' . self::build_qr_svg( $verify_url ) . '</div></td>
		    </tr></table>
		</div>
		<div class="verify-url">Verificar en: ' . esc_html( $verify_url ) . '</div>
		</div>
		</body>
		</html>';
	}

	public static function serve_pdf( int $miembro_id, string $theme = '' ): void {
		$cert_id = get_post_meta( $miembro_id, '_convoca_certificado_id', true );

		if ( ! $cert_id ) {
			$result = self::generate( $miembro_id, $theme );
			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}
			$cert_id = $result['id'];
		}

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="certificado-' . $cert_id . '.pdf"' );

		$result = self::generate( $miembro_id );
		if ( ! is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF content, cannot be escaped.
			echo $result['pdf'];
		}
		exit;
	}

	public static function verify( string $cert_id ): ?array {
		global $wpdb;

		$miembro_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_convoca_certificado_id' AND meta_value = %s LIMIT 1",
				$cert_id
			)
		);

		if ( ! $miembro_id ) {
			return null;
		}

		$miembro = get_post( $miembro_id );

		$emitido = get_post_meta( $miembro_id, '_convoca_certificado_emitido', true );
		$valido_hasta = get_post_meta( $miembro_id, '_convoca_certificado_valido_hasta', true );
		if ( empty( $valido_hasta ) && $emitido ) {
			// Certificados anteriores a la política D13: validez desde emisión.
			$valido_hasta = wp_date( 'Y-m-d H:i:s', strtotime( $emitido ) + ( self::validity_years() * YEAR_IN_SECONDS ) );
		}
		$caducado = ! empty( $valido_hasta ) && strtotime( $valido_hasta ) < time();

		return array(
			'nombre'         => $miembro->post_title,
			'horas'          => Voluntariado_Manager::get_horas_aprobadas( $miembro_id ),
			'certificado_id' => $cert_id,
			'emitido'        => $emitido,
			'valido_hasta'   => $valido_hasta ?: null,
			'estado'         => $caducado ? 'caducado' : 'vigente',
		);
	}
}
