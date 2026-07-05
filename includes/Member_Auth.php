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
 * Member authentication and session management.
 *
 * Now uses WordPress native credentials (username/password).
 * Access code login removed — credentials are sent upon approval.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

use Convoca\Core\Logger;
use Convoca\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Member_Auth {

	private const SESSION_COOKIE        = 'convoca_member_session';
	private const TRANSIENT_PREFIX      = 'convoca_member_session_';
	private const SESSION_EXPIRATION    = 6 * HOUR_IN_SECONDS;
	private const MAX_LOGIN_ATTEMPTS    = 5;
	private const LOGIN_LOCKOUT_SECONDS = 15 * MINUTE_IN_SECONDS;

	/**
	 * Authenticate a member using WordPress credentials.
	 *
	 * @param string $username WP username or email.
	 * @param string $password WP password.
	 * @return string|\WP_Error Token on success, WP_Error on failure.
	 */
	public static function login( string $username, string $password ): string|\WP_Error {
		$username = sanitize_user( $username );

		if ( ! $username || ! $password ) {
			return new \WP_Error( 'missing_params', __( 'Usuario y contraseña son obligatorios.', 'convoca-members' ) );
		}

		// Rate limiting: prevent brute-force attacks.
		if ( ! Utils::check_rate_limit( 'login', self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCKOUT_SECONDS ) ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Demasiados intentos de acceso. Por favor, espera 15 minutos e inténtalo de nuevo.', 'convoca-members' )
			);
		}

		// Authenticate via WordPress.
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			Logger::warning( "Intento de inicio de sesión fallido: {$username}", 'Members/Members' );
			return new \WP_Error(
				'login_failed',
				__( 'Usuario o contraseña incorrectos.', 'convoca-members' )
			);
		}

		// Verify the user has a linked member (miembro CPT).
		$member_id = (int) get_user_meta( $user->ID, '_convoca_member_id', true );
		if ( ! $member_id ) {
			Logger::warning( "Usuario {$username} no tiene un perfil de socio vinculado.", 'Members/Members' );
			return new \WP_Error(
				'not_found',
				__( 'Tu usuario no está vinculado a un perfil de socio. Contacta con coordinación.', 'convoca-members' )
			);
		}

		$member = get_post( $member_id );
		if ( ! $member || $member->post_type !== 'miembro' ) {
			return new \WP_Error(
				'not_found',
				__( 'No se encontró tu perfil de socio. Contacta con coordinación.', 'convoca-members' )
			);
		}

		// Generate session token.
		$token = wp_generate_password( 32, false );

		set_transient(
			self::TRANSIENT_PREFIX . $token,
			array(
				'id'             => $member_id,
				'wp_user_id'     => $user->ID,
				'last_renewal'   => time(),
				'pending_cookie' => false,
			),
			self::SESSION_EXPIRATION
		);

		// Set cookie.
		setcookie(
			self::SESSION_COOKIE,
			$token,
			array(
				'expires'  => time() + self::SESSION_EXPIRATION,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		Logger::info( "Socio ha iniciado sesión: {$member->post_title} (ID: {$member_id})", 'Members/Members', $member_id );

		return $token;
	}

	/**
	 * End member session.
	 */
	public static function logout(): void {
		$token = self::get_current_token();
		if ( $token ) {
			delete_transient( self::TRANSIENT_PREFIX . $token );
		}

		setcookie(
			self::SESSION_COOKIE,
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Get the ID of the currently logged-in member.
	 *
	 * @return int Member ID or 0 if not logged in.
	 */
	public static function get_current_member_id(): int {
		$token = self::get_current_token();
		if ( ! $token ) {
			return 0;
		}

		$session = get_transient( self::TRANSIENT_PREFIX . $token );
		if ( ! $session || ! is_array( $session ) ) {
			return 0;
		}

		$member_id      = (int) ( $session['id'] ?? 0 );
		$last_renewal   = (int) ( $session['last_renewal'] ?? 0 );
		$pending_cookie = ! empty( $session['pending_cookie'] );

		if ( ! $member_id ) {
			return 0;
		}

		$now                    = time();
		$needs_cookie_update    = ( $now - $last_renewal ) > HOUR_IN_SECONDS || $pending_cookie;
		$needs_transient_update = ( $now - $last_renewal ) > ( 15 * MINUTE_IN_SECONDS );

		if ( $needs_cookie_update ) {
			if ( ! headers_sent() ) {
				setcookie(
					self::SESSION_COOKIE,
					$token,
					array(
						'expires'  => $now + self::SESSION_EXPIRATION,
						'path'     => COOKIEPATH,
						'domain'   => COOKIE_DOMAIN,
						'secure'   => is_ssl(),
						'httponly' => true,
						'samesite' => 'Lax',
					)
				);
				$pending_cookie         = false;
				$needs_transient_update = true;
			} else {
				$pending_cookie = true;
			}
		}

		if ( $needs_transient_update ) {
			set_transient(
				self::TRANSIENT_PREFIX . $token,
				array(
					'id'             => $member_id,
					'wp_user_id'     => $session['wp_user_id'] ?? 0,
					'last_renewal'   => $now,
					'pending_cookie' => $pending_cookie,
				),
				self::SESSION_EXPIRATION
			);
		}

		return $member_id;
	}

	/**
	 * Get the data of the currently logged-in member securely.
	 *
	 * @return array|null Array or null if not logged in.
	 */
	public static function get_current_member_data(): ?array {
		$member_id = self::get_current_member_id();
		if ( ! $member_id ) {
			return null;
		}

		$post = get_post( $member_id );
		if ( ! $post ) {
			return null;
		}

		return array(
			'id'            => $member_id,
			'name'          => $post->post_title,
			'email'         => get_post_meta( $member_id, '_convoca_email', true ),
			'phone'         => get_post_meta( $member_id, '_convoca_telefono', true ),
			'dni'           => get_post_meta( $member_id, '_convoca_dni', true ),
			'member_status' => get_post_meta( $member_id, '_convoca_estado_miembro', true ),
		);
	}

	/**
	 * Check if the current request is authenticated.
	 *
	 * @return bool
	 */
	public static function is_authenticated(): bool {
		return self::get_current_member_id() > 0;
	}

	/**
	 * Check if the current member is active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		$member_id = self::get_current_member_id();
		if ( ! $member_id ) {
			return false;
		}
		$status = get_post_meta( $member_id, '_convoca_estado_miembro', true );
		return $status === 'activo';
	}

	/**
	 * Get the current session token from cookie or header.
	 *
	 * @return string
	 */
	public static function get_current_token(): string {
		$token = sanitize_text_field( $_COOKIE[ self::SESSION_COOKIE ] ?? '' );

		if ( ! $token && isset( $_SERVER['HTTP_X_CONVOCA_AUTH'] ) ) {
			$token = sanitize_text_field( $_SERVER['HTTP_X_CONVOCA_AUTH'] );
		}

		return (string) $token;
	}
}
