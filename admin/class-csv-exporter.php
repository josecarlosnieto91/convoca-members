<?php

/**
 * Convoca Members
 *
 * @package    Convoca\Members
 * @subpackage Admin
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
 * CSV exporter for member data — configurable columns via modal.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CSV_Exporter {


	/**
	 * All available export columns.
	 *
	 * key => label for display in the modal.
	 *
	 * @return array<string, string>
	 */
	public static function get_available_columns(): array {
		return array(
			'ID'                 => 'ID',
			'Nombre'             => __( 'Nombre', 'convoca-members' ),
			'Email'              => __( 'Email', 'convoca-members' ),
			'DNI'                => __( 'DNI', 'convoca-members' ),
			'Teléfono'          => __( 'Teléfono', 'convoca-members' ),
			'WhatsApp'           => __( 'WhatsApp', 'convoca-members' ),
			'Dirección'         => __( 'Dirección', 'convoca-members' ),
			'Municipio'          => __( 'Municipio', 'convoca-members' ),
			'Código Postal'     => __( 'Código Postal', 'convoca-members' ),
			'Provincia'          => __( 'Provincia', 'convoca-members' ),
			'Tipo'               => __( 'Tipo', 'convoca-members' ),
			'Estado'             => __( 'Estado', 'convoca-members' ),
			'Plan'               => __( 'Plan', 'convoca-members' ),
			'Sub Plan'           => __( 'Sub Plan', 'convoca-members' ),
			'Forma pago'         => __( 'Forma pago', 'convoca-members' ),
			'Menor'              => __( 'Menor edad', 'convoca-members' ),
			'RGPD versión'      => __( 'RGPD versión', 'convoca-members' ),
			'RGPD fecha'         => __( 'RGPD fecha', 'convoca-members' ),
			'Comunicaciones'     => __( 'Comunicaciones', 'convoca-members' ),
			'Fecha alta'         => __( 'Fecha alta', 'convoca-members' ),
			'Fecha renovación'  => __( 'Fecha renovación', 'convoca-members' ),
			'Recurrente'         => __( 'Recurrente', 'convoca-members' ),
			'Número socio'      => __( 'Número socio', 'convoca-members' ),
			'Código acceso'     => __( 'Código acceso', 'convoca-members' ),
			'Voluntario'         => __( 'Voluntario', 'convoca-members' ),
			'Horas voluntariado' => __( 'Horas voluntariado', 'convoca-members' ),
			'Notas'              => __( 'Notas', 'convoca-members' ),
			'Intereses'          => __( 'Intereses', 'convoca-members' ),
			'Disponibilidad'     => __( 'Disponibilidad', 'convoca-members' ),
			'Tipo voluntariado'  => __( 'Tipo voluntariado', 'convoca-members' ),
		);
	}

	/**
	 * Default columns (the original 19).
	 *
	 * @return array<string>
	 */
	public static function get_default_columns(): array {
		return array(
			'ID',
			'Nombre',
			'Email',
			'DNI',
			'Teléfono',
			'WhatsApp',
			'Dirección',
			'Municipio',
			'Tipo',
			'Estado',
			'Plan',
			'Forma pago',
			'Menor',
			'RGPD versión',
			'RGPD fecha',
			'Comunicaciones',
			'Fecha alta',
			'Fecha renovación',
			'Recurrente',
		);
	}

	/**
	 * Get the last used column selection for the current user.
	 *
	 * @return array<string>|null  Null means "never saved" (show all).
	 */
	public static function get_user_columns(): ?array {
		$saved = get_user_meta( get_current_user_id(), '_convoca_csv_columns', true );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return null;
		}
		return $saved;
	}

	public function __construct() {
		add_action( 'wp_ajax_convoca_export_csv', array( $this, 'export' ) );
		add_action( 'wp_ajax_convoca_save_csv_columns', array( $this, 'ajax_save_columns' ) );
	}

	/**
	 * AJAX handler: export CSV with optional column selection.
	 *
	 * Expects GET params:
	 *   nonce          – security nonce
	 *   columns        – (optional) comma-separated column keys
	 *   estado         – (optional) filter by member state
	 */
	public function export(): void {
		check_ajax_referer( 'convoca_export_csv', 'nonce' );

		if ( ! current_user_can( 'convoca_export_members' ) ) {
			wp_die(
				esc_html__( 'No tienes permisos suficientes para exportar la lista de miembros.', 'convoca-members' ),
				esc_html__( 'Acceso Denegado', 'convoca-members' ),
				array( 'back_link' => true )
			);
		}

		$all_columns = self::get_available_columns();
		$all_keys    = array_keys( $all_columns );

		// Determine which columns to export.
		$raw_columns = sanitize_text_field( $_GET['columns'] ?? '' );
		if ( $raw_columns ) {
			$requested = array_map( 'trim', explode( ',', $raw_columns ) );
			$columns   = array_values( array_intersect( $requested, $all_keys ) );
		} else {
			$columns = $all_keys; // all columns.
		}

		if ( empty( $columns ) ) {
			$columns = $all_keys;
		}

		$args = array(
			'post_type'      => 'miembro',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Optional estado filter.
		$estado = sanitize_text_field( $_GET['estado'] ?? '' );
		if ( $estado ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_convoca_estado_miembro',
					'value' => $estado,
				),
			);
		}

		$query = new \WP_Query( $args );

		$filename = 'convoca-miembros-' . wp_date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV streaming to php://output.

		// Write BOM for Excel.
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Write header row.
		$header_labels = array();
		foreach ( $columns as $key ) {
			$header_labels[] = $all_columns[ $key ];
		}
		fputcsv( $out, $header_labels, ';' );

		foreach ( $query->posts as $post ) {
			$row = $this->build_row( $post, $columns );
			fputcsv( $out, $row, ';' );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- CSV streaming to php://output.
		exit;
	}

	/**
	 * Build a single CSV row for the given set of columns.
	 *
	 * @param \WP_Post      $post
	 * @param array<string> $columns
	 * @return array<string>
	 */
	private function build_row( \WP_Post $post, array $columns ): array {
		$meta  = fn( string $key ) => get_post_meta( $post->ID, '_convoca_' . $key, true );
		$esc   = fn( $v ) => \Convoca\Core\Utils::escape_csv_field( $v );
		$terms = wp_get_object_terms( $post->ID, 'tipo_miembro', array( 'fields' => 'names' ) );
		$tipo  = is_wp_error( $terms ) ? '' : implode( ', ', $terms );

		$row = array();

		foreach ( $columns as $col ) {
			$row[] = match ( $col ) {
				'ID'                 => (string) $post->ID,
				'Nombre'             => $esc( $post->post_title ),
				'Email'              => $esc( $meta( 'email' ) ),
				'DNI'                => $esc( $meta( 'dni' ) ),
				'Teléfono'           => $esc( $meta( 'telefono' ) ),
				'WhatsApp'           => $esc( $meta( 'whatsapp' ) ),
				'Dirección'          => $esc( $meta( 'direccion' ) ),
				'Municipio'          => $esc( $meta( 'municipio' ) ),
				'Código Postal'      => $esc( $meta( 'c_postal' ) ),
				'Provincia'          => $esc( $meta( 'provincia' ) ),
				'Tipo'               => $esc( $tipo ),
				'Estado'             => Estados::LABELS[ $meta( 'estado_miembro' ) ] ?? $meta( 'estado_miembro' ),
				'Plan'               => $esc( $meta( 'plan' ) ),
				'Sub Plan'           => $esc( $meta( 'sub_plan' ) ),
				'Forma pago'         => $esc( $meta( 'forma_pago' ) ),
				'Menor'              => $meta( 'menor_edad' ) === '1' ? 'Sí' : 'No',
				'RGPD versión'       => $esc( $meta( 'rgpd_version' ) ),
				'RGPD fecha'         => $esc( $meta( 'rgpd_timestamp' ) ),
				'Comunicaciones'     => $meta( 'comunicaciones_ok' ) === '1' ? 'Sí' : 'No',
				'Fecha alta'         => get_the_date( 'd/m/Y', $post ),
				'Fecha renovación'   => $esc( $meta( 'fecha_renovacion' ) ),
				'Recurrente'         => $meta( 'pago_recurrente' ) === '1' ? 'Sí' : 'No',
				'Número socio'       => $esc( $meta( 'numero_socio' ) ),
				'Código acceso'      => $esc( $meta( 'access_code' ) ),
				'Voluntario'         => $meta( 'voluntario' ) === '1' ? 'Sí' : 'No',
				'Horas voluntariado' => $esc( $meta( 'horas_voluntariado' ) ),
				'Notas'              => $esc( $meta( 'notas' ) ),
				'Intereses'          => $esc( $meta( 'intereses' ) ),
				'Disponibilidad'     => $esc( $meta( 'disponibilidad' ) ),
				'Tipo voluntariado'  => $esc( $meta( 'tipo_voluntariado' ) ),
				default              => '',
			};
		}

		return $row;
	}

	/**
	 * AJAX handler: save selected columns as user preference.
	 *
	 * POST params:
	 *   nonce   – security nonce
	 *   columns – JSON array of column key strings
	 */
	public function ajax_save_columns(): void {
		check_ajax_referer( 'convoca_export_csv', 'nonce' );

		if ( ! current_user_can( 'convoca_export_members' ) ) {
			wp_send_json_error( __( 'Sin permisos.', 'convoca-members' ) );
		}

		$raw     = isset( $_POST['columns'] ) ? wp_unslash( $_POST['columns'] ) : '';
		$columns = json_decode( $raw, true );

		if ( ! is_array( $columns ) ) {
			wp_send_json_error( __( 'Formato inválido.', 'convoca-members' ) );
		}

		$all_keys = array_keys( self::get_available_columns() );
		$valid    = array_values( array_intersect( $columns, $all_keys ) );

		update_user_meta( get_current_user_id(), '_convoca_csv_columns', $valid );

		wp_send_json_success( array( 'columns' => $valid ) );
	}
}
