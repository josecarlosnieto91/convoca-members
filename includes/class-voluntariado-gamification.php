<?php
/**
 * Volunteer Gamification — badge/level system for volunteer hours.
 * Multi-track: Busgosu (nature), Lugg (social), Deva (elemental).
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
	const TRACKS = array(
		'busgosu' => array(
			'label'  => 'Busgosu · Naturaleza',
			'levels' => array(
				array(
					'name'  => 'Semilla',
					'emoji' => '🌱',
					'hours' => 0,
					'color' => '#7FA36B',
					'desc'  => 'Empiezas a conectar con la naturaleza',
				),
				array(
					'name'  => 'Brote',
					'emoji' => '🌿',
					'hours' => 10,
					'color' => '#5C6B4F',
					'desc'  => 'Tus raíces crecen',
				),
				array(
					'name'  => 'Árbol',
					'emoji' => '🌳',
					'hours' => 25,
					'color' => '#FF8700',
					'desc'  => 'Ya eres refugio de biodiversidad',
				),
				array(
					'name'  => 'Bosque',
					'emoji' => '🌲',
					'hours' => 50,
					'color' => '#FFAB00',
					'desc'  => 'El ecosistema te reconoce',
				),
				array(
					'name'  => 'Ecosistema',
					'emoji' => '🌍',
					'hours' => 100,
					'color' => '#7D0032',
					'desc'  => 'Eres parte del todo',
				),
			),
		),
		'lugg'    => array(
			'label'  => 'Lugg · Social',
			'levels' => array(
				array(
					'name'  => 'Mano Abierta',
					'emoji' => '🤝',
					'hours' => 0,
					'color' => '#4A90D9',
					'desc'  => 'El primer paso es la confianza',
				),
				array(
					'name'  => 'Vínculo',
					'emoji' => '👥',
					'hours' => 10,
					'color' => '#357ABD',
					'desc'  => 'Creas comunidad',
				),
				array(
					'name'  => 'Comunidad',
					'emoji' => '🏘️',
					'hours' => 25,
					'color' => '#E67E22',
					'desc'  => 'El grupo te sostiene',
				),
				array(
					'name'  => 'Ciudad',
					'emoji' => '🌆',
					'hours' => 50,
					'color' => '#F39C12',
					'desc'  => 'Tu huella social se expande',
				),
				array(
					'name'  => 'Red',
					'emoji' => '🌐',
					'hours' => 100,
					'color' => '#8E44AD',
					'desc'  => 'Eres nodo de una red viva',
				),
			),
		),
		'deva'    => array(
			'label'  => 'Deva · Elementales',
			'levels' => array(
				array(
					'name'  => 'Náyade',
					'emoji' => '💧',
					'hours' => 0,
					'color' => '#3498DB',
					'desc'  => 'Fluir con el cambio',
				),
				array(
					'name'  => 'Salamandra',
					'emoji' => '🔥',
					'hours' => 10,
					'color' => '#E74C3C',
					'desc'  => 'La pasión que transforma',
				),
				array(
					'name'  => 'Silfo',
					'emoji' => '🌪️',
					'hours' => 25,
					'color' => '#1ABC9C',
					'desc'  => 'La idea que vuela',
				),
				array(
					'name'  => 'Gnomo',
					'emoji' => '⛰️',
					'hours' => 50,
					'color' => '#795548',
					'desc'  => 'La fuerza que sostiene',
				),
				array(
					'name'  => 'Deva',
					'emoji' => '✨',
					'hours' => 100,
					'color' => '#9B59B6',
					'desc'  => 'Guardian/a de la naturaleza',
				),
			),
		),
	);

	const DEFAULT_TRACK = 'busgosu';

	const OPTION_KEY = 'bdv_gamification_tracks';

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Get the track key for a given member.
	 *
	 * Examines _bdv_sub_plan then _bdv_plan and extracts the
	 * track name from the suffix (e.g. fam-busgosu → busgosu).
	 *
	 * @param  int $member_id Post ID of the member.
	 * @return string Track key (busgosu, lugg, deva, or DEFAULT_TRACK).
	 */
	public static function get_track_for_member( int $member_id ): string {
		$sub_plan = get_post_meta( $member_id, '_bdv_sub_plan', true );
		$plan     = get_post_meta( $member_id, '_bdv_plan', true );
		$key      = $sub_plan ?: $plan;

		if ( ! $key ) {
			return self::DEFAULT_TRACK;
		}

		// Extract track from the suffix: fam-busgosu → busgosu, juv-lugg → lugg, deva → deva
		foreach ( array( 'busgosu', 'lugg', 'deva' ) as $track ) {
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
		$defaults = self::TRACKS;
		$saved    = get_option( self::OPTION_KEY, array() );

		if ( empty( $saved ) ) {
			return $defaults;
		}

		foreach ( $defaults as $track_key => &$track ) {
			if ( isset( $saved[ $track_key ]['levels'] ) && is_array( $saved[ $track_key ]['levels'] ) ) {
				foreach ( $track['levels'] as $i => &$default_level ) {
					if ( isset( $saved[ $track_key ]['levels'][ $i ] ) && is_array( $saved[ $track_key ]['levels'][ $i ] ) ) {
						// Merge saved values over defaults (preserves all keys)
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
	 * @param  string $track Track key (busgosu, lugg, deva).
	 * @return array  Current level with name, emoji, color, desc, index.
	 */
	public static function get_level( float $hours, string $track = '' ): array {
		if ( ! $track || ! isset( self::TRACKS[ $track ] ) ) {
			$track = self::DEFAULT_TRACK;
		}

		$config = self::get_tracks_config();
		$levels = $config[ $track ]['levels'] ?? self::TRACKS[ self::DEFAULT_TRACK ]['levels'];

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
		if ( ! $track || ! isset( self::TRACKS[ $track ] ) ) {
			$track = self::DEFAULT_TRACK;
		}

		$config = self::get_tracks_config();
		$levels = $config[ $track ]['levels'] ?? self::TRACKS[ self::DEFAULT_TRACK ]['levels'];

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
		if ( ! $track || ! isset( self::TRACKS[ $track ] ) ) {
			$track = self::DEFAULT_TRACK;
		}

		$config = self::get_tracks_config();
		$levels = $config[ $track ]['levels'] ?? self::TRACKS[ self::DEFAULT_TRACK ]['levels'];

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
			return new \WP_REST_Response( array( 'error' => 'No autorizado.' ), 401 );
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

		// Include full levels list for the step ladder UI
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
