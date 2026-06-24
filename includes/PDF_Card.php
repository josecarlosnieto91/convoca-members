<?php
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
	 */
	public static function get_html( int $post_id ): string {
		$nombre            = get_the_title( $post_id );
		$num_socio         = get_post_meta( $post_id, '_conv_numero_socio', true );
		$num_socio_display = $num_socio ? str_pad( $num_socio, 4, '0', STR_PAD_LEFT ) : esc_html__( 'PENDIENTE', 'convoca-members' );

		$plan_key  = get_post_meta( $post_id, '_conv_plan', true );
		$plan_data = CPT_Miembro::get_plan( $plan_key ?: '' );
		$plan      = ( $plan_data && isset( $plan_data['label'] ) ) ? $plan_data['label'] : esc_html__( 'Socio/a', 'convoca-members' );

		$fecha     = get_post_meta( $post_id, '_conv_fecha_alta', true );
		$fecha_fmt = $fecha ? wp_date( 'd/m/Y', strtotime( $fecha ) ) : wp_date( 'd/m/Y', strtotime( get_the_date( 'Y-m-d', $post_id ) ) );

		$logo_html = \Convoca\Core\Utils::get_branding_html( 'members', '', 'height: 45px; width: auto; color: #fff; margin: 0; font-size: 24px;' );

		$verification_hash = hash_hmac( 'sha256', 'member_' . $post_id, \Convoca\Core\Utils::get_persistent_salt() );
		$site_domain = strtoupper( parse_url( home_url(), PHP_URL_HOST ) );
		$qr_url            = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode( home_url( '/verificar-socio/?id=' . $post_id . '&token=' . $verification_hash ) );

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
                    background: linear-gradient(135deg, #4b0082 0%, #320028 100%);
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
            </style>
        </head>
        <body>
            <div class="card">
                <div class="header">
                    ' . $logo_html . '
                    <div class="plan-badge">' . esc_html( $plan ) . '</div>
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
                        <img src="' . esc_url( $qr_url ) . '" alt="' . esc_attr__( 'QR Verification', 'convoca-members' ) . '">
                    </div>
                </div>
            </div>
            
            <button class="btn-print no-print" onclick="window.print()">
                📄 ' . esc_html__( 'Imprimir / Guardar como PDF', 'convoca-members' ) . '
            </button>
            <p class="no-print" style="margin-top:15px; color:#666; font-size:13px;">
                ' . esc_html__( 'Se abrirá el diálogo de impresión. Elige "Guardar como PDF" como destino.', 'convoca-members' ) . '
            </p>
        </body>
        </html>
        ';
	}

	/**
	 * Get the dynamic URL for the reservation panel.
	 */
	private static function get_panel_url(): string {
		$panel_page_id = (int) get_option( 'convoca_enroll_panel_reservas_page_id' );
		if ( $panel_page_id ) {
			return get_permalink( $panel_page_id );
		}

		global $wpdb;
		$page_id = $wpdb->get_var(
			"
            SELECT ID FROM {$wpdb->posts} 
            WHERE post_content LIKE '%[convoca_panel_reservas]%' 
            AND post_status = 'publish' 
            AND post_type = 'page'
            LIMIT 1
        "
		);

		if ( $page_id ) {
			update_option( 'convoca_enroll_panel_reservas_page_id', $page_id );
			return get_permalink( $page_id );
		}

		return home_url( '/' );
	}

	/**
	 * Generate a PDF for the member card using CONV_Signature (Dompdf).
	 *
	 * @param int $post_id Member post ID.
	 * @return string PDF binary content.
	 */
	public static function generate_pdf( int $post_id ): string {
		$html     = self::get_html( $post_id );
		$tmp_path = wp_tempnam( 'member-card-' ) . '.pdf';

		$signature = new \Convoca\Core\CONV_Signature();
		$result    = $signature->generate_pdf(
			$html,
			array(),
			$tmp_path,
			array(
				'isRemoteEnabled' => true,
			)
		);

		if ( ! $result || ! file_exists( $result ) ) {
			throw new \RuntimeException( $signature->get_last_error() ?: 'Error al generar el PDF.' );
		}

		$pdf_content = file_get_contents( $result );
		unlink( $result );
		return $pdf_content;
	}
}
