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

namespace Convoca\Members;

/**
 * Centralized Audit Logger for Members.
 * Tracks lifecycle events like deletions, email logs, etc.
 *
 * @package Convoca\Members
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Audit_Logger {

	public function __construct() {
		// Track logical deletions (trash).
		add_action( 'wp_trash_post', array( $this, 'log_trash' ), 10, 1 );
		add_action( 'untrash_post', array( $this, 'log_untrash' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'log_permanent_delete' ), 10, 1 );

		// Track metadata changes if needed.
		add_action( 'updated_post_meta', array( $this, 'log_meta_change' ), 10, 4 );
	}

	public function log_trash( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'miembro' ) {
			return;
		}

		\Convoca\Core\Logger::warning(
			__( 'Miembro movido a la papelera (borrado lógico).', 'convoca-members' ),
			'Members/Audit',
			$post_id
		);
	}

	public function log_untrash( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'miembro' ) {
			return;
		}

		\Convoca\Core\Logger::info(
			__( 'Miembro restaurado de la papelera.', 'convoca-members' ),
			'Members/Audit',
			$post_id
		);
	}

	public function log_permanent_delete( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'miembro' ) {
			return;
		}

		\Convoca\Core\Logger::error(
			__( 'ELIMINACIÓN PERMANENTE del miembro.', 'convoca-members' ),
			'Members/Audit',
			$post_id
		);
	}

	/**
	 * Log specific meta changes like WhatsApp usage.
	 * Protects sensitive data by redacting values or ignoring fields.
	 */
	public function log_meta_change( int $meta_id, int $post_id, string $meta_key, $meta_value ): void {
		if ( get_post_type( $post_id ) !== 'miembro' ) {
			return;
		}

		// 1. WhatsApp exception: log specifically
		if ( $meta_key === '_convoca_ultimo_contacto_whatsapp' ) {
			\Convoca\Core\Logger::info(
				__( 'Contacto por WhatsApp registrado.', 'convoca-members' ),
				'Members/Audit',
				$post_id
			);
			return;
		}

		// 2. Filter sensitive fields
		$sensitive_fields = apply_filters(
			'convoca_members_sensitive_meta',
			array(
				'_convoca_dni',
				'_convoca_email',
				'_convoca_telefono',
				'_convoca_password',
				'_convoca_iban',
			)
		);

		if ( in_array( $meta_key, $sensitive_fields, true ) ) {
			return;
		}

		// 3. Generic log for other Convoca fields (only track that it changed, not the value)
		if ( str_starts_with( $meta_key, '_convoca_' ) ) {
			\Convoca\Core\Logger::info(
				sprintf( __( 'Metadato "%s" actualizado.', 'convoca-members' ), $meta_key ),
				'Members/Audit',
				$post_id
			);
		}
	}
}
