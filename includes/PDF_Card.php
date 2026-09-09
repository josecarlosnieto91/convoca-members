<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Generates Member Card (PDF/Printable).
 * Currently implements a high-fidelity HTML print view.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PDF_Card {


	/**
	 * Generate HTML for the member card.
	 *
	 * @param int    $post_id Member post ID.
	 * @param string $theme   'light'|'dark'. Default: opción global convoca_document_theme.
	 */
	public static function get_html( int $post_id, string $theme = '' ): string {
		$theme             = in_array( $theme, array( 'light', 'dark' ), true ) ? $theme : \Convoca\Core\Utils::get_document_theme( 'card' );
		$light             = 'light' === $theme;
		$nombre            = get_the_title( $post_id );
		$num_socio         = get_post_meta( $post_id, '_convoca_numero_socio', true );
		$num_socio_display = $num_socio ? str_pad( $num_socio, 4, '0', STR_PAD_LEFT ) : esc_html__( 'PENDIENTE', 'convoca-members' );

		$plan_key  = get_post_meta( $post_id, '_convoca_plan', true );
		$plan_data = CPT_Miembro::get_plan( $plan_key ?: '' );
		$plan      = ( $plan_data && isset( $plan_data['label'] ) ) ? $plan_data['label'] : esc_html__( 'Socio/a', 'convoca-members' );

		$fecha     = get_post_meta( $post_id, '_convoca_fecha_alta', true );
		$fecha_fmt = $fecha ? wp_date( 'd/m/Y', strtotime( $fecha ) ) : wp_date( 'd/m/Y', strtotime( get_the_date( 'Y-m-d', $post_id ) ) );

		// Seniority badge (D3): whole years since registration (fecha_alta,
		// preserved on reactivation). Hidden for members with < 1 year.
		$fecha_alta_raw  = $fecha ? $fecha : get_the_date( 'Y-m-d', $post_id );
		$seniority_ts    = $fecha_alta_raw ? strtotime( $fecha_alta_raw ) : 0;
		$anios           = $seniority_ts > 0 ? max( 0, (int) floor( ( time() - $seniority_ts ) / YEAR_IN_SECONDS ) ) : 0;
		$seniority_badge = $anios >= 1
			? '<div class="seniority-badge">' . esc_html(
				sprintf(
					/* translators: %d: number of years as a member */
					_n( '%d año', '%d años', $anios, 'convoca-members' ),
					$anios
				)
			) . '</div>'
			: '';

		$logo_style = $light ? 'height: 45px; width: auto; margin: 0; font-size: 24px; color: #320028;' : 'height: 45px; width: auto; color: #fff; margin: 0; font-size: 24px;';
		$logo_html  = \Convoca\Core\Utils::get_branding_html( 'members', '', $logo_style );

		$verification_hash = hash_hmac( 'sha256', 'member_' . $post_id, \Convoca\Core\Utils::get_persistent_salt() );
		$site_domain       = strtoupper( wp_parse_url( home_url(), PHP_URL_HOST ) );
		$verify_url        = home_url( '/verificar-socio/?id=' . $post_id . '&token=' . $verification_hash );

		// QR local (E2E-7): generar con chillerlan como Certificate_Generator,
		// sin depender de api.qrserver.com (API externa / filtración de datos).
		$qr_img = self::qr_data_uri( $verify_url, 150 );

		return '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>' . esc_html__( 'Tarjeta Socio', 'convoca-members' ) . ' #' . esc_html( $num_socio_display ) . '</title>
            <style>
                @media print {
                    body { margin: 0; padding: 0; }
                    .no-print { display: none; }
                }
                body { 
                    font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
                    background: #f4f7f6; 
                    display: flex; 
                    flex-direction: column; 
                    align-items: center; 
                    justify-content: center; 
                    min-height: 100vh;
                    margin: 0;
                    color: #333;
                }
                .card {
                    width: 450px; 
                    height: 280px;
                    border-radius: 20px;
                    background: #320028;
                    color: #fff;
                    position: relative;
                    box-shadow: 0 15px 35px rgba(50, 0, 40, 0.4);
                    padding: 30px;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    overflow: hidden;
                    box-sizing: border-box;
                    border: 1px solid rgba(255,255,255,0.1);
                }
                .card::before {
                    content: "";
                    position: absolute;
                    top: -60px;
                    right: -60px;
                    width: 200px;
                    height: 200px;
                    background: linear-gradient(135deg, rgba(255, 135, 0, 0.2) 0%, rgba(255, 135, 0, 0) 70%);
                    border-radius: 50%;
                }
                .card::after {
                    content: "";
                    position: absolute;
                    bottom: -30px;
                    left: -30px;
                    width: 120px;
                    height: 120px;
                    background: rgba(157, 78, 221, 0.1);
                    border-radius: 50%;
                }
                .header { display: flex; justify-content: space-between; align-items: flex-start; z-index: 1; }
                .header-right { display: flex; align-items: center; gap: 8px; }
                .header img { max-height: 45px; width: auto; display: block; }
                .logo-container { display: flex; align-items: center; gap: 10px; }
                .logo-text { font-size: 24px; font-weight: 900; letter-spacing: 2px; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
                .plan-badge {
                    background: #ff8700;
                    color: #fff;
                    padding: 6px 16px;
                    border-radius: 30px;
                    font-size: 11px;
                    font-weight: 800;
                    text-transform: uppercase;
                    box-shadow: 0 4px 10px rgba(255, 135, 0, 0.3);
                    letter-spacing: 0.5px;
                }
                .seniority-badge {
                    background: rgba(255,255,255,0.16);
                    border: 1px solid rgba(255,255,255,0.35);
                    color: #fff;
                    padding: 6px 12px;
                    border-radius: 30px;
                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 0.5px;
                    white-space: nowrap;
                }
                .body { margin-top: 20px; z-index: 1; }
                .member-number { 
                    font-family: "Courier New", monospace; 
                    font-size: 26px; 
                    letter-spacing: 5px; 
                    margin-bottom: 8px; 
                    color: #ff8700;
                    font-weight: bold;
                }
                .member-name { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
                .footer { display: flex; justify-content: space-between; align-items: flex-end; z-index: 1; }
                .info { font-size: 11px; opacity: 0.9; line-height: 1.4; }
                .qr-code { 
                    width: 75px; 
                    height: 75px; 
                    background: #fff; 
                    padding: 6px; 
                    border-radius: 12px; 
                    box-shadow: 0 8px 20px rgba(0,0,0,0.3);
                }
                .qr-code img { width: 100%; height: 100%; }
                .btn-print {
                    margin-top: 30px;
                    background: #ff8700;
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    transition: all 0.3s ease;
                }
                .btn-print:hover { background: #e67a00; transform: translateY(-2px); }
                ' . ( $light ? '
                /* ── Tema claro: tarjeta blanca/crema con textos púrpura ── */
                body { background: #f7f3f0; }
                .card {
                    background: #ffffff;
                    color: #320028;
                    border: 1px solid rgba(50, 0, 40, 0.18);
                    box-shadow: 0 15px 35px rgba(50, 0, 40, 0.14);
                }
                .card::before { background: linear-gradient(135deg, rgba(255, 135, 0, 0.16) 0%, rgba(255, 135, 0, 0) 70%); }
                .card::after { background: rgba(157, 78, 221, 0.07); }
                .logo-text, .header h1, .header .logo-text { color: #320028; text-shadow: none; }
                .member-name { color: #320028; }
                .info { color: #5c4250; opacity: 1; }
                .qr-code { box-shadow: 0 8px 20px rgba(50, 0, 40, 0.12); }
                .seniority-badge {
                    background: rgba(50, 0, 40, 0.06);
                    border: 1px solid rgba(50, 0, 40, 0.28);
                    color: #320028;
                }
                ' : '' ) . '
            </style>
        </head>
        <body>
            <div class="card">
                <div class="header">
                    ' . $logo_html . '
                    <div class="header-right">
                        ' . $seniority_badge . '
                        <div class="plan-badge">' . esc_html( $plan ) . '</div>
                    </div>
                </div>
                
                <div class="body">
                    <div class="member-number">' . esc_html__( 'NO.', 'convoca-members' ) . ' ' . esc_html( $num_socio_display ) . '</div>
                    <div class="member-name">' . esc_html( $nombre ) . '</div>
                </div>
                
                <div class="footer">
                    <div class="info">
                        <div>' . esc_html__( 'FECHA DE ALTA:', 'convoca-members' ) . ' ' . esc_html( $fecha_fmt ) . '</div>
                        <div style="margin-top:4px;">WWW.' . esc_html( $site_domain ) . '</div>
                    </div>
                    <div class="qr-code">
                        ' . $qr_img . '
                    </div>
                </div>
            </div>
            
            <button class="btn-print no-print" onclick="window.print()">
                ' . esc_html__( 'IMPRIMIR / GUARDAR PDF', 'convoca-members' ) . '
            </button>
            <p class="no-print" style="margin-top:15px; color:#666; font-size:13px;">
                ' . esc_html__( 'Se abrirá el diálogo de impresión. Elige "Guardar como PDF" como destino.', 'convoca-members' ) . '
            </p>
        </body>
        </html>
        ';
	}

	/**
	 * Generate a PDF for the member card using Signature (Dompdf).
	 *
	 * @param int    $post_id Member post ID.
	 * @param string $theme   'light'|'dark'. Default: opción global convoca_document_theme.
	 * @return string PDF binary content.
	 */
	public static function generate_pdf( int $post_id, string $theme = '' ): string {
		$html     = self::get_html( $post_id, $theme );
		$tmp_path = wp_tempnam( 'member-card-' ) . '.pdf';

		$signature = new \Convoca\Core\Signature();
		$result    = $signature->generate_pdf(
			$html,
			array(),
			$tmp_path,
			array(
				'isRemoteEnabled' => true,
			)
		);

		if ( ! $result || ! file_exists( $result ) ) {
			throw new \RuntimeException( esc_html( $signature->get_last_error() ) ?: 'Error al generar el PDF.' );
		}

		$pdf_content = file_get_contents( $result );
		wp_delete_file( $result );
		return $pdf_content;
	}

	/**
	 * Generate a QR code image as a local data-URI (no external API).
	 *
	 * Reuses chillerlan/php-qrcode when available (same as Certificate_Generator),
	 * with a graceful text fallback so the card never breaks.
	 *
	 * NOTE: el <img> NO lleva style de tamaño inline: PDF_Card lo inserta dentro
	 * de .qr-code (75px) cuyo CSS `.qr-code img { width:100%; height:100%; }`
	 * lo escala; un width inline ganaría y desbordaría la caja.
	 *
	 * @param string $data Content to encode (verification URL).
	 * @param int    $size  Render scale hint (used only for the PNG pixels).
	 */
	private static function qr_data_uri( string $data, int $size = 150 ): string {
		if (
			class_exists( '\chillerlan\QRCode\QRCode' )
			&& class_exists( '\chillerlan\QRCode\QROptions' )
			&& class_exists( '\chillerlan\QRCode\Output\QRGdImagePNG' )
		) {
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
				// Sin width/height inline: el CSS contenedor (.qr-code) escala el QR.
				return '<img src="data:image/png;base64,' . base64_encode( $png ) . '" alt="' . esc_attr__( 'QR Verification', 'convoca-members' ) . '" />';
			} catch ( \Throwable $e ) {
				\Convoca\Core\Logger::warning( 'QR local del carnet falló: ' . $e->getMessage(), 'Members/Card' );
			}
		}

		// Fallback: texto legible con la URL de verificación (rellena la caja contenedora).
		return '<div style="width:100%;height:100%;background:#fff;color:#333;display:flex;align-items:center;justify-content:center;font-size:9px;padding:4px;text-align:center;word-break:break-all;box-sizing:border-box;">' . esc_html( $data ) . '</div>';
	}
}
