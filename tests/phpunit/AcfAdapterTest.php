<?php
/**
 * ACF adapter tests.
 *
 * @package ProgressAgenticRag\Tests
 */

use PHPUnit\Framework\TestCase;
use ProgressAgenticRag\Indexing\AcfAdapter;

final class AcfAdapterTest extends TestCase {
	private const POST_ID = 47;

	protected function setUp(): void {
		$GLOBALS['progress_agentic_rag_test_acf_fields'] = [];
		$GLOBALS['progress_agentic_rag_test_posts']      = [];
		$GLOBALS['progress_agentic_rag_test_terms']      = [];
		$GLOBALS['progress_agentic_rag_test_users']      = [];
	}

	private function load_fixture(): array {
		return require __DIR__ . '/fixtures/acf-fields-example.php';
	}

	private function post(): WP_Post {
		return new WP_Post( [ 'ID' => self::POST_ID ] );
	}

	public function test_is_active_reflects_get_field_objects_availability(): void {
		$this->assertTrue( ( new AcfAdapter() )->is_active() );
	}

	public function test_extract_text_returns_empty_string_when_no_fields(): void {
		$GLOBALS['progress_agentic_rag_test_acf_fields'][ self::POST_ID ] = false;

		$this->assertSame( '', ( new AcfAdapter() )->extract_text( $this->post() ) );
	}

	public function test_extract_text_maps_full_fixture(): void {
		$GLOBALS['progress_agentic_rag_test_acf_fields'][ self::POST_ID ] = $this->load_fixture();

		// Referenced posts used by post_object/page_link/relationship fields.
		$GLOBALS['progress_agentic_rag_test_posts'][201] = new WP_Post(
			[
				'ID'          => 201,
				'post_title'  => 'Public Related Page',
				'post_status' => 'publish',
			]
		);
		$GLOBALS['progress_agentic_rag_test_posts'][202] = new WP_Post(
			[
				'ID'          => 202,
				'post_title'  => 'Secret Draft Page',
				'post_status' => 'draft',
			]
		);

		$GLOBALS['progress_agentic_rag_test_terms']['category'] = [
			3 => (object) [ 'term_id' => 3, 'name' => 'Eye Test' ],
			4 => (object) [ 'term_id' => 4, 'name' => 'Hearing Test' ],
		];

		$text = ( new AcfAdapter() )->extract_text( $this->post() );

		// Simple scalars.
		$this->assertStringContainsString( 'Text Field: Text content', $text );
		$this->assertStringContainsString( 'Text Area Field: Text area content', $text );
		$this->assertStringContainsString( 'Number Field: 1000', $text );
		$this->assertStringContainsString( 'Range Field: 40', $text );
		$this->assertStringContainsString( 'Email Field: email@gmail.com', $text );
		$this->assertStringContainsString( 'Url Field: https://website.com', $text );
		$this->assertStringContainsString( 'Select Field: choice 1', $text );
		$this->assertStringContainsString( 'Radio Button Field: choice 2', $text );
		$this->assertStringContainsString( 'Button Group Field: choice 2', $text );
		$this->assertStringContainsString( 'True/False Field: Yes', $text );
		$this->assertStringContainsString( 'Date Picker Field: 2026-09-17', $text );

		// WYSIWYG must be stripped of HTML.
		$this->assertStringContainsString( 'WYSIWYG Field: wys wyg content', $text );
		$this->assertStringNotContainsString( '<strong>', $text );

		// Security-sensitive fields must never appear.
		$this->assertStringNotContainsString( 'Password Field', $text );
		$this->assertStringNotContainsString( '123123123', $text );
		$this->assertStringNotContainsString( 'Color Picker Field', $text );
		$this->assertStringNotContainsString( 'OEmbed Field', $text );

		// Organizational fields hold no data.
		$this->assertStringNotContainsString( 'Message Field', $text );
		$this->assertStringNotContainsString( 'Tab Field', $text );
		$this->assertStringNotContainsString( 'Accordion Field', $text );

		// Multi-value fields.
		$this->assertStringContainsString( 'Checkbox Field: choice 2', $text );
		$this->assertStringContainsString( 'Taxonomy Field: Eye Test, Hearing Test', $text );

		// Reference fields: public post included, draft post's title excluded.
		$this->assertStringContainsString( 'Post Object Field: Public Related Page', $text );
		$this->assertStringContainsString( 'Page Link Field: Public Related Page', $text );
		$this->assertStringContainsString( 'Relationship Field: Public Related Page', $text );
		$this->assertStringNotContainsString( 'Secret Draft Page', $text );

		$this->assertStringContainsString( 'User Field: Jane Admin', $text );
		$this->assertStringContainsString( 'Linke Field: link text (http://website1.url)', $text );
		$this->assertStringContainsString( 'Google Map Field: 21-22 Queen Street, Exeter, EX4 3SH', $text );

		// Image/File/Gallery.
		$this->assertStringContainsString( 'Image Field: Storefront photo', $text );
		$this->assertStringNotContainsString( 'File Field', $text ); // No alt/caption/title present.
		$this->assertStringContainsString( 'Branch Gallery: Store front', $text );

		// Group.
		$this->assertStringContainsString(
			'Address: Street - 21-22 Queen Street; City - Exeter; Postcode - EX4 3SH; Country - UK',
			$text
		);

		// Repeater — generic rule, no special-cased "Closed" shortcut: the false
		// 'closed' toggle on Monday still renders as "Closed - No".
		$this->assertStringContainsString(
			'Opening Hours: Day - Monday; Open - 09:00; Close - 17:00; Closed - No; Day - Sunday; Closed - Yes',
			$text
		);

		// Flexible Content 
		$this->assertStringContainsString(
			'Hero: Heading - Welcome to our Exeter branch; Subheading - Open every day; CTA: Button Text - Book now; Button URL - https://example.com/book',
			$text
		);

		// Clone (group mode) reuses the Group rule.
		$this->assertStringContainsString(
			'Cloned Contact Info: Phone - 01392 349693; Address - 21-22 Queen Street, Exeter, EX4 3SH',
			$text
		);
	}

	public function test_password_and_color_picker_fields_are_always_excluded(): void {
		$GLOBALS['progress_agentic_rag_test_acf_fields'][ self::POST_ID ] = [
			'password_field' => [
				'key'   => 'field_password',
				'label' => 'Password Field',
				'name'  => 'password_field',
				'type'  => 'password',
				'value' => 'super-secret',
			],
			'color_field'    => [
				'key'   => 'field_color',
				'label' => 'Color Field',
				'name'  => 'color_field',
				'type'  => 'color_picker',
				'value' => '#ff0000',
			],
		];

		$this->assertSame( '', ( new AcfAdapter() )->extract_text( $this->post() ) );
	}

	public function test_post_reference_excludes_password_protected_post(): void {
		$GLOBALS['progress_agentic_rag_test_posts'][301]                = new WP_Post(
			[
				'ID'            => 301,
				'post_title'    => 'Protected Page',
				'post_status'   => 'publish',
				'post_password' => 'secret',
			]
		);
		$GLOBALS['progress_agentic_rag_test_acf_fields'][ self::POST_ID ] = [
			'related' => [
				'key'   => 'field_related',
				'label' => 'Related',
				'name'  => 'related',
				'type'  => 'post_object',
				'value' => 301,
			],
		];

		$this->assertSame( '', ( new AcfAdapter() )->extract_text( $this->post() ) );
	}

	public function test_empty_repeater_and_group_values_are_skipped(): void {
		$GLOBALS['progress_agentic_rag_test_acf_fields'][ self::POST_ID ] = [
			'empty_group'    => [
				'key'        => 'field_empty_group',
				'label'      => 'Empty Group',
				'name'       => 'empty_group',
				'type'       => 'group',
				'sub_fields' => [
					[ 'key' => 'field_street', 'label' => 'Street', 'name' => 'street', 'type' => 'text' ],
				],
				'value'      => [ 'street' => '' ],
			],
			'empty_repeater' => [
				'key'        => 'field_empty_repeater',
				'label'      => 'Empty Repeater',
				'name'       => 'empty_repeater',
				'type'       => 'repeater',
				'sub_fields' => [],
				'value'      => [],
			],
		];

		$this->assertSame( '', ( new AcfAdapter() )->extract_text( $this->post() ) );
	}
}
