<?php
/**
 * Maps ACF ("Advanced Custom Fields") field data into a plain-text paragraph
 * suitable for indexing alongside a post's primary content.
 *
 * See features/ACF/parsing.md for the field-type mapping decisions this class
 * implements.
 *
 * @package ProgressAgenticRag
 */

namespace ProgressAgenticRag\Indexing;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class AcfAdapter implements MetadataAdapterInterface {
	public function is_active(): bool {
		return function_exists( 'get_field_objects' );
	}

	public function extract_text( WP_Post $post ): string {
		if ( ! $this->is_active() ) {
			return '';
		}

		$field_objects = get_field_objects( $post->ID );
		if ( ! is_array( $field_objects ) || [] === $field_objects ) {
			return '';
		}

		$parts = [];
		foreach ( $field_objects as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$value_text = $this->leaf_value_text( $field, $field['value'] ?? null );
			if ( null === $value_text || '' === $value_text ) {
				continue;
			}

			$parts[] = $this->terminate_sentence( $this->field_label( $field ) . ': ' . $value_text );
		}

		// Joined as short sentences ("Label: value."), not "\n" — a period
		// stays visible even where whitespace gets collapsed downstream.
		return implode( ' ', $parts );
	}

	/**
	 * Ensures a composed sentence ends in terminal punctuation, without
	 * doubling up if the value already ends in one (e.g. WYSIWYG text that
	 * already ends with its own ".").
	 */
	private function terminate_sentence( string $text ): string {
		return preg_match( '/[.!?]$/', $text ) ? $text : $text . '.';
	}

	/**
	 * Dispatches a single ACF field's value to its type-specific mapping rule.
	 * Returns just the value's text representation (no label) — used both for
	 * top-level fields and recursively for Group/Repeater/Flexible Content
	 * sub-fields.
	 *
	 * @param array<string, mixed> $field ACF field object (from get_field_objects()).
	 */
	private function leaf_value_text( array $field, mixed $value ): ?string {
		$type = (string) ( $field['type'] ?? '' );

		return match ( $type ) {
			'text', 'textarea', 'number', 'email', 'url', 'range',
			'date_picker', 'time_picker', 'date_time_picker' => $this->scalar_text( $value ),
			'wysiwyg' => $this->scalar_text( $this->strip_html( (string) $value ) ),
			'true_false' => $value ? 'Yes' : 'No',
			'select', 'radio', 'button_group' => is_array( $value )
				? $this->join_list( array_map( 'strval', $value ) )
				: $this->scalar_text( $value ),
			'checkbox' => $this->join_list( is_array( $value ) ? array_map( 'strval', $value ) : [] ),
			'taxonomy' => $this->taxonomy_text( $value, (string) ( $field['taxonomy'] ?? '' ) ),
			'post_object', 'page_link', 'relationship' => $this->post_reference_text( $value ),
			'user' => $this->user_reference_text( $value ),
			'image', 'file' => $this->attachment_text( $value ),
			'gallery' => $this->gallery_text( $value ),
			'google_map' => $this->google_map_text( $value ),
			'link' => $this->link_text( $value ),
			'group', 'clone' => $this->subfields_text( $this->sub_fields_of( $field ), is_array( $value ) ? $value : [] ),
			'repeater' => $this->repeater_text( $this->sub_fields_of( $field ), is_array( $value ) ? $value : [] ),
			'flexible_content' => $this->flexible_content_text( $this->layouts_of( $field ), is_array( $value ) ? $value : [] ),
			// Color Picker: not meaningful for text search. Password: never sent
			// to a 3rd-party index. oEmbed: low RAG value. Message/Tab/Accordion:
			// admin-UI only, hold no data.
			'color_picker', 'password', 'oembed', 'message', 'tab', 'accordion' => null,
			default => null,
		};
	}

	private function field_label( array $field ): string {
		$label = trim( (string) ( $field['label'] ?? '' ) );

		return '' !== $label ? $label : (string) ( $field['name'] ?? '' );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function sub_fields_of( array $field ): array {
		return is_array( $field['sub_fields'] ?? null ) ? $field['sub_fields'] : [];
	}

	/**
	 * ACF returns `layouts` as a plain sequential list, not keyed by name.
	 * Re-keys it by each layout's `name` for lookup by `acf_fc_layout`.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function layouts_of( array $field ): array {
		$layouts = is_array( $field['layouts'] ?? null ) ? $field['layouts'] : [];

		$by_name = [];
		foreach ( $layouts as $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}

			$name = (string) ( $layout['name'] ?? '' );
			if ( '' !== $name ) {
				$by_name[ $name ] = $layout;
			}
		}

		return $by_name;
	}

	private function scalar_text( mixed $value ): ?string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return null;
		}

		$text = trim( (string) $value );

		return '' === $text ? null : $text;
	}

	private function strip_html( string $html ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * @param list<string> $values
	 */
	private function join_list( array $values ): ?string {
		$clean = array_values(
			array_filter(
				array_map( 'trim', $values ),
				static fn( string $value ): bool => '' !== $value
			)
		);

		return [] === $clean ? null : implode( ', ', $clean );
	}

	private function taxonomy_text( mixed $value, string $taxonomy ): ?string {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$names = [];
		foreach ( $value as $term ) {
			if ( is_object( $term ) && isset( $term->name ) ) {
				$names[] = (string) $term->name;
				continue;
			}

			if ( is_string( $term ) ) {
				$names[] = $term;
				continue;
			}

			if ( is_numeric( $term ) && function_exists( 'get_term' ) ) {
				$resolved = get_term( (int) $term, $taxonomy );
				if ( is_object( $resolved ) && isset( $resolved->name ) ) {
					$names[] = (string) $resolved->name;
				}
			}
		}

		return $this->join_list( $names );
	}

	private function post_reference_text( mixed $value ): ?string {
		$ids = [];
		foreach ( is_array( $value ) ? $value : [ $value ] as $item ) {
			if ( $item instanceof WP_Post ) {
				$ids[] = $item->ID;
				continue;
			}

			if ( is_numeric( $item ) ) {
				$ids[] = (int) $item;
				continue;
			}

			// Page Link returns a permalink URL string (or array of URLs for
			// multi-select), not an ID/WP_Post — resolve it to a post ID.
			if ( is_string( $item ) && '' !== trim( $item ) && function_exists( 'url_to_postid' ) ) {
				$resolved_id = (int) url_to_postid( $item );
				if ( $resolved_id > 0 ) {
					$ids[] = $resolved_id;
				}
			}
		}

		$titles = [];
		foreach ( $ids as $id ) {
			if ( ! $this->is_publicly_referenceable( $id ) ) {
				continue;
			}

			$title = trim( (string) get_the_title( $id ) );
			if ( '' !== $title ) {
				$titles[] = $title;
			}
		}

		return $this->join_list( $titles );
	}

	/**
	 * Security: only titles of publicly viewable posts may be included, to avoid
	 * leaking draft/private/password-protected post titles into the search index
	 * (mirrors ManualSync::is_indexable_post()'s visibility rule).
	 */
	private function is_publicly_referenceable( int $post_id ): bool {
		$referenced = get_post( $post_id );

		return $referenced instanceof WP_Post
			&& 'publish' === $referenced->post_status
			&& '' === $referenced->post_password;
	}

	private function user_reference_text( mixed $value ): ?string {
		if ( is_array( $value ) && isset( $value['display_name'] ) ) {
			return $this->scalar_text( $value['display_name'] );
		}

		if ( is_object( $value ) && isset( $value->display_name ) ) {
			return $this->scalar_text( $value->display_name );
		}

		if ( is_numeric( $value ) && function_exists( 'get_userdata' ) ) {
			$user = get_userdata( (int) $value );
			if ( is_object( $user ) && isset( $user->display_name ) ) {
				return $this->scalar_text( $user->display_name );
			}
		}

		return null;
	}

	private function attachment_text( mixed $value ): ?string {
		if ( ! is_array( $value ) ) {
			return null;
		}

		foreach ( [ 'alt', 'caption', 'title' ] as $key ) {
			if ( ! empty( $value[ $key ] ) ) {
				return $this->scalar_text( $value[ $key ] );
			}
		}

		return null;
	}

	private function gallery_text( mixed $value ): ?string {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$parts = [];
		foreach ( $value as $image ) {
			$text = $this->attachment_text( $image );
			if ( null !== $text ) {
				$parts[] = $text;
			}
		}

		return [] === $parts ? null : implode( '; ', $parts );
	}

	private function google_map_text( mixed $value ): ?string {
		if ( ! is_array( $value ) || empty( $value['address'] ) ) {
			return null;
		}

		return $this->scalar_text( $value['address'] );
	}

	private function link_text( mixed $value ): ?string {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$url   = trim( (string) ( $value['url'] ?? '' ) );
		$title = trim( (string) ( $value['title'] ?? '' ) );

		if ( '' === $url && '' === $title ) {
			return null;
		}

		if ( '' === $title ) {
			return $url;
		}

		return '' === $url ? $title : $title . ' (' . $url . ')';
	}

	/**
	 * Renders a set of named sub-fields (a Group's fields, or one Repeater row)
	 * as `"{label} - {value}"` pairs joined with `; `.
	 *
	 * @param list<array<string, mixed>> $sub_field_defs
	 * @param array<string, mixed>       $values
	 */
	private function subfields_text( array $sub_field_defs, array $values ): ?string {
		$parts = [];
		foreach ( $sub_field_defs as $sub_field ) {
			if ( ! is_array( $sub_field ) ) {
				continue;
			}

			$name = (string) ( $sub_field['name'] ?? '' );
			if ( '' === $name || ! array_key_exists( $name, $values ) ) {
				continue;
			}

			$text = $this->leaf_value_text( $sub_field, $values[ $name ] );
			if ( null === $text || '' === $text ) {
				continue;
			}

			$parts[] = $this->field_label( $sub_field ) . ' - ' . $text;
		}

		return [] === $parts ? null : implode( '; ', $parts );
	}

	/**
	 * @param list<array<string, mixed>> $sub_field_defs
	 * @param list<array<string, mixed>> $rows
	 */
	private function repeater_text( array $sub_field_defs, array $rows ): ?string {
		$row_parts = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$row_text = $this->subfields_text( $sub_field_defs, $row );
			if ( null !== $row_text ) {
				$row_parts[] = $row_text;
			}
		}

		return [] === $row_parts ? null : implode( '; ', $row_parts );
	}

	/**
	 * @param array<string, array<string, mixed>> $layouts Keyed by layout name.
	 * @param list<array<string, mixed>>          $rows
	 */
	private function flexible_content_text( array $layouts, array $rows ): ?string {
		$row_parts = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$layout_name = (string) ( $row['acf_fc_layout'] ?? '' );
			$layout      = is_array( $layouts[ $layout_name ] ?? null ) ? $layouts[ $layout_name ] : [];
			$sub_fields  = is_array( $layout['sub_fields'] ?? null ) ? $layout['sub_fields'] : [];
			$label       = trim( (string) ( $layout['label'] ?? $layout_name ) );

			$values = $row;
			unset( $values['acf_fc_layout'] );

			$row_text = $this->subfields_text( $sub_fields, $values );
			if ( null === $row_text ) {
				continue;
			}

			$row_parts[] = ( '' !== $label ? $label : $layout_name ) . ': ' . $row_text;
		}

		return [] === $row_parts ? null : implode( '; ', $row_parts );
	}
}
