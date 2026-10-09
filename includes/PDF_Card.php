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
	 * @param string $theme   'light'|'dark'.
	 * @param bool   $para_pdf true cuando el HTML va a Dompdf y no a un navegador. Dompdf ignora
	 *                         `@media print`, así que el botón de imprimir se colaba DENTRO del PDF
	 *                         (una tarjeta con un botón dentro) y la página salía en A4 con la
	 *                         tarjeta flotando en medio. En modo PDF: sin botón, y la página es la
	 *                         tarjeta. El botón sigue estando para quien abre la vista en el navegador.
	 */
	public static function get_html( int $post_id, string $theme = '', bool $para_pdf = false ): string {
		$theme             = in_array( $theme, array( 'light', 'dark' ), true ) ? $theme : \Convoca\Core\Utils::get_document_theme( 'card' );
		$light             = 'light' === $theme;
		$nombre            = get_the_title( $post_id );

		// Un nombre largo no cabe en dos líneas del carnet: en vez de dejar que se desborde por
		// debajo (se cruzaba con el pie y el orden del texto se rompía), se reduce el tamaño. El
		// caso está probado con un nombre de 107 caracteres.
		$largo_nombre = mb_strlen( (string) $nombre );
		if ( $largo_nombre > 40 ) {
			$clase_nombre = 'member-name member-name--largo';
		} elseif ( $largo_nombre > 24 ) {
			$clase_nombre = 'member-name member-name--medio';
		} else {
			$clase_nombre = 'member-name';
		}

		$num_socio         = get_post_meta( $post_id, '_convoca_numero_socio', true );
		$num_socio_display = $num_socio ? str_pad( $num_socio, 4, '0', STR_PAD_LEFT ) : esc_html__( 'PENDIENTE', 'convoca-members' );

		$plan_key  = get_post_meta( $post_id, '_convoca_plan', true );
		$plan_data = CPT_Miembro::get_plan( $plan_key ?: '' );
		$plan      = ( $plan_data && isset( $plan_data['label'] ) ) ? $plan_data['label'] : esc_html__( 'Socio/a', 'convoca-members' );

		// Dompdf solo lleva Helvetica: un emoji del plan no tiene glifo y sale un «?» en la tarjeta
		// (p. ej. «🏅 BRONCE» → «? BRONCE»). En el PDF se quita; en el navegador se queda.
		if ( $para_pdf ) {
			$plan = Texto_PDF::sin_emoji( (string) $plan );
		}

		// Uno mismo: una modalidad con el nombre largo (p. ej. «Modalidad Familiar Juvenil de
		// Busgosu») se comía la cabecera y tapaba el nombre de la organización. A partir de 15
		// caracteres el distintivo se aprieta para que quepa al lado del logo junto a la insignia de
		// antigüedad; medido: con 36 caracteres cabe en una línea.
		$clase_plan = mb_strlen( (string) $plan ) > 15 ? 'plan-badge plan-badge--largo' : 'plan-badge';
		// Con una etiqueta larga, la modalidad no cabe en la línea del logo: en el PDF ocupa su
		// propia línea y el cuerpo y el pie bajan (hay sitio en los 280 px). Lo manda esta clase en
		// la tarjeta, para no repartir la decisión entre tres reglas.
		$clase_cabecera = 'header';
		// Solo en el PDF: la clase lleva reglas de colocación (posiciones absolutas) que en el
		// navegador no aplican, porque allí la cabecera la reparte el flexbox y funciona con
		// cualquier etiqueta.
		$clase_tarjeta = ( $para_pdf && mb_strlen( (string) $plan ) > 15 ) ? 'card card--plan-largo' : 'card';

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
            <style>' . Estilos_PDF::base() . '
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
                /* Una modalidad con el nombre largo se comía la cabecera: el distintivo ocupaba la
                   línea entera y el logo caía debajo, tapado. Con la etiqueta larga se aprieta. */
                .plan-badge--largo { font-size: 7.5px; padding: 4px 8px; letter-spacing: 0; }
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
                /* Nombres largos: se ajusta el tamaño para que quepan en el carnet (si no, se
                   desbordaban por debajo y se cruzaban con el pie). También en el navegador. */
                .member-name--medio { font-size: 16px; letter-spacing: 0.5px; line-height: 1.15; }
                .member-name--largo { font-size: 13px; letter-spacing: 0.2px; line-height: 1.05; }
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
                ' : '' ) . ( $para_pdf ? '
                /* Modo PDF: la página ES la tarjeta (450x280 px = 119x74 mm), en vez del A4 que
                   dompdf pone por defecto y que dejaba la tarjeta flotando en medio del folio. */
                @page { size: 450px 280px; margin: 0; }
                body { min-height: auto; padding: 0; }
                /* dompdf NO respeta `box-sizing: border-box`: le SUMA el padding (30px x2) y el borde
                   (1px x2) a las medidas, así que la tarjeta se le iba a 510x340 y no cabía en un
                   folio de 450x280 — de ahí la segunda página. Aquí se le dan las medidas del
                   CONTENIDO (450-60-2 y 280-60-2), y así el total vuelve a ser la tarjeta de
                   450x280 con el mismo espacio interior que en el navegador. */
                .card { box-sizing: content-box; width: 388px; height: 218px; }
                /* Los adornos del fondo (200 px colocados en top:-60px / right:-60px) se salen del
                   folio, y dompdf los tiene en cuenta: añadía una SEGUNDA página vacía. En PDF no
                   se pintan; en el navegador siguen decorando la tarjeta. */
                .card::before, .card::after { display: none; }
                /* dompdf NO sabe hacer flexbox: con `display:flex` las tres zonas de la tarjeta
                   (cabecera, datos y pie) se apilaban en vertical y el QR de 75 px se salía de los
                   280 px de alto, así que el PDF salía con una segunda página. Aquí se sustituye
                   por flotantes, que dompdf sí coloca: las insignias a la derecha de la cabecera y
                   el QR a la derecha del pie. */
                .card, .header, .body, .footer { display: block; }
                /* SIN `overflow: hidden` en cabecera y pie: con flotantes dentro, dompdf calcula el
                   contenedor con altura CERO y recorta su contenido (el logo, las insignias, la fecha
                   y el QR desaparecían del PDF aunque siguieran en la capa de texto). Para bajar el
                   cuerpo debajo de la cabecera ya está el `clear` de abajo. */
                .footer .info { float: left; }
                /* Cabecera del PDF. El navegador la reparte con flexbox, que dompdf no tiene, así que
                   aquí hay dos caminos según lo que ocupe la etiqueta de la modalidad:
                     - corta (lo normal: «Ejemplo», «Deva»): el logo a la izquierda y las insignias a la
                       derecha con un flotante. Medido: queda a la derecha sin solaparse.
                     - larga: los flotantes se metían encima del logo (se comían el nombre de la
                       organización) y las tablas o los absolutos lo desplazaban a la línea de datos.
                       Se deja el flujo normal —logo y luego las insignias, todo en línea— que no
                       depende de nada y nunca se rompe, aunque el reparto quede más simple. */
                .header-right { float: right; }
                /* Modalidad larga: no cabe en la línea del logo, así que ocupa la suya y el resto de
                   la tarjeta baja. Con flotantes dompdf se la comía (tapaba el nombre de la
                   organización); en flujo normal se iba a la línea de los datos. Cada cosa en su
                   línea y las tres zonas recolocadas: legible y sin solapes, que es lo que importa en
                   un documento. */
                .card--plan-largo .header-right { float: none; display: block; margin-top: 4px; text-align: left; }
                .card--plan-largo .body { top: 112px; }
                .card--plan-largo .footer { top: 192px; }
                /* Dentro del grupo de insignias dompdf las apilaba una debajo de otra y la segunda
                   acababa cayendo en la línea de los datos. En PDF van lado a lado, como en el
                   navegador (el `gap` del flex tampoco existe aquí, de ahí el margen). */
                .header-right > * { display: inline-block; margin-left: 6px; }
                /* El logo llega como <h1> (en bloque) y el grupo flotante caía DEBAJO de él. Flotando
                   también el logo, quedan uno al lado del otro, como en el navegador. */
                .header > h1, .header > img, .header > a { float: left; }
                /* Con el logo flotado, el cuerpo se le colaba al lado y el número de socio se iba a
                   la derecha. `clear` lo baja debajo de la cabecera, que es donde va. */
                .body, .footer { clear: both; }
                /* Y las tres zonas se reparten como en el navegador: cabecera arriba, datos en medio
                   y pie abajo. El `justify-content: space-between` del flex no existe en dompdf, así
                   que sin esto el contenido se apelotonaba arriba y quedaba un tercio de tarjeta
                   vacío. Las medidas del contenido son 388x218 (450-60-2 x 280-60-2). */
                .header { position: absolute; top: 30px; left: 30px; width: 388px; }
                .body { position: absolute; top: 80px; left: 30px; width: 388px; }
                .footer { position: absolute; top: 173px; left: 30px; width: 388px; }
                ' : '' ) . '
            </style>
        </head>
        <body>
            <div class="' . esc_attr( $clase_tarjeta ) . '">
                <div class="' . esc_attr( $clase_cabecera ) . '">
                    ' . $logo_html . '
                    <div class="header-right">
                        ' . $seniority_badge . '
                        <div class="' . esc_attr( $clase_plan ) . '">' . esc_html( $plan ) . '</div>
                    </div>
                </div>
                
                <div class="body">
                    <div class="member-number">' . esc_html__( 'NO.', 'convoca-members' ) . ' ' . esc_html( $num_socio_display ) . '</div>
                    <div class="' . esc_attr( $clase_nombre ) . '">' . esc_html( $nombre ) . '</div>
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
            </div>' . ( $para_pdf
				? '</body></html>'
				: '
            <button class="btn-print no-print" onclick="window.print()">
                ' . esc_html__( 'IMPRIMIR / GUARDAR PDF', 'convoca-members' ) . '
            </button>
            <p class="no-print" style="margin-top:15px; color:#666; font-size:13px;">
                ' . esc_html__( 'Se abrirá el diálogo de impresión. Elige "Guardar como PDF" como destino.', 'convoca-members' ) . '
            </p>
        </body>
        </html>' ) . '
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
		// En modo PDF: sin el botón de imprimir (dompdf ignora `@media print` y lo pintaba DENTRO de
		// la tarjeta) y con la página del tamaño de la tarjeta en vez de un A4.
		$html = self::get_html( $post_id, $theme, true );

		// El temporal se crea con `get_temp_dir()` (del core, siempre disponible) y NO con
		// `wp_tempnam()`, que vive en wp-admin/includes/file.php. Generar la tarjeta desde un correo
		// de cron (Email_Manager la adjunta) moría con «Call to undefined function wp_tempnam()»,
		// porque fuera del escritorio ese fichero no está cargado. Nombre único con uniqid().
		$tmp_path = get_temp_dir() . 'convoca-tarjeta-' . uniqid() . '.pdf';

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
