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
 * Volunteer Gamification — badge/level system for volunteer hours.
 * Multi-track gamification system with configurable tracks via convoca_gamification_tracks filter.
 *
 * @package Convoca\Members
 */

namespace Convoca\Members;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Voluntariado_Gamification {

	/**
	 * Default track definitions. Saved options merge over these.
	 */
	public static function default_tracks(): array {
		return array(
			'nature'    => array(
				'label'  => __( '🌱 Naturaleza', 'convoca-members' ),
				'levels' => array(
					array(
						'name'  => __( 'Semilla', 'convoca-members' ),
						'emoji' => '🌱',
						'hours' => 0,
						'color' => '#7FA36B',
						'desc'  => __( 'Empiezas a conectar con la naturaleza', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Brote', 'convoca-members' ),
						'emoji' => '🌿',
						'hours' => 10,
						'color' => '#5C6B4F',
						'desc'  => __( 'Tus raíces crecen', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Árbol', 'convoca-members' ),
						'emoji' => '🌳',
						'hours' => 25,
						'color' => '#FF8700',
						'desc'  => __( 'Ya eres refugio de biodiversidad', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Bosque', 'convoca-members' ),
						'emoji' => '🌲',
						'hours' => 50,
						'color' => '#FFAB00',
						'desc'  => __( 'El ecosistema te reconoce', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Ecosistema', 'convoca-members' ),
						'emoji' => '🌍',
						'hours' => 100,
						'color' => '#7D0032',
						'desc'  => __( 'Eres parte del todo', 'convoca-members' ),
					),
				),
			),
			'community' => array(
				'label'  => __( '🤝 Comunidad', 'convoca-members' ),
				'levels' => array(
					array(
						'name'  => __( 'Mano Abierta', 'convoca-members' ),
						'emoji' => '🤝',
						'hours' => 0,
						'color' => '#4A90D9',
						'desc'  => __( 'El primer paso es la confianza', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Vínculo', 'convoca-members' ),
						'emoji' => '👥',
						'hours' => 10,
						'color' => '#357ABD',
						'desc'  => __( 'Creas comunidad', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Comunidad', 'convoca-members' ),
						'emoji' => '🏘️',
						'hours' => 25,
						'color' => '#E67E22',
						'desc'  => __( 'El grupo te sostiene', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Ciudad', 'convoca-members' ),
						'emoji' => '🌆',
						'hours' => 50,
						'color' => '#F39C12',
						'desc'  => __( 'Tu huella social se expande', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Red', 'convoca-members' ),
						'emoji' => '🌐',
						'hours' => 100,
						'color' => '#8E44AD',
						'desc'  => __( 'Eres nodo de una red viva', 'convoca-members' ),
					),
				),
			),
			'growth'    => array(
				'label'  => __( '🚀 Crecimiento', 'convoca-members' ),
				'levels' => array(
					array(
						'name'  => __( 'Náyade', 'convoca-members' ),
						'emoji' => '💧',
						'hours' => 0,
						'color' => '#3498DB',
						'desc'  => __( 'Fluir con el cambio', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Salamandra', 'convoca-members' ),
						'emoji' => '🔥',
						'hours' => 10,
						'color' => '#E74C3C',
						'desc'  => __( 'La pasión que transforma', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Silfo', 'convoca-members' ),
						'emoji' => '🌪️',
						'hours' => 25,
						'color' => '#1ABC9C',
						'desc'  => __( 'La idea que vuela', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Gnomo', 'convoca-members' ),
						'emoji' => '⛰️',
						'hours' => 50,
						'color' => '#795548',
						'desc'  => __( 'La fuerza que sostiene', 'convoca-members' ),
					),
					array(
						'name'  => __( 'Deva', 'convoca-members' ),
						'emoji' => '✨',
						'hours' => 100,
						'color' => '#9B59B6',
						'desc'  => __( 'Guardian/a de la naturaleza', 'convoca-members' ),
					),
				),
			),
		);
	}

	const DEFAULT_TRACK = 'nature';

	const OPTION_KEY = 'convoca_gamification_tracks';

	/**
	 * Get default track definitions.
	 *
	 * @return array Default track data.
	 */
	public static function get_default_tracks(): array {
		return self::default_tracks();
	}

	/**
	 * Get tracks with filter applied.
	 *
	 * @return array Filtered track configuration.
	 */
	public static function get_tracks(): array {
		return apply_filters( 'convoca_gamification_tracks', self::get_default_tracks() );
	}

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Get the track key for a given member.
	 *
	 * Examines _convoca_sub_plan then _convoca_plan and extracts the
	 * track name from the suffix.
	 *
	 * @param  int $member_id Post ID of the member.
	 * @return string Track key (first matching track or DEFAULT_TRACK).
	 */
	public static function get_track_for_member( int $member_id ): string {
		$sub_plan = get_post_meta( $member_id, '_convoca_sub_plan', true );
		$plan     = get_post_meta( $member_id, '_convoca_plan', true );
		$key      = $sub_plan ?: $plan;

		if ( ! $key ) {
			return self::DEFAULT_TRACK;
		}

		// Extract track from the suffix by matching against available track keys.
		$tracks = array_keys( self::get_tracks() );
		foreach ( $tracks as $track ) {
			if ( str_ends_with( $key, $track ) ) {
				return $track;
			}
		}

		return self::DEFAULT_TRACK;
	}

	/**
	 * Get the merged track configuration (defaults overlaid with saved options).
	 *
	 * @return array Full TRACKS config with saved overrides merged in.
	 */
	public static function get_tracks_config(): array {
		$defaults = self::default_tracks();
		$saved    = get_option( self::OPTION_KEY, array() );

		if ( empty( $saved ) ) {
			return $defaults;
		}

		foreach ( $defaults as $track_key => &$track ) {
			if ( isset( $saved[ $track_key ]['levels'] ) && is_array( $saved[ $track_key ]['levels'] ) ) {
				foreach ( $track['levels'] as $i => &$default_level ) {
					if ( isset( $saved[ $track_key ]['levels'][ $i ] ) && is_array( $saved[ $track_key ]['levels'][ $i ] ) ) {
						// Merge saved values over defaults (preserves all keys).
						$default_level = array_merge( $default_level, $saved[ $track_key ]['levels'][ $i ] );
					}
				}
			}
			if ( isset( $saved[ $track_key ]['label'] ) ) {
				$track['label'] = $saved[ $track_key ]['label'];
			}
		}

		return $defaults;
	}

	/**
	 * Get the current level for a given amount of hours, on a specific track.
	 *
	 * @param  float  $hours Total approved volunteer hours.
	 * @param  string $track Track key.
	 * @return array  Current level with name, emoji, color, desc, index.
	 */
	public static function get_level( float $hours, string $track = '' ): array {
		if ( ! $track || ! isset( self::default_tracks()[ $track ] ) ) {
			$track = self::DEFAULT_TRACK;
		}

		$config = self::get_tracks_config();
		$levels = $config[ $track ]['levels'] ?? self::default_tracks()[ self::DEFAULT_TRACK ]['levels'];

		$level          = $levels[0];
		$level['index'] = 0;

		foreach ( $levels as $i => $lvl ) {
			if ( $hours >= $lvl['hours'] ) {
				$level          = $lvl;
				$level['index'] = $i;
			}
		}

		return $level;
	}

	/**
	 * Get the next level to reach, or null if already at max.
	 *
	 * @param  float  $hours Total approved volunteer hours.
	 * @param  string $track Track key.
	 * @return array|null Next level data or null.
	 */
	public static function get_next_level( float $hours, string $track = '' ): ?array {
		if ( ! $track || ! isset( self::default_tracks()[ $track ] ) ) {
			$track = self::DEFAULT_TRACK;
		}

		$config = self::get_tracks_config();
		$levels = $config[ $track ]['levels'] ?? self::default_tracks()[ self::DEFAULT_TRACK ]['levels'];

		$current_index = self::get_level( $hours, $track )['index'] ?? 0;
		$next_index    = $current_index + 1;

		if ( ! isset( $levels[ $next_index ] ) ) {
			return null;
		}

		return $levels[ $next_index ];
	}

	/**
	 * Get gamification progress data.
	 *
	 * @param  float  $hours Total approved volunteer hours.
	 * @param  string $track Track key.
	 * @return array{current: array, next: array|null, progress_percent: float, hours_to_next: float}
	 */
	public static function get_progress( float $hours, string $track = '' ): array {
		if ( ! $track || ! isset( self::default_tracks()[ $track ] ) ) {
			$track = self::DEFAULT_TRACK;
		}

		$config = self::get_tracks_config();
		$levels = $config[ $track ]['levels'] ?? self::default_tracks()[ self::DEFAULT_TRACK ]['levels'];

		$current = self::get_level( $hours, $track );
		$next    = self::get_next_level( $hours, $track );

		$progress_percent = 0.0;
		$hours_to_next    = 0.0;

		if ( $next !== null ) {
			$current_threshold = $current['hours'];
			$next_threshold    = $next['hours'];
			$range             = $next_threshold - $current_threshold;

			if ( $range > 0 ) {
				$progress_in_level = $hours - $current_threshold;
				$progress_percent  = round( ( $progress_in_level / $range ) * 100, 1 );
				$hours_to_next     = round( $next_threshold - $hours, 1 );
			}

			if ( $progress_percent < 0 ) {
				$progress_percent = 0;
			}
			if ( $progress_percent > 100 ) {
				$progress_percent = 100;
			}
			if ( $hours_to_next < 0 ) {
				$hours_to_next = 0;
			}
		} else {
			$progress_percent = 100;
			$hours_to_next    = 0;
		}

		return array(
			'current'          => $current,
			'next'             => $next,
			'progress_percent' => $progress_percent,
			'hours_to_next'    => $hours_to_next,
		);
	}

	/**
	 * Register REST route: GET /convoca-members/v1/me/gamification
	 */
	public static function register_routes(): void {
		register_rest_route(
			Rest_API::NAMESPACE,
			'/me/gamification',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_gamification_data' ),
				'permission_callback' => array( Rest_API::class, 'check_active_member' ),
			)
		);
	}

	/**
	 * REST callback: return gamification data for the current member.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function get_gamification_data( \WP_REST_Request $request ): \WP_REST_Response {
		$member_id = Member_Auth::get_current_member_id();

		if ( ! $member_id ) {
			return new \WP_REST_Response( array( 'error' => __( 'No autorizado.', 'convoca-members' ) ), 401 );
		}

		$track_key   = self::get_track_for_member( $member_id );
		$config      = self::get_tracks_config();
		$track_cfg   = $config[ $track_key ] ?? $config[ self::DEFAULT_TRACK ];
		$track_label = $track_cfg['label'];
		$levels_def  = $track_cfg['levels'];
		$total_hours = Voluntariado_Manager::get_horas_aprobadas( $member_id );

		$level    = self::get_level( $total_hours, $track_key );
		$next     = self::get_next_level( $total_hours, $track_key );
		$progress = self::get_progress( $total_hours, $track_key );

		$response = array(
			'track'       => $track_key,
			'track_label' => $track_label,
			'total_hours' => $total_hours,
			'level'       => array(
				'name'  => $level['name'],
				'emoji' => $level['emoji'],
				'color' => $level['color'],
				'desc'  => $level['desc'] ?? '',
				'index' => $level['index'],
			),
			'badges'      => array(),
		);

		if ( $next !== null ) {
			$response['next_level'] = array(
				'name'             => $next['name'],
				'emoji'            => $next['emoji'],
				'color'            => $next['color'],
				'desc'             => $next['desc'] ?? '',
				'hours'            => $next['hours'],
				'hours_to_go'      => $progress['hours_to_next'],
				'progress_percent' => $progress['progress_percent'],
			);
		}

		// Include full levels list for the step ladder UI.
		$response['levels'] = array();
		$current_index      = $level['index'];
		foreach ( $levels_def as $i => $lvl ) {
			$response['levels'][] = array(
				'name'    => $lvl['name'],
				'emoji'   => $lvl['emoji'],
				'color'   => $lvl['color'],
				'desc'    => $lvl['desc'] ?? '',
				'hours'   => $lvl['hours'],
				'index'   => $i,
				'reached' => $i <= $current_index,
			);
		}

		return new \WP_REST_Response( $response );
	}
}
