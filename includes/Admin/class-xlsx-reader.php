<?php
/**
 * Convoca Members — XLSX Reader
 *
 * Parser mínimo de archivos .xlsx (sin dependencias externas) que
 * extrae la primera hoja como CSV. El formato XLSX es un ZIP con XML
 * interno; solo leemos sharedStrings.xml + sheet1.xml.
 *
 * @package Convoca\Members\Admin
 */

namespace Convoca\Members\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal XLSX reader — first sheet to CSV.
 */
class Xlsx_Reader {

	/**
	 * Convert an .xlsx file to CSV string (first sheet).
	 *
	 * @param string $filepath Absolute path to .xlsx file.
	 * @throws \RuntimeException When file cannot be read or ZipArchive missing.
	 * @return string CSV content.
	 */
	public static function to_csv( string $filepath ): string {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new \RuntimeException( 'ZipArchive extension is required to read XLSX files.' );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $filepath ) ) {
			throw new \RuntimeException( 'Could not open XLSX file.' );
		}

		// Shared strings (text values).
		$shared     = array();
		$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false !== $shared_xml ) {
			$shared = self::parse_shared_strings( $shared_xml );
		}

		// First sheet: find workbook.xml relation or default sheet1.xml.
		$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		if ( false === $sheet_xml ) {
			$zip->close();
			throw new \RuntimeException( 'Could not find sheet1 in XLSX.' );
		}

		$zip->close();
		return self::parse_sheet( $sheet_xml, $shared );
	}

	/**
	 * Parse sharedStrings.xml into an array of strings.
	 *
	 * @param string $xml XML content.
	 * @return array<int,string>
	 */
	private static function parse_shared_strings( string $xml ): array {
		$strings = array();
		$reader  = new \XMLReader();
		if ( ! $reader->XML( $xml ) ) {
			return $strings;
		}
		while ( $reader->read() ) {
			if ( \XMLReader::ELEMENT === $reader->nodeType && 't' === $reader->localName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
				$strings[] = $reader->readString();
			}
		}
		$reader->close();
		return $strings;
	}

	/**
	 * Parse a worksheet XML into CSV rows.
	 *
	 * @param string            $xml    Worksheet XML.
	 * @param array<int,string> $shared Shared strings.
	 * @return string
	 */
	private static function parse_sheet( string $xml, array $shared ): string {
		$rows   = array();
		$reader = new \XMLReader();
		if ( ! $reader->XML( $xml ) ) {
			return '';
		}

		$current_row        = array();
		$current_cell_value = '';
		$in_cell            = false;
		$in_inline_str      = false;

		while ( $reader->read() ) {
			if ( \XMLReader::ELEMENT === $reader->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
				if ( 'row' === $reader->localName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
					$current_row = array();
				} elseif ( 'c' === $reader->localName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
					$in_cell            = true;
					$current_cell_value = '';
					$cell_type          = $reader->getAttribute( 't' );
					$cell_ref           = $reader->getAttribute( 'r' );
					// Track column index from ref (A1, B1...) for empty cells.
					if ( $cell_ref ) {
						$current_row['__col'] = self::col_index( $cell_ref );
					}
				} elseif ( 'is' === $reader->localName && $in_cell ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
					$in_inline_str = true;
				} elseif ( 'v' === $reader->localName && $in_cell ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
					$current_cell_value = $reader->readString();
					// Handle shared strings.
					if ( isset( $cell_type ) && 's' === $cell_type ) {
						$idx                = (int) $current_cell_value;
						$current_cell_value = $shared[ $idx ] ?? '';
					}
				} elseif ( 't' === $reader->localName && $in_inline_str ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
					$current_cell_value = $reader->readString();
				}
			} elseif ( \XMLReader::END_ELEMENT === $reader->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
				if ( 'c' === $reader->localName && $in_cell ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
					$col = $current_row['__col'] ?? count( $current_row );
					unset( $current_row['__col'] );
					// Fill gaps with empty strings.
					$row_count = count( $current_row );
					while ( $row_count < $col ) {
						$current_row[] = '';
						$row_count++;
					}
					$current_row[] = $current_cell_value;
					$in_cell       = false;
					$in_inline_str = false;
				} elseif ( 'row' === $reader->localName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader native property.
					if ( ! empty( $current_row ) ) {
						$rows[] = $current_row;
					}
					$current_row = array();
				}
			}
		}
		$reader->close();

		// Convert to CSV.
		$out = array();
		foreach ( $rows as $row ) {
			$out[] = implode( ',', array_map( array( __CLASS__, 'csv_escape' ), $row ) );
		}
		return implode( "\n", $out );
	}

	/**
	 * Convert column reference (e.g. 'C') to 0-based index.
	 *
	 * @param string $ref Cell reference like 'C5'.
	 * @return int
	 */
	private static function col_index( string $ref ): int {
		$col = preg_replace( '/[0-9]/', '', $ref );
		$idx = 0;
		$len = strlen( $col );
		for ( $i = 0; $i < $len; $i++ ) {
			$idx = $idx * 26 + ( ord( strtoupper( $col[ $i ] ) ) - 64 );
		}
		return $idx - 1; // A=0, B=1, etc.
	}

	/**
	 * Escape a value for CSV.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function csv_escape( $value ): string {
		$value = (string) $value;
		if ( strpbrk( $value, ",\"\n" ) !== false ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}
}
