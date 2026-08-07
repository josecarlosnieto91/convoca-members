<?php
/**
 * Convoca Members
 *
 * @package    Convoca\Members
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Convoca\Members\Providers\Phone_Verifier_Provider;
use Convoca\Members\Providers\Telegram_Provider;
use Convoca\Members\Providers\WhatsApp_Provider;

/**
 * Phone verification registry (provider pluggable).
 *
 * The first available provider wins. Admins can later reorder or extend
 * the list via the 'convoca_phone_verifiers' filter.
 */
class Phone_Verifier {

	/** @var Phone_Verifier_Provider[]|null */
	private static ?array $providers = null;

	/**
	 * Get all registered providers.
	 *
	 * @return Phone_Verifier_Provider[]
	 */
	public static function get_providers(): array {
		if ( null === self::$providers ) {
			self::$providers = array(
				new Telegram_Provider(),
				new WhatsApp_Provider(),
			);
			self::$providers = apply_filters( 'convoca_phone_verifiers', self::$providers );
		}
		return self::$providers;
	}

	/**
	 * Get the active provider (first available) or null.
	 */
	public static function get_active_provider(): ?Phone_Verifier_Provider {
		foreach ( self::get_providers() as $provider ) {
			if ( $provider->is_available() ) {
				return $provider;
			}
		}
		return null;
	}

	/**
	 * Get a provider by slug.
	 */
	public static function get_provider( string $slug ): ?Phone_Verifier_Provider {
		foreach ( self::get_providers() as $provider ) {
			if ( $provider->get_slug() === $slug ) {
				return $provider;
			}
		}
		return null;
	}

	/**
	 * Whether any provider is active and a member's phone is verified.
	 */
	public static function is_phone_verified( int $member_id ): bool {
		return '1' === get_post_meta( $member_id, Providers\Telegram_Provider::META_VERIFIED, true )
			|| '1' === get_post_meta( $member_id, '_convoca_telefono_verificado', true );
	}

	/**
	 * Register webhook routes for providers that need them.
	 */
	public static function register_webhooks(): void {
		foreach ( self::get_providers() as $provider ) {
			if ( $provider instanceof Providers\Telegram_Provider ) {
				add_action( 'rest_api_init', array( $provider, 'register_webhook' ) );
			}
		}
	}
}
