<?php
/**
 * Example `get_field_objects()` return value covering every ACF field type,
 * for use in unit tests of ProgressAgenticRag\Indexing\AcfAdapter (see
 * features/ACF/parsing.md for the mapping rules this fixture exercises).
 *
 * Shaped like ACF's real get_field_objects() output: each entry carries
 * 'type'/'label'/'name' metadata alongside its 'value', which is what the
 * dispatcher needs to tell apart otherwise-ambiguous array shapes (e.g. a
 * Link field vs a Google Map field vs a Group).
 *
 * Simple/reference/group values are based on a real local test post
 * (`?branch=full-test`). Pro-only field types not available in the local test
 * environment (Repeater, Flexible Content, Gallery, Clone) are synthesized
 * based on ACF's documented return-value shapes. Post references (201/202)
 * are IDs the consuming test is expected to register via its own `get_post()`
 * stub — 201 as a public/publish post, 202 as a draft/private post — to
 * exercise the visibility security check.
 *
 * @package ProgressAgenticRag\Tests\Fixtures
 *
 * @return array<string, array<string, mixed>> Same shape as get_field_objects($post_id).
 */

return [
	// --- Simple scalars ---
	'text_field'             => [
		'key'   => 'field_text',
		'label' => 'Text Field',
		'name'  => 'text_field',
		'type'  => 'text',
		'value' => 'Text content',
	],
	'text_area_field'        => [
		'key'   => 'field_textarea',
		'label' => 'Text Area Field',
		'name'  => 'text_area_field',
		'type'  => 'textarea',
		'value' => 'Text area content',
	],
	'number_field'           => [
		'key'   => 'field_number',
		'label' => 'Number Field',
		'name'  => 'number_field',
		'type'  => 'number',
		'value' => 1000,
	],
	'range_field'            => [
		'key'   => 'field_range',
		'label' => 'Range Field',
		'name'  => 'range_field',
		'type'  => 'range',
		'value' => 40,
	],
	'email_field'            => [
		'key'   => 'field_email',
		'label' => 'Email Field',
		'name'  => 'email_field',
		'type'  => 'email',
		'value' => 'email@gmail.com',
	],
	'url_field'              => [
		'key'   => 'field_url',
		'label' => 'Url Field',
		'name'  => 'url_field',
		'type'  => 'url',
		'value' => 'https://website.com',
	],
	'password_field'         => [
		'key'   => 'field_password',
		'label' => 'Password Field',
		'name'  => 'password_field',
		'type'  => 'password',
		'value' => '123123123',
	],
	'wysiwyg_field'          => [
		'key'   => 'field_wysiwyg',
		'label' => 'WYSIWYG Field',
		'name'  => 'wysiwyg_field',
		'type'  => 'wysiwyg',
		'value' => "<strong>wys</strong> <em>wyg</em> content\nsome wyswyg text",
	],
	'select_field'           => [
		'key'   => 'field_select',
		'label' => 'Select Field',
		'name'  => 'select_field',
		'type'  => 'select',
		'value' => 'choice 1',
	],
	'radio_button_field'     => [
		'key'   => 'field_radio',
		'label' => 'Radio Button Field',
		'name'  => 'radio_button_field',
		'type'  => 'radio',
		'value' => 'choice 2',
	],
	'button_group_field'     => [
		'key'   => 'field_button_group',
		'label' => 'Button Group Field',
		'name'  => 'button_group_field',
		'type'  => 'button_group',
		'value' => 'choice 2',
	],
	'truefalse_field'        => [
		'key'   => 'field_truefalse',
		'label' => 'True/False Field',
		'name'  => 'truefalse_field',
		'type'  => 'true_false',
		'value' => true,
	],
	'date_picker_field'      => [
		'key'   => 'field_date_picker',
		'label' => 'Date Picker Field',
		'name'  => 'date_picker_field',
		'type'  => 'date_picker',
		// Default Return Format is "Ymd" (no separators), not a display format.
		'value' => '20260917',
	],
	'date_time_picker_field' => [
		'key'   => 'field_date_time_picker',
		'label' => 'Date Time Picker Field',
		'name'  => 'date_time_picker_field',
		'type'  => 'date_time_picker',
		'value' => '2026-09-18 00:00:07',
	],
	'time_picker_field'      => [
		'key'   => 'field_time_picker',
		'label' => 'Time Picker Field',
		'name'  => 'time_picker_field',
		'type'  => 'time_picker',
		'value' => '00:00:00',
	],
	'color_picker_field'     => [
		'key'   => 'field_color_picker',
		'label' => 'Color Picker Field',
		'name'  => 'color_picker_field',
		'type'  => 'color_picker',
		'value' => '#ff0000',
	],

	// --- Multi-value ---
	'checkbox_field'         => [
		'key'   => 'field_checkbox',
		'label' => 'Checkbox Field',
		'name'  => 'checkbox_field',
		'type'  => 'checkbox',
		'value' => [ 'choice 2' ],
	],
	'taxonomy_field'         => [
		'key'      => 'field_taxonomy',
		'label'    => 'Taxonomy Field',
		'name'     => 'taxonomy_field',
		'type'     => 'taxonomy',
		'taxonomy' => 'category',
		'value'    => [ 3, 4 ], // Term IDs; resolved via get_term().
	],

	// --- Reference fields ---
	// 201 = public/publish post (title included), 202 = draft/private post
	// (title must be excluded by the security check).
	'post_object_field'      => [
		'key'   => 'field_post_object',
		'label' => 'Post Object Field',
		'name'  => 'post_object_field',
		'type'  => 'post_object',
		'value' => 201,
	],
	// Page Link returns a URL string (or array of URLs for multi-select),
	// never a post ID or WP_Post — unlike Post Object/Relationship.
	// `url_to_postid()` resolves it to a post ID.
	'page_link_field'        => [
		'key'   => 'field_page_link',
		'label' => 'Page Link Field',
		'name'  => 'page_link_field',
		'type'  => 'page_link',
		'value' => 'https://example.test/?p=201',
	],
	'relationship_field'     => [
		'key'   => 'field_relationship',
		'label' => 'Relationship Field',
		'name'  => 'relationship_field',
		'type'  => 'relationship',
		'value' => [ 201, 202 ],
	],
	'user_field'             => [
		'key'   => 'field_user',
		'label' => 'User Field',
		'name'  => 'user_field',
		'type'  => 'user',
		'value' => [
			'ID'           => 1,
			'display_name' => 'Jane Admin',
		],
	],
	'linke_field'            => [
		'key'   => 'field_link',
		'label' => 'Linke Field',
		'name'  => 'linke_field',
		'type'  => 'link',
		'value' => [
			'title'  => 'link text',
			'url'    => 'http://website1.url',
			'target' => '',
		],
	],
	'google_map_field'       => [
		'key'   => 'field_google_map',
		'label' => 'Google Map Field',
		'name'  => 'google_map_field',
		'type'  => 'google_map',
		'value' => [
			'address' => '21-22 Queen Street, Exeter, EX4 3SH',
			'lat'     => '50.7236',
			'lng'     => '-3.5339',
		],
	],

	// --- Image/File ---
	'image_field'            => [
		'key'   => 'field_image',
		'label' => 'Image Field',
		'name'  => 'image_field',
		'type'  => 'image',
		'value' => [
			'ID'      => 12,
			'url'     => 'https://example.com/wp-content/uploads/image.jpg',
			'alt'     => 'Storefront photo',
			'caption' => '',
		],
	],
	'file_field'             => [
		'key'   => 'field_file',
		'label' => 'File Field',
		'name'  => 'file_field',
		'type'  => 'file',
		'value' => [
			'ID'       => 13,
			'url'      => 'https://example.com/wp-content/uploads/brochure.pdf',
			'filename' => 'brochure.pdf',
		],
	],
	'oembed_field'           => [
		'key'   => 'field_oembed',
		'label' => 'OEmbed Field',
		'name'  => 'oembed_field',
		'type'  => 'oembed',
		'value' => 'https://youtube.com/watch?v=example',
	],

	// --- Group ---
	'address'                => [
		'key'        => 'field_address_group',
		'label'      => 'Address',
		'name'       => 'address',
		'type'       => 'group',
		'sub_fields' => [
			[ 'key' => 'field_street', 'label' => 'Street', 'name' => 'street', 'type' => 'text' ],
			[ 'key' => 'field_city', 'label' => 'City', 'name' => 'city', 'type' => 'text' ],
			[ 'key' => 'field_postcode', 'label' => 'Postcode', 'name' => 'postcode', 'type' => 'text' ],
			[ 'key' => 'field_country', 'label' => 'Country', 'name' => 'country', 'type' => 'text' ],
		],
		'value'      => [
			'street'   => '21-22 Queen Street',
			'city'     => 'Exeter',
			'postcode' => 'EX4 3SH',
			'country'  => 'UK',
		],
	],

	// --- Repeater (Pro; opening-hours use case) ---
	'opening_hours_repeater' => [
		'key'        => 'field_opening_hours',
		'label'      => 'Opening Hours',
		'name'       => 'opening_hours_repeater',
		'type'       => 'repeater',
		'sub_fields' => [
			[ 'key' => 'field_day', 'label' => 'Day', 'name' => 'day', 'type' => 'text' ],
			[ 'key' => 'field_open', 'label' => 'Open', 'name' => 'open', 'type' => 'time_picker' ],
			[ 'key' => 'field_close', 'label' => 'Close', 'name' => 'close', 'type' => 'time_picker' ],
			[ 'key' => 'field_closed', 'label' => 'Closed', 'name' => 'closed', 'type' => 'true_false' ],
		],
		'value'      => [
			[
				'day'    => 'Monday',
				'open'   => '09:00',
				'close'  => '17:00',
				'closed' => false,
			],
			[
				'day'    => 'Sunday',
				'open'   => '',
				'close'  => '',
				'closed' => true,
			],
		],
	],

	// --- Flexible Content (Pro) ---
	'page_sections'          => [
		'key'     => 'field_page_sections',
		'label'   => 'Page Sections',
		'name'    => 'page_sections',
		'type'    => 'flexible_content',
		// `layouts` is a sequential list, not keyed by layout name.
		'layouts' => [
			[
				'key'        => 'layout_hero',
				'label'      => 'Hero',
				'name'       => 'hero',
				'sub_fields' => [
					[ 'key' => 'field_heading', 'label' => 'Heading', 'name' => 'heading', 'type' => 'text' ],
					[ 'key' => 'field_subheading', 'label' => 'Subheading', 'name' => 'subheading', 'type' => 'text' ],
				],
			],
			[
				'key'        => 'layout_cta',
				'label'      => 'CTA',
				'name'       => 'cta',
				'sub_fields' => [
					[ 'key' => 'field_button_text', 'label' => 'Button Text', 'name' => 'button_text', 'type' => 'text' ],
					[ 'key' => 'field_button_url', 'label' => 'Button URL', 'name' => 'button_url', 'type' => 'url' ],
				],
			],
		],
		'value'   => [
			[
				'acf_fc_layout' => 'hero',
				'heading'       => 'Welcome to our Exeter branch',
				'subheading'    => 'Open every day',
			],
			[
				'acf_fc_layout' => 'cta',
				'button_text'   => 'Book now',
				'button_url'    => 'https://example.com/book',
			],
		],
	],

	// --- Gallery (Pro) ---
	'branch_gallery'         => [
		'key'   => 'field_gallery',
		'label' => 'Branch Gallery',
		'name'  => 'branch_gallery',
		'type'  => 'gallery',
		'value' => [
			[
				'ID'  => 21,
				'url' => 'https://example.com/wp-content/uploads/store-front.jpg',
				'alt' => 'Store front',
			],
			[
				'ID'  => 22,
				'url' => 'https://example.com/wp-content/uploads/interior.jpg',
				'alt' => '',
			],
		],
	],

	// --- Clone (Pro, group mode) ---
	'cloned_contact_info'    => [
		'key'        => 'field_clone_contact',
		'label'      => 'Cloned Contact Info',
		'name'       => 'cloned_contact_info',
		'type'       => 'clone',
		'sub_fields' => [
			[ 'key' => 'field_phone', 'label' => 'Phone', 'name' => 'phone', 'type' => 'text' ],
			[ 'key' => 'field_cloned_address', 'label' => 'Address', 'name' => 'address', 'type' => 'text' ],
		],
		'value'      => [
			'phone'   => '01392 349693',
			'address' => '21-22 Queen Street, Exeter, EX4 3SH',
		],
	],

	// --- Pure organizational fields — hold no value ---
	'message_field'          => [
		'key'     => 'field_message',
		'label'   => 'Message Field',
		'name'    => 'message_field',
		'type'    => 'message',
		'message' => 'Some message content',
		'value'   => null,
	],
	'tab_field'              => [
		'key'   => 'field_tab',
		'label' => 'Tab Field',
		'name'  => 'tab_field',
		'type'  => 'tab',
		'value' => null,
	],
	'accordion_field'        => [
		'key'   => 'field_accordion',
		'label' => 'Accordion Field',
		'name'  => 'accordion_field',
		'type'  => 'accordion',
		'value' => null,
	],
];
