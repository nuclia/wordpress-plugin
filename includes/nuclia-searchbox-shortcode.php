<?php
/**
 * Nuclia_Search_Shortcode file.
 *
 * @since   1.0.0
 *
 * *
 * Shortcode : [agentic_rag_searchbox features="navigateToLink,suggestions" proxy="true" show_config="true"]
 * Zone and KBID default to plugin settings when omitted.
 */

namespace Progress\WPSWN;

\add_shortcode( 'agentic_rag_searchbox', 'Progress\WPSWN\nuclia_searchbox' );

function nuclia_searchbox( $atts = [] ): string {
    $atts = (array) $atts;

    $atts = shortcode_atts(
        [
            'zone'     => '',
            'kbid'     => '',
            'features' => 'navigateToLink',
            'proxy'    => 'false',
            'show_config' => 'false',
        ],
        $atts,
        'agentic_rag_searchbox'
    );

    $zone     = $atts['zone'];
    $kbid     = $atts['kbid'];
    $features = $atts['features'];
    $proxy    = $atts['proxy'];
    $show_config = $atts['show_config'];

	if ( empty( $zone ) ) {
		$zone = sanitize_text_field( (string) get_option( 'nuclia_zone', '' ) );
	}

	if ( empty( $kbid ) ) {
		$kbid = sanitize_text_field( (string) get_option( 'nuclia_kbid', '' ) );
	}

	// We need zone and kbid, either from shortcode attributes or saved settings.
	if ( empty($zone) || empty($kbid) ) :
		if ( current_user_can('edit_posts')) {
			return sprintf(
				'<div style="color:red; border: 2px dotted red; padding: .5em;">%s</div>',
				__( 'Nuclia shortcode misconfigured. Please set your zone and Knowledge Box ID in plugin settings, or pass zone and kbid in the shortcode.', 'progress-agentic-rag' )
			);
		} else {
			return '';
		}
	endif;
	
	// sanitize atts
	$zone = sanitize_title( $zone );
	$kbid = sanitize_title( $kbid );
	
	// available features (navigateToLink = open source in new tab instead of embedded PDF viewer; avoids PDF.js offsetParent scroll error)
	$nuclia_searchbox_features = ["navigateToLink","answers","rephrase","filter","suggestions","autocompleteFromNERs","llmCitations","hideResults"];

	// $nuclia_searchbox_features as string: "answers,rephrase,filter,suggestions,autocompleteFromNERs,llmCitations,hideResults"
	$features = explode( ',', $features );
	$features = array_filter( $features, 'sanitize_title' );
	$features = array_intersect( $nuclia_searchbox_features, $features );
	$features = implode( ',', $features );
	$proxy_enabled = nuclia_searchbox_parse_bool( $proxy, false );
	$show_config_enabled = nuclia_searchbox_parse_bool( $show_config, false );
	
	// TODO : check if the searchbox is available : is_valid_credentials( $zone, $kbid ); 
	
	// enqueue script
	wp_enqueue_script('nuclia-widget', "https://cdn.rag.progress.cloud/nuclia-widget.umd.js", [], false, true );
	wp_enqueue_script(
		'nuclia-searchbox-config',
		PROGRESS_NUCLIA_PLUGIN_URL . 'includes/public/js/nuclia-searchbox-config.js',
		[],
		PROGRESS_NUCLIA_VERSION,
		true
	);

	$selected_config_id = '';
	$select_markup = '';
	$search_config_style = '';

	if ( $show_config_enabled ) {
		$search_configs = nuclia_searchbox_fetch_search_configurations( $zone, $kbid );
		$options_markup = '';

		if ( ! empty( $search_configs ) ) {
			foreach ( $search_configs as $index => $config ) {
				$config_id = (string) ( $config['id'] ?? '' );
				if ( $config_id === '' ) {
					continue;
				}
				$label = (string) ( $config['label'] ?? $config_id );
				$is_selected = $index === 0;
				if ( $is_selected ) {
					$selected_config_id = $config_id;
				}
				$options_markup .= sprintf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $config_id ),
					$is_selected ? ' selected' : '',
					esc_html( $label )
				);
			}
		} else {
			$options_markup = sprintf(
				'<option value="">%s</option>',
				esc_html__( 'No configurations available', 'progress-agentic-rag' )
			);
		}

		$select_markup = sprintf(
			'<div class="pl-nuclia-search-config">
				<label class="pl-nuclia-label">%1$s</label>
				<select class="pl-nuclia-searchbox-select" aria-label="%1$s"%2$s>%3$s</select>
			</div>',
			esc_html__( 'Search configuration', 'progress-agentic-rag' ),
			empty( $search_configs ) ? ' disabled' : '',
			$options_markup
		);
		$search_config_style = '<style>
			.pl-nuclia-search-config{
				margin:0 0 22px;
				padding:12px 14px;
				border:1px solid #d0d5dd;
				border-radius:12px;
				background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);
				box-shadow:0 1px 2px rgba(16,24,40,.06);
				max-width:460px;
			}
			.pl-nuclia-search-config .pl-nuclia-label{
				display:block;
				margin:0 0 8px;
				font-size:13px;
				font-weight:600;
				color:#344054;
				letter-spacing:.01em;
			}
			.pl-nuclia-searchbox-select{
				width:100%;
				min-height:40px;
				padding:8px 42px 8px 12px;
				border:1px solid #98a2b3;
				border-radius:10px;
				background-color:#ffffff;
				background-image:url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2214%22 height=%229%22 viewBox=%220 0 14 9%22%3E%3Cpath fill=%22%23475667%22 d=%22M7 9a1 1 0 0 1-.707-.293l-6-6A1 1 0 0 1 1.707 1.293L7 6.586l5.293-5.293a1 1 0 1 1 1.414 1.414l-6 6A1 1 0 0 1 7 9Z%22/%3E%3C/svg%3E");
				background-repeat:no-repeat;
				background-position:right 14px center;
				background-size:14px 9px;
				color:#101828;
				font-size:14px;
				line-height:1.4;
				appearance:none;
				-webkit-appearance:none;
				transition:border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
			}
			.pl-nuclia-searchbox-select:hover{
				border-color:#667085;
				background-color:#fcfcfd;
			}
			.pl-nuclia-searchbox-select:focus{
				outline:none;
				border-color:#1570ef;
				box-shadow:0 0 0 4px rgba(21,112,239,.18);
				background-color:#ffffff;
			}
			.pl-nuclia-searchbox-select:disabled{
				color:#98a2b3;
				border-color:#d0d5dd;
				background-color:#f2f4f7;
				cursor:not-allowed;
			}
		</style>';
	}

	$backend_attr = '';
	$proxy_attr = '';
	$api_key_attr = '';
	if ( $proxy_enabled ) {
		$backend_attr = ' backend="' . esc_url( nuclia_proxy_url( $zone ) ) . '"';
		$proxy_attr = ' proxy="true"';
	} else {
		$api_key = sanitize_text_field( (string) get_option( 'nuclia_token', '' ) );
		if ( $api_key !== '' ) {
			$api_key_attr = ' apiKey="' . esc_attr( $api_key ) . '"';
		}
	}

	$searchbox = sprintf(
		'<div class="pl-nuclia-searchbox">%9$s%4$s<nuclia-search-bar
		  knowledgebox="%1$s"
		  zone="%2$s"
		  features="%3$s"
		  %6$s%7$s%8$s></nuclia-search-bar>
		<nuclia-search-results ></nuclia-search-results></div>',
		sanitize_title( $kbid ),
		sanitize_title( $zone ),
		$features,
		$select_markup,
		$selected_config_id !== '' ? ' search_config_id="' . esc_attr( $selected_config_id ) . '"' : '',
		$backend_attr,
		$proxy_attr,
		$api_key_attr,
		$search_config_style
	);

	return $searchbox;
}

function nuclia_searchbox_parse_bool( mixed $value, bool $default = false ): bool {
	if ( is_bool( $value ) ) {
		return $value;
	}

	$normalized = strtolower( trim( sanitize_text_field( (string) $value ) ) );
	if ( $normalized === '' ) {
		return $default;
	}

	$truthy = [ '1', 'true', 'yes', 'on' ];
	$falsy = [ '0', 'false', 'no', 'off' ];

	if ( in_array( $normalized, $truthy, true ) ) {
		return true;
	}

	if ( in_array( $normalized, $falsy, true ) ) {
		return false;
	}

	return $default;
}

function nuclia_searchbox_fetch_search_configurations( string $zone, string $kbid ): array {
	$zone = sanitize_text_field( $zone );
	$kbid = sanitize_text_field( $kbid );
	$token = sanitize_text_field( (string) get_option( 'nuclia_token', '' ) );

	if ( $zone === '' || $kbid === '' || $token === '' ) {
		return [];
	}

	$uri = sprintf(
		'https://%1$s.rag.progress.cloud/api/v1/kb/%2$s/search_configurations',
		rawurlencode( $zone ),
		rawurlencode( $kbid )
	);

	$response = wp_remote_get(
		$uri,
		[
			'method' => 'GET',
			'timeout' => 20,
			'headers' => [
				'X-NUCLIA-SERVICEACCOUNT' => 'Bearer ' . $token,
			],
		]
	);

	if ( is_wp_error( $response ) ) {
		return [];
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( $status !== 200 ) {
		return [];
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return nuclia_searchbox_normalize_search_configurations( $data );
}

function nuclia_searchbox_normalize_search_configurations( mixed $data ): array {
	if ( ! is_array( $data ) ) {
		return [];
	}

	$payload = $data['search_configurations'] ?? $data['configs'] ?? $data['configurations'] ?? $data;
	if ( ! is_array( $payload ) ) {
		return [];
	}

	$configs = [];
	$is_assoc = array_keys( $payload ) !== range( 0, count( $payload ) - 1 );

	if ( $is_assoc ) {
		foreach ( $payload as $key => $item ) {
			$id = is_string( $key ) ? $key : '';
			$label = '';
			if ( is_array( $item ) ) {
				$id = $item['id'] ?? $id;
				$label = $item['name'] ?? $item['title'] ?? $item['label'] ?? $id;
			} elseif ( is_string( $item ) ) {
				$label = $item;
				if ( $id === '' ) {
					$id = $item;
				}
			}

			$id = sanitize_text_field( (string) $id );
			$label = sanitize_text_field( (string) $label );
			if ( $id !== '' ) {
				$configs[] = [
					'id' => $id,
					'label' => $label !== '' ? $label : $id,
				];
			}
		}
	} else {
		foreach ( $payload as $item ) {
			$id = '';
			$label = '';
			if ( is_string( $item ) ) {
				$id = $item;
				$label = $item;
			} elseif ( is_array( $item ) ) {
				$id = $item['id'] ?? $item['name'] ?? $item['title'] ?? '';
				$label = $item['name'] ?? $item['title'] ?? $item['label'] ?? $id;
			}

			$id = sanitize_text_field( (string) $id );
			$label = sanitize_text_field( (string) $label );
			if ( $id !== '' ) {
				$configs[] = [
					'id' => $id,
					'label' => $label !== '' ? $label : $id,
				];
			}
		}
	}

	$unique = [];
	foreach ( $configs as $config ) {
		$unique[ $config['id'] ] = $config;
	}

	return array_values( $unique );
}
