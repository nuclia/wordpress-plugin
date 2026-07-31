( function () {
	const config = window.progressAgenticRagAdmin || {};
	const startButton = document.querySelector( '[data-progress-agentic-rag-manual-sync]' );
	const deleteSyncedButton = document.querySelector( '[data-progress-agentic-rag-delete-synced]' );
	const labelReprocessButton = document.querySelector( '[data-progress-agentic-rag-label-reprocess]' );
	const labelReprocessCancelButton = document.querySelector( '[data-progress-agentic-rag-label-reprocess-cancel]' );
	const testConnectionButton = document.querySelector( '[data-progress-agentic-rag-test-connection]' );
	const connectionTestOutput = document.querySelector( '[data-progress-agentic-rag-connection-test-output]' );
	const retryFailedButton = document.querySelector( '[data-progress-agentic-rag-retry-failed-sync]' );
	const retryStatus = document.querySelector( '[data-progress-agentic-rag-retry-status]' );
	const singleDeleteStatus = document.querySelector( '[data-progress-agentic-rag-single-delete-status]' );
	const diagnosticsButton = document.querySelector( '[data-progress-agentic-rag-export-diagnostics]' );
	const diagnosticsOutput = document.querySelector( '[data-progress-agentic-rag-diagnostics-output]' );
	const diagnosticsStatus = document.querySelector( '[data-progress-agentic-rag-diagnostics-status]' );
	const syncedCount = document.querySelector( '[data-progress-agentic-rag-synced-count]' );
	const backgroundSync = document.querySelector( '[data-progress-agentic-rag-background-sync]' );
	const backgroundLayout = backgroundSync ? backgroundSync.closest( '.progress-agentic-rag__indexation-layout' ) : null;
	const taxonomySelect = document.querySelector( '[data-progress-agentic-rag-taxonomy-select]' );
	const mappingContainer = document.querySelector( '[data-progress-agentic-rag-mapping-container]' );
	const addMappingButton = document.querySelector( '[data-progress-agentic-rag-add-mapping]' );
	const modal = document.querySelector( '[data-progress-agentic-rag-sync-modal]' );
	const appearanceForm = document.querySelector( '[data-progress-agentic-rag-widget-appearance-form]' );
	const appearancePreview = document.querySelector( '[data-progress-agentic-rag-widget-preview]' );
	const defaultStartButtonText = config.strings && config.strings.sync ? config.strings.sync : startButton ? startButton.textContent : '';
	const defaultDeleteButtonText = deleteSyncedButton ? deleteSyncedButton.textContent : '';
	const startButtonInitiallyDisabled = startButton ? startButton.disabled : false;
	const deleteButtonInitiallyDisabled = deleteSyncedButton ? deleteSyncedButton.disabled : false;

	if ( appearanceForm && appearancePreview ) {
		const fontFamilies = {
			roboto: 'Roboto, Arial, sans-serif',
			inter: 'Inter, Arial, sans-serif',
			system: '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
		};
		const shadows = {
			none: 'none',
			subtle: '0 1px 2px rgb(16 24 40 / 5%)',
			soft: '0 12px 32px rgb(16 24 40 / 8%)',
		};
		const appearanceValue = ( name ) => {
			const input = appearanceForm.querySelector( '[name$="[' + name + ']"]' );

			return input ? input.value : '';
		};
		const updateAppearancePreview = () => {
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-accent-color', appearanceValue( 'accent_color' ) );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-text-color', appearanceValue( 'text_color' ) );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-muted-color', appearanceValue( 'muted_color' ) );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-surface-color', appearanceValue( 'surface_color' ) );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-border-color', appearanceValue( 'border_color' ) );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-font-family', fontFamilies[ appearanceValue( 'font_family' ) ] || fontFamilies.roboto );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-font-size', appearanceValue( 'font_size' ) + 'px' );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-line-height', appearanceValue( 'line_height' ) );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-border-radius', appearanceValue( 'border_radius' ) + 'px' );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-card-padding', appearanceValue( 'card_padding' ) + 'px' );
			appearancePreview.style.setProperty( '--progress-agentic-rag-widget-shadow', shadows[ appearanceValue( 'shadow' ) ] || shadows.soft );
		};

		appearanceForm.addEventListener( 'input', updateAppearancePreview );
		appearanceForm.addEventListener( 'change', updateAppearancePreview );
		updateAppearancePreview();
	}

	if ( ! config.ajaxUrl || ! config.nonce ) {
		return;
	}

	const selectors = {
		bar: '[data-progress-agentic-rag-sync-bar]',
		close: '[data-progress-agentic-rag-sync-close]',
		closeButton: '[data-progress-agentic-rag-modal-close]',
		completed: '[data-progress-agentic-rag-sync-completed]',
		completedLabel: '[data-progress-agentic-rag-sync-completed-label]',
		current: '[data-progress-agentic-rag-sync-current]',
		currentLabel: '[data-progress-agentic-rag-sync-current-label]',
		failed: '[data-progress-agentic-rag-sync-failed]',
		label: '[data-progress-agentic-rag-modal-label]',
		message: '[data-progress-agentic-rag-sync-message]',
		percent: '[data-progress-agentic-rag-sync-percent]',
		title: '[data-progress-agentic-rag-modal-title]',
		total: '[data-progress-agentic-rag-sync-total]',
		totalLabel: '[data-progress-agentic-rag-sync-total-label]',
	};
	let pollTimer = null;
	let deletePollTimer = null;
	let backgroundPollTimer = null;
	let syncStatus = config.initialSyncStatus || {};
	let deleteStatus = config.initialDeleteStatus || {};
	let backgroundSyncStatus = config.initialBackgroundSyncStatus || {};
	const mappingConfig = config.mapping || { taxonomies: {}, labelsets: [] };
	const selectorValue = ( value ) => window.CSS && window.CSS.escape ? window.CSS.escape( value ) : String( value ).replace( /"/g, '\\"' );

	const field = ( selector ) => modal.querySelector( selector );

	const setText = ( selector, value ) => {
		const element = field( selector );
		if ( element ) {
			element.textContent = value;
		}
	};

	const selectedPostTypes = () =>
		Array.from( document.querySelectorAll( 'input[name="indexable_post_types[]"]:checked' ) ).map( ( input ) => input.value );

	const currentSyncedCount = () => {
		const countElement = syncedCount || document.querySelector( '[data-progress-agentic-rag-label-synced-count]' );
		if ( ! countElement ) {
			return 0;
		}

		return Number.parseInt( countElement.textContent, 10 ) || 0;
	};

	const syncIsRunning = () => syncStatus && syncStatus.status === 'running';

	const deleteIsRunning = () => deleteStatus && deleteStatus.status === 'running';

	const backgroundSyncIsActive = () => backgroundSyncStatus && backgroundSyncStatus.is_active;

	const statusNumber = ( status, key ) => Number.parseInt( status && status[ key ], 10 ) || 0;

	const processedCount = ( status ) => {
		if ( status && status.processed !== undefined ) {
			return statusNumber( status, 'processed' );
		}

		return statusNumber( status, 'completed' ) + statusNumber( status, 'failed' );
	};

	const runningButtonText = ( status ) => {
		const total = statusNumber( status, 'total' );
		const percent = Math.max( 0, Math.min( 100, statusNumber( status, 'percent' ) ) );
		const runningText = config.strings.running || defaultStartButtonText;

		if ( total > 0 ) {
			return runningText + ' (' + Math.min( total, processedCount( status ) ) + '/' + total + ', ' + percent + '%)';
		}

		return runningText;
	};

	const deleteButtonText = ( status ) => {
		const total = statusNumber( status, 'total' );
		const percent = Math.max( 0, Math.min( 100, statusNumber( status, 'percent' ) ) );
		const runningText = config.strings.deleteRunning || defaultDeleteButtonText;

		if ( total > 0 ) {
			return runningText + ' (' + Math.min( total, processedCount( status ) ) + '/' + total + ', ' + percent + '%)';
		}

		return runningText;
	};

	const setModalMode = ( mode ) => {
		const isDelete = mode === 'delete';
		const closeButton = field( selectors.closeButton );

		setText( selectors.label, isDelete ? config.strings.deleteModalLabel : config.strings.syncModalLabel );
		setText( selectors.title, isDelete ? config.strings.deleteModalTitle : config.strings.syncModalTitle );
		setText( selectors.totalLabel, isDelete ? config.strings.deleteTotalLabel : config.strings.syncTotalLabel );
		setText( selectors.completedLabel, isDelete ? config.strings.deleteDoneLabel : config.strings.syncDoneLabel );
		setText( selectors.currentLabel, isDelete ? config.strings.deleteCurrentLabel : config.strings.syncCurrentLabel );

		if ( closeButton ) {
			closeButton.setAttribute( 'aria-label', isDelete ? config.strings.closeDeleteProgress : config.strings.closeSyncProgress );
		}
	};

	const request = async ( action, values = {} ) => {
		const body = new FormData();
		body.append( 'action', action );
		body.append( '_ajax_nonce', config.nonce );

		Object.entries( values ).forEach( ( [ key, value ] ) => {
			if ( Array.isArray( value ) ) {
				value.forEach( ( item ) => body.append( key + '[]', item ) );
				return;
			}

			body.append( key, value );
		} );

		const response = await fetch( config.ajaxUrl, {
			method: 'POST',
			body,
			credentials: 'same-origin',
		} );
		const payload = await response.json();

		if ( ! response.ok || ! payload.success ) {
			throw new Error( payload.data && payload.data.message ? payload.data.message : config.strings.failed );
		}

		return payload.data;
	};

	const renderMessage = ( container, message, isError = false ) => {
		if ( ! container ) {
			return;
		}

		container.textContent = message || '';
		container.classList.toggle( 'progress-agentic-rag__inline-message--error', Boolean( isError ) );
	};

	const connectionFormValues = () => {
		const form = testConnectionButton ? testConnectionButton.closest( 'form' ) : null;
		const values = {};

		if ( ! form ) {
			return values;
		}

		Array.from( form.querySelectorAll( 'input[name]' ) ).forEach( ( input ) => {
			if ( input.name !== 'action' && input.name !== '_wpnonce' ) {
				values[ input.name ] = input.value;
			}
		} );

		return values;
	};

	const copyText = async ( text ) => {
		if ( window.navigator.clipboard && window.navigator.clipboard.writeText ) {
			await window.navigator.clipboard.writeText( text );
		}
	};

	const openModal = () => {
		modal.hidden = false;
		document.body.classList.add( 'progress-agentic-rag-sync-open' );
		const closeButton = modal.querySelector( '.progress-agentic-rag__modal-close' );
		if ( closeButton ) {
			closeButton.focus();
		}
	};

	const closeModal = () => {
		modal.hidden = true;
		document.body.classList.remove( 'progress-agentic-rag-sync-open' );
		if ( pollTimer && ! syncIsRunning() ) {
			window.clearTimeout( pollTimer );
			pollTimer = null;
		}
		if ( deletePollTimer && ! deleteIsRunning() ) {
			window.clearTimeout( deletePollTimer );
			deletePollTimer = null;
		}
		startButton.disabled = deleteIsRunning() ? true : startButtonInitiallyDisabled;
	};

	const renderStatus = ( status ) => {
		syncStatus = status || {};
		if ( pollTimer ) {
			window.clearTimeout( pollTimer );
			pollTimer = null;
		}

		const percent = statusNumber( status, 'percent' );
		const bar = field( selectors.bar );

		if ( bar ) {
			bar.style.width = Math.max( 0, Math.min( 100, percent ) ) + '%';
		}

		setText( selectors.percent, percent + '%' );
		setText( selectors.message, status.message || config.strings.starting );
		setText( selectors.total, String( status.total || 0 ) );
		setText( selectors.completed, String( status.completed || 0 ) );
		setText( selectors.failed, String( status.failed || 0 ) );
		setText( selectors.current, status.current || '' );

		if ( syncIsRunning() ) {
			startButton.textContent = runningButtonText( status );
			startButton.disabled = false;
			pollTimer = window.setTimeout( pollStatus, 2000 );
			return;
		}

		if ( status.status !== 'starting' ) {
			startButton.textContent = defaultStartButtonText;
			startButton.disabled = startButtonInitiallyDisabled;
		}
	};

	const renderError = ( message ) => {
		renderStatus( {
			status: 'failed',
			total: 0,
			completed: 0,
			failed: 0,
			current: '',
			message,
			percent: 0,
		} );
	};

	const pollStatus = async () => {
		try {
			renderStatus( await request( 'progress_agentic_rag_manual_sync_status' ) );
		} catch ( error ) {
			startButton.disabled = false;
			renderError( error.message );
		}
	};

	const renderDeleteStatus = ( status, reloadOnComplete = false ) => {
		if ( ! deleteSyncedButton ) {
			return;
		}

		deleteStatus = status || {};
		if ( deletePollTimer ) {
			window.clearTimeout( deletePollTimer );
			deletePollTimer = null;
		}

		const percent = statusNumber( status, 'percent' );
		const bar = field( selectors.bar );

		if ( bar ) {
			bar.style.width = Math.max( 0, Math.min( 100, percent ) ) + '%';
		}

		setText( selectors.percent, percent + '%' );
		setText( selectors.message, status.message || config.strings.deleting );
		setText( selectors.total, String( status.total || 0 ) );
		setText( selectors.completed, String( status.deleted || status.completed || 0 ) );
		setText( selectors.failed, String( status.failed || 0 ) );
		setText( selectors.current, status.current || '' );

		if ( deleteIsRunning() ) {
			deleteSyncedButton.textContent = deleteButtonText( status );
			deleteSyncedButton.disabled = false;
			startButton.disabled = true;
			deletePollTimer = window.setTimeout( pollDeleteStatus, 2000 );
			return;
		}

		if ( reloadOnComplete && status && status.status === 'complete' ) {
			if ( status.message ) {
				window.alert( status.message );
			}
			window.location.reload();
			return;
		}

		if ( status.status !== 'starting' ) {
			deleteSyncedButton.textContent = defaultDeleteButtonText;
			deleteSyncedButton.disabled = deleteButtonInitiallyDisabled;
			if ( ! syncIsRunning() ) {
				startButton.disabled = startButtonInitiallyDisabled;
			}
		}
	};

	const setBackgroundText = ( selector, value ) => {
		if ( ! backgroundSync ) {
			return;
		}

		const element = backgroundSync.querySelector( selector );
		if ( element ) {
			element.textContent = value;
		}
	};

	const renderBackgroundSyncStatus = ( status ) => {
		if ( ! backgroundSync ) {
			return;
		}

		status = status || {};
		backgroundSyncStatus = status || {};
		backgroundSync.hidden = ! backgroundSyncIsActive();
		if ( backgroundLayout ) {
			backgroundLayout.classList.toggle( 'progress-agentic-rag__indexation-layout--has-background', backgroundSyncIsActive() );
		}

		if ( backgroundPollTimer ) {
			window.clearTimeout( backgroundPollTimer );
			backgroundPollTimer = null;
		}

		const percent = Math.max( 0, Math.min( 100, statusNumber( status, 'percent' ) ) );
		const track = backgroundSync.querySelector( '[data-progress-agentic-rag-background-track]' );
		const bar = backgroundSync.querySelector( '[data-progress-agentic-rag-background-bar]' );

		if ( track ) {
			track.setAttribute( 'aria-valuenow', String( percent ) );
		}

		if ( bar ) {
			bar.style.width = percent + '%';
		}

		setBackgroundText( '[data-progress-agentic-rag-background-percent]', percent + '%' );
		setBackgroundText( '[data-progress-agentic-rag-background-message]', status.message || '' );
		setBackgroundText( '[data-progress-agentic-rag-background-processed]', String( status.processed || 0 ) );
		setBackgroundText( '[data-progress-agentic-rag-background-total]', String( status.total || 0 ) );
		setBackgroundText( '[data-progress-agentic-rag-background-failed]', String( status.failed || 0 ) );
		setBackgroundText( '[data-progress-agentic-rag-background-pending]', String( status.pending || 0 ) );
		setBackgroundText( '[data-progress-agentic-rag-background-running]', String( status.running || 0 ) );
		setBackgroundText( '[data-progress-agentic-rag-background-current]', status.current || '' );

		backgroundPollTimer = window.setTimeout( pollBackgroundSyncStatus, backgroundSyncIsActive() ? 2000 : 8000 );
	};

	const pollBackgroundSyncStatus = async () => {
		try {
			renderBackgroundSyncStatus( await request( 'progress_agentic_rag_background_sync_status' ) );
		} catch ( error ) {
			backgroundPollTimer = window.setTimeout( pollBackgroundSyncStatus, 10000 );
		}
	};

	const pollDeleteStatus = async () => {
		try {
			renderDeleteStatus( await request( 'progress_agentic_rag_delete_synced_resources_status' ), true );
		} catch ( error ) {
			if ( deleteSyncedButton ) {
				deleteSyncedButton.disabled = false;
			}
			window.alert( error.message || config.strings.deleteFailed );
		}
	};

	const setMappingMessage = ( container, message ) => {
		container.replaceChildren();
		const notice = document.createElement( 'em' );
		notice.textContent = message;
		container.appendChild( notice );
	};

	const labelsetOptions = () => {
		const fragment = document.createDocumentFragment();
		const emptyOption = document.createElement( 'option' );
		emptyOption.value = '';
		emptyOption.textContent = config.strings.mappingSelectLabelset;
		fragment.appendChild( emptyOption );

		( mappingConfig.labelsets || [] ).forEach( ( labelset ) => {
			const option = document.createElement( 'option' );
			option.value = labelset;
			option.textContent = labelset;
			fragment.appendChild( option );
		} );

		return fragment;
	};

	const labelCheckboxName = ( taxonomy, termId, fallback ) =>
		fallback ?
			'nuclia_taxonomy_label_map[' + taxonomy + '][fallback][labels][]' :
			'nuclia_taxonomy_label_map[' + taxonomy + '][terms][' + termId + '][]';

	const renderLabelCheckboxes = ( container, taxonomy, labels, termId = '', fallback = false ) => {
		container.replaceChildren();

		if ( ! labels.length ) {
			setMappingMessage( container, config.strings.mappingNoLabels );
			return;
		}

		labels.forEach( ( label ) => {
			const wrapper = document.createElement( 'label' );
			const input = document.createElement( 'input' );
			const text = document.createElement( 'span' );

			wrapper.className = 'progress-agentic-rag__label-checkbox';
			input.type = 'checkbox';
			input.value = label;
			input.name = labelCheckboxName( taxonomy, termId, fallback );
			text.textContent = label;

			wrapper.append( input, text );
			container.appendChild( wrapper );
		} );
	};

	const loadMappingLabels = async ( labelset ) => {
		if ( ! labelset ) {
			return [];
		}

		const data = await request( 'progress_agentic_rag_get_labelset_labels', { labelset } );
		return Array.isArray( data.labels ) ? data.labels : [];
	};

	const buildMappingBlock = ( taxonomy ) => {
		const taxonomyConfig = mappingConfig.taxonomies[ taxonomy ];
		const block = document.createElement( 'div' );
		const header = document.createElement( 'div' );
		const titleWrap = document.createElement( 'div' );
		const title = document.createElement( 'h4' );
		const code = document.createElement( 'code' );
		const remove = document.createElement( 'button' );
		const labelsetLabel = document.createElement( 'label' );
		const labelsetText = document.createElement( 'span' );
		const labelsetSelect = document.createElement( 'select' );
		const table = document.createElement( 'table' );
		const thead = document.createElement( 'thead' );
		const tbody = document.createElement( 'tbody' );
		const fallback = document.createElement( 'div' );
		const fallbackTitle = document.createElement( 'p' );
		const fallbackStrong = document.createElement( 'strong' );
		const fallbackLabel = document.createElement( 'label' );
		const fallbackText = document.createElement( 'span' );
		const fallbackSelect = document.createElement( 'select' );
		const fallbackLabels = document.createElement( 'div' );

		block.className = 'progress-agentic-rag__mapping-block';
		block.dataset.progressAgenticRagMappingBlock = '';
		block.dataset.taxonomy = taxonomy;

		header.className = 'progress-agentic-rag__mapping-block-header';
		title.textContent = taxonomyConfig.label;
		code.textContent = taxonomy;
		remove.type = 'button';
		remove.className = 'progress-agentic-rag__button progress-agentic-rag__button--danger';
		remove.dataset.progressAgenticRagRemoveMapping = '';
		remove.textContent = config.strings.mappingRemove;
		titleWrap.append( title, code );
		header.append( titleWrap, remove );

		labelsetLabel.className = 'progress-agentic-rag__mapping-select';
		labelsetText.textContent = config.strings.mappingLabelset;
		labelsetSelect.name = 'nuclia_taxonomy_label_map[' + taxonomy + '][labelset]';
		labelsetSelect.dataset.progressAgenticRagLabelsetSelect = '';
		labelsetSelect.dataset.taxonomy = taxonomy;
		labelsetSelect.appendChild( labelsetOptions() );
		labelsetLabel.append( labelsetText, labelsetSelect );

		table.className = 'progress-agentic-rag__mapping-table';
		{
			const headerRow = document.createElement( 'tr' );
			const termHeading = document.createElement( 'th' );
			const labelsHeading = document.createElement( 'th' );

			termHeading.scope = 'col';
			termHeading.textContent = config.strings.mappingTerm;
			labelsHeading.scope = 'col';
			labelsHeading.textContent = config.strings.mappingLabels;
			headerRow.append( termHeading, labelsHeading );
			thead.appendChild( headerRow );
		}

		( taxonomyConfig.terms || [] ).forEach( ( term ) => {
			const row = document.createElement( 'tr' );
			const termCell = document.createElement( 'th' );
			const labelsCell = document.createElement( 'td' );
			const labels = document.createElement( 'div' );

			termCell.scope = 'row';
			termCell.textContent = term.name;
			labels.className = 'progress-agentic-rag__label-checkboxes';
			labels.dataset.progressAgenticRagLabelCheckboxes = '';
			labels.dataset.taxonomy = taxonomy;
			labels.dataset.termId = String( term.id );
			setMappingMessage( labels, config.strings.mappingSelectLabels );
			labelsCell.appendChild( labels );
			row.append( termCell, labelsCell );
			tbody.appendChild( row );
		} );

		table.append( thead, tbody );

		fallback.className = 'progress-agentic-rag__fallback';
		fallbackStrong.textContent = config.strings.mappingFallback;
		fallbackTitle.appendChild( fallbackStrong );
		fallbackLabel.className = 'progress-agentic-rag__mapping-select';
		fallbackText.textContent = config.strings.mappingLabelset;
		fallbackSelect.name = 'nuclia_taxonomy_label_map[' + taxonomy + '][fallback][labelset]';
		fallbackSelect.dataset.progressAgenticRagFallbackLabelsetSelect = '';
		fallbackSelect.dataset.taxonomy = taxonomy;
		fallbackSelect.appendChild( labelsetOptions() );
		fallbackLabel.append( fallbackText, fallbackSelect );
		fallbackLabels.className = 'progress-agentic-rag__fallback-labels';
		fallbackLabels.dataset.progressAgenticRagFallbackLabels = '';
		fallbackLabels.dataset.taxonomy = taxonomy;
		setMappingMessage( fallbackLabels, config.strings.mappingSelectLabels );
		fallback.append( fallbackTitle, fallbackLabel, fallbackLabels );

		block.append( header, labelsetLabel );
		if ( ( taxonomyConfig.terms || [] ).length ) {
			block.appendChild( table );
		} else {
			const message = document.createElement( 'p' );
			message.className = 'progress-agentic-rag__mapping-muted';
			message.textContent = config.strings.mappingNoTerms;
			block.appendChild( message );
		}
		block.appendChild( fallback );

		return block;
	};

	const restoreTaxonomyOption = ( taxonomy ) => {
		if ( ! taxonomySelect || ! mappingConfig.taxonomies[ taxonomy ] ) {
			return;
		}

		const option = document.createElement( 'option' );
		option.value = taxonomy;
		option.textContent = mappingConfig.taxonomies[ taxonomy ].label;
		taxonomySelect.appendChild( option );
	};

	if ( addMappingButton && taxonomySelect && mappingContainer ) {
		addMappingButton.addEventListener( 'click', () => {
			const taxonomy = taxonomySelect.value;

			if ( ! taxonomy || ! mappingConfig.taxonomies[ taxonomy ] || mappingContainer.querySelector( '[data-taxonomy="' + selectorValue( taxonomy ) + '"]' ) ) {
				return;
			}

			mappingContainer.appendChild( buildMappingBlock( taxonomy ) );
			const option = taxonomySelect.querySelector( 'option[value="' + selectorValue( taxonomy ) + '"]' );
			if ( option ) {
				option.remove();
			}
			taxonomySelect.value = '';
		} );

		mappingContainer.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-progress-agentic-rag-remove-mapping]' );
			if ( ! button ) {
				return;
			}

			const block = button.closest( '[data-progress-agentic-rag-mapping-block]' );
			if ( ! block ) {
				return;
			}

			restoreTaxonomyOption( block.dataset.taxonomy );
			block.remove();
		} );

		mappingContainer.addEventListener( 'change', async ( event ) => {
			const select = event.target.closest( '[data-progress-agentic-rag-labelset-select], [data-progress-agentic-rag-fallback-labelset-select]' );
			if ( ! select ) {
				return;
			}

			const taxonomy = select.dataset.taxonomy;
			const isFallback = select.matches( '[data-progress-agentic-rag-fallback-labelset-select]' );
			const containers = isFallback ?
				[ mappingContainer.querySelector( '[data-progress-agentic-rag-fallback-labels][data-taxonomy="' + selectorValue( taxonomy ) + '"]' ) ] :
				Array.from( mappingContainer.querySelectorAll( '[data-progress-agentic-rag-label-checkboxes][data-taxonomy="' + selectorValue( taxonomy ) + '"]' ) );

			containers.filter( Boolean ).forEach( ( container ) => setMappingMessage( container, select.value ? config.strings.mappingLoadingLabels : config.strings.mappingSelectLabels ) );
			if ( ! select.value ) {
				return;
			}

			try {
				const labels = await loadMappingLabels( select.value );
				containers.filter( Boolean ).forEach( ( container ) => {
					renderLabelCheckboxes( container, taxonomy, labels, container.dataset.termId || '', isFallback );
				} );
			} catch ( error ) {
				containers.filter( Boolean ).forEach( ( container ) => setMappingMessage( container, config.strings.mappingLabelsFailed ) );
			}
		} );
	}

	if ( testConnectionButton && connectionTestOutput ) {
		testConnectionButton.addEventListener( 'click', async () => {
			testConnectionButton.disabled = true;
			renderMessage( connectionTestOutput, config.strings.testConnection || '' );

			try {
				const result = await request( 'progress_agentic_rag_test_connection', connectionFormValues() );
				renderMessage( connectionTestOutput, ( result.checked_at_label ? result.checked_at_label + ': ' : '' ) + ( result.message || '' ), ! result.connected );
			} catch ( error ) {
				renderMessage( connectionTestOutput, error.message || config.strings.connectionTestFailed, true );
			} finally {
				testConnectionButton.disabled = false;
			}
		} );
	}

	if ( retryFailedButton ) {
		retryFailedButton.addEventListener( 'click', async () => {
			retryFailedButton.disabled = true;

			try {
				const status = await request( 'progress_agentic_rag_retry_failed_sync' );
				renderMessage( retryStatus, status.message || '' );
				renderBackgroundSyncStatus( status );
			} catch ( error ) {
				renderMessage( retryStatus, error.message || config.strings.retryFailed, true );
				retryFailedButton.disabled = false;
			}
		} );
	}

	document.addEventListener( 'click', async ( event ) => {
		const button = event.target.closest( '[data-progress-agentic-rag-delete-single-synced]' );
		if ( ! button ) {
			return;
		}

		const postId = button.dataset.progressAgenticRagDeleteSingleSynced;
		if ( ! postId || ! window.confirm( config.strings.confirmDeleteSingle || '' ) ) {
			return;
		}

		button.disabled = true;
		renderMessage( singleDeleteStatus, config.strings.deleting || '' );

		try {
			const result = await request( 'progress_agentic_rag_delete_synced_resource', { post_id: postId } );
			const row = button.closest( '[data-progress-agentic-rag-synced-row]' );
			if ( row ) {
				row.remove();
			}
			renderMessage( singleDeleteStatus, result.message || config.strings.deleteSingleComplete || '' );
		} catch ( error ) {
			button.disabled = false;
			renderMessage( singleDeleteStatus, error.message || config.strings.deleteSingleFailed, true );
		}
	} );

	if ( diagnosticsButton && diagnosticsOutput ) {
		diagnosticsButton.addEventListener( 'click', async () => {
			diagnosticsButton.disabled = true;

			try {
				const data = await request( 'progress_agentic_rag_export_diagnostics' );
				const text = JSON.stringify( data.diagnostics || {}, null, 2 );
				diagnosticsOutput.value = text;
				await copyText( text );
				renderMessage( diagnosticsStatus, config.strings.diagnosticsCopied || '' );
			} catch ( error ) {
				renderMessage( diagnosticsStatus, error.message || config.strings.diagnosticsFailed, true );
			} finally {
				diagnosticsButton.disabled = false;
			}
		} );
	}

	if ( startButton && modal ) {
		startButton.addEventListener( 'click', async () => {
			setModalMode( 'sync' );
			openModal();

			if ( syncIsRunning() ) {
				try {
					renderStatus( await request( 'progress_agentic_rag_manual_sync_status' ) );
				} catch ( error ) {
					renderError( error.message );
				}
				return;
			}

			const postTypes = selectedPostTypes();
			startButton.disabled = true;

			try {
				renderStatus( {
					status: 'starting',
					total: 0,
					completed: 0,
					failed: 0,
					current: '',
					message: config.strings.starting,
					percent: 0,
				} );
				renderStatus( await request( 'progress_agentic_rag_manual_sync_start', { post_types: postTypes } ) );
			} catch ( error ) {
				startButton.disabled = false;
				renderError( error.message );
			}
		} );

		if ( syncIsRunning() ) {
			renderStatus( syncStatus );
		}

		if ( deleteSyncedButton && deleteIsRunning() ) {
			renderDeleteStatus( deleteStatus );
		}

		renderBackgroundSyncStatus( backgroundSyncStatus );

		if ( deleteSyncedButton ) {
			deleteSyncedButton.addEventListener( 'click', async () => {
				if ( deleteIsRunning() ) {
					try {
						setModalMode( 'delete' );
						openModal();
						renderDeleteStatus( await request( 'progress_agentic_rag_delete_synced_resources_status' ), true );
					} catch ( error ) {
						window.alert( error.message || config.strings.deleteFailed );
					}
					return;
				}

				const count = currentSyncedCount();
				const message = ( config.strings.confirmDelete || '' ).replace( '%d', String( count ) );
				if ( ! window.confirm( message ) ) {
					return;
				}

				deleteSyncedButton.disabled = true;
				deleteSyncedButton.textContent = config.strings.deleting || defaultDeleteButtonText;
				setModalMode( 'delete' );
				openModal();

				try {
					renderDeleteStatus( {
						status: 'starting',
						total: 0,
						deleted: 0,
						failed: 0,
						current: '',
						message: config.strings.deleting,
						percent: 0,
					} );
					renderDeleteStatus( await request( 'progress_agentic_rag_delete_synced_resources' ), true );
				} catch ( error ) {
					deleteSyncedButton.disabled = false;
					deleteSyncedButton.textContent = defaultDeleteButtonText;
					window.alert( error.message || config.strings.deleteFailed );
				}
			} );
		}

		modal.querySelectorAll( selectors.close ).forEach( ( closeButton ) => {
			closeButton.addEventListener( 'click', closeModal );
		} );
	}

	if ( labelReprocessButton ) {
		labelReprocessButton.addEventListener( 'click', async () => {
			const count = currentSyncedCount();
			const message = ( config.strings.confirmReprocess || '' ).replace( '%d', String( count ) );
			if ( ! window.confirm( message ) ) {
				return;
			}

			labelReprocessButton.disabled = true;

			try {
				window.alert( ( await request( 'progress_agentic_rag_label_reprocess_start' ) ).message || '' );
				window.location.reload();
			} catch ( error ) {
				labelReprocessButton.disabled = false;
				window.alert( error.message || config.strings.reprocessFailed );
			}
		} );
	}

	if ( labelReprocessCancelButton ) {
		labelReprocessCancelButton.addEventListener( 'click', async () => {
			labelReprocessCancelButton.disabled = true;

			try {
				await request( 'progress_agentic_rag_label_reprocess_cancel' );
				window.location.reload();
			} catch ( error ) {
				labelReprocessCancelButton.disabled = false;
				window.alert( error.message || config.strings.reprocessFailed );
			}
		} );
	}

}() );
