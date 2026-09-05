<?php
/**
 * PHPStan stub for Convoca\Core\Upgrade_Manager.
 *
 * This plugin extends Convoca\Core\Upgrade_Manager, which is provided by the
 * separate convoca-core plugin at runtime and is NOT present in this repo's CI.
 * A minimal, signature-accurate stub keeps PHPStan able to resolve the class
 * hierarchy without depending on ../convoca-core.
 *
 * Analysis-only: never loaded at runtime (guarded by the namespace under PHPStan).
 */

namespace Convoca\Core;

abstract class Upgrade_Manager {
	public function init(): void {}
	public function maybe_upgrade(): void {}
	public function force_version_check(): void {}
	protected function run_upgrades( string $from, string $to ): void {}
	protected function run_data_migration( callable $callback, string $description ): void {}
	protected function update_db_version( string $version ): void {}
	protected function get_saved_version(): string {
		return '';
	}
	protected function is_cached(): bool {
		return false;
	}
	protected function set_cache(): void {}
	protected function clear_cache(): void {}

	abstract protected function get_db_version(): string;
	abstract protected function get_option_name(): string;
	abstract protected function get_transient_prefix(): string;
	abstract protected function get_upgrade_callbacks(): array;
}
