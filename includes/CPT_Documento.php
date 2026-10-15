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
 * Custom Post Type for Documents (Volunteers).
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CPT_Documento {

	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'load-post-new.php', array( $this, 'redirect_default_editor' ) );
		add_action( 'load-post.php', array( $this, 'redirect_default_editor' ) );
		add_filter( 'map_meta_cap', array( $this, 'map_documento_caps' ), 10, 4 );
	}

	public function redirect_default_editor(): void {
		global $typenow;
		if ( $typenow === 'convoca_documento' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=conv-members' ) );
			exit;
		}
	}

	public function register_cpt(): void {
		$labels = array(
			'name'               => _x( 'Documentos', 'Post Type General Name', 'convoca-members' ),
			'singular_name'      => _x( 'Documento', 'Post Type Singular Name', 'convoca-members' ),
			'menu_name'          => __( 'Documentos', 'convoca-members' ),
			'name_admin_bar'     => __( 'Documento', 'convoca-members' ),
			'archives'           => __( 'Archivo de documentos', 'convoca-members' ),
			'attributes'         => __( 'Atributos del documento', 'convoca-members' ),
			'parent_item_colon'  => __( 'Documento superior:', 'convoca-members' ),
			'all_items'          => __( 'Todos los documentos', 'convoca-members' ),
			'add_new_item'       => __( 'Añadir nuevo documento', 'convoca-members' ),
			'add_new'            => __( 'Añadir nuevo', 'convoca-members' ),
			'new_item'           => __( 'Nuevo documento', 'convoca-members' ),
			'edit_item'          => __( 'Editar documento', 'convoca-members' ),
			'update_item'        => __( 'Actualizar documento', 'convoca-members' ),
			'view_item'          => __( 'Ver documento', 'convoca-members' ),
			'view_items'         => __( 'Ver documentos', 'convoca-members' ),
			'search_items'       => __( 'Buscar documento', 'convoca-members' ),
			'not_found'          => __( 'No encontrado', 'convoca-members' ),
			'not_found_in_trash' => __( 'No encontrado en la papelera', 'convoca-members' ),
		);

		$args = array(
			'label'               => __( 'Documento', 'convoca-members' ),
			'description'         => __( 'Documentos generados como el Acuerdo de Incorporación.', 'convoca-members' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'author', 'custom-fields' ),
			'hierarchical'        => false,
			'public'              => false, // Only accessible via code/admin.
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'convoca_documento',
			'map_meta_cap'        => true,
		);

		register_post_type( 'convoca_documento', $args );

		register_post_meta(
			'convoca_documento',
			'_convoca_fecha_caducidad',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => static function ( $valor ) {
					$valor = (string) $valor;

					return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $valor ) ? $valor : '';
				},
			)
		);
	}

	/**
	 * Estado de vigencia de un documento (issue #31).
	 *
	 * La web prometía «control de vigencia» de los documentos (DNI, seguros, autorizaciones) y no existía
	 * nada: el tipo no tenía ni noción de caducidad. La regla vive aquí, en un solo sitio, para que la ficha,
	 * los informes y cualquier aviso digan lo mismo.
	 *
	 * @param int $documento_id Documento.
	 * @return array{estado: string, dias: int|null, fecha: string, etiqueta: string}
	 */
	public static function estado_caducidad( int $documento_id ): array {
		$fecha = (string) get_post_meta( $documento_id, '_convoca_fecha_caducidad', true );
		$vacio = array(
			'estado'   => 'sin_fecha',
			'dias'     => null,
			'fecha'    => '',
			'etiqueta' => __( 'Sin fecha de caducidad', 'convoca-members' ),
		);

		if ( '' === $fecha || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha ) ) {
			return $vacio;
		}

		$hoy   = current_time( 'Y-m-d' );
		$dias  = (int) floor( ( strtotime( $fecha . ' 00:00:00' ) - strtotime( $hoy . ' 00:00:00' ) ) / DAY_IN_SECONDS );
		$aviso = (int) apply_filters( 'convoca_members_dias_aviso_caducidad', 30, $documento_id );

		if ( $dias < 0 ) {
			return array(
				'estado'   => 'caducado',
				'dias'     => $dias,
				'fecha'    => $fecha,
				'etiqueta' => sprintf(
					/* translators: 1: fecha de caducidad, 2: días transcurridos. */
					_n( 'Caducado el %1$s (hace %2$d día)', 'Caducado el %1$s (hace %2$d días)', abs( $dias ), 'convoca-members' ),
					$fecha,
					abs( $dias )
				),
			);
		}

		if ( $dias <= $aviso ) {
			return array(
				'estado'   => 'caduca_pronto',
				'dias'     => $dias,
				'fecha'    => $fecha,
				'etiqueta' => sprintf(
					/* translators: 1: fecha de caducidad, 2: días que quedan. */
					_n( 'Caduca el %1$s (quedan %2$d día)', 'Caduca el %1$s (quedan %2$d días)', $dias, 'convoca-members' ),
					$fecha,
					$dias
				),
			);
		}

		return array(
			'estado'   => 'vigente',
			'dias'     => $dias,
			'fecha'    => $fecha,
			'etiqueta' => sprintf(
				/* translators: %s: fecha de caducidad. */
				__( 'Vigente hasta el %s', 'convoca-members' ),
				$fecha
			),
		);
	}

	/**
	 * ¿Está caducado? Atajo para quien solo necesita el sí o el no.
	 *
	 * @param int $documento_id Documento.
	 * @return bool
	 */
	public static function caducado( int $documento_id ): bool {
		return 'caducado' === self::estado_caducidad( $documento_id )['estado'];
	}

	/**
	 * Map capabilities to 'gestionar_documentos_voluntariado'
	 */
	public function map_documento_caps( $caps, $cap, $user_id, $args ) {
		$documento_caps = array(
			'edit_convoca_documento',
			'read_convoca_documento',
			'delete_convoca_documento',
			'edit_convoca_documentos',
			'edit_others_convoca_documentos',
			'publish_convoca_documentos',
			'read_private_convoca_documentos',
		);

		if ( in_array( $cap, $documento_caps, true ) ) {
			$caps = array( 'gestionar_documentos_voluntariado' );
		}

		return $caps;
	}
}
