(function() {
	'use strict';

	var statusPollInterval = null;
	var isPolling = false;
	var reprocessPollInterval = null;
	var isReprocessPolling = false;

	/**
	 * Get internationalized string.
	 *
	 * @param {string} key The translation key.
	 * @param {*} defaultValue Default value if not found.
	 * @return {*} The translated string or default.
	 */
	function __(key, defaultValue) {
		if (typeof nucliaReindex !== 'undefined' && nucliaReindex.i18n && nucliaReindex.i18n[key]) {
			return nucliaReindex.i18n[key];
		}
		return defaultValue;
	}

	document.addEventListener('DOMContentLoaded', function() {
		// Schedule indexing buttons
		var scheduleButtons = document.querySelectorAll('.nuclia-schedule-button');
		scheduleButtons.forEach(function(button) {
			button.addEventListener('click', handleScheduleButtonClick);
		});

		// Cancel buttons (per post type)
		var cancelButtons = document.querySelectorAll('.nuclia-cancel-button');
		cancelButtons.forEach(function(button) {
			button.addEventListener('click', handleCancelButtonClick);
		});

		// Cancel all button
		var cancelAllButton = document.querySelector('.nuclia-cancel-all-button');
		if (cancelAllButton) {
			cancelAllButton.addEventListener('click', handleCancelAllButtonClick);
		}

		// Start polling if there are pending jobs
		var pendingStatus = document.querySelector('.nuclia-pending-count');
		if (pendingStatus) {
			startStatusPolling();
		}

		// Labelset change handlers (delegate for dynamic blocks)
		document.addEventListener('change', function(e) {
			if (e.target && e.target.classList.contains('nuclia-labelset-select')) {
				handleLabelsetChange(e);
			}
		if (e.target && e.target.classList.contains('nuclia-fallback-labelset-select')) {
			handleFallbackLabelsetChange(e);
		}
		});

		// Add mapping button
		var addMappingButton = document.querySelector('.nuclia-add-mapping');
		if (addMappingButton) {
			addMappingButton.addEventListener('click', handleAddMappingClick);
		}

		// Remove mapping buttons (delegate)
		document.addEventListener('click', function(e) {
			if (e.target && e.target.classList.contains('nuclia-remove-mapping')) {
				e.preventDefault();
				handleRemoveMappingClick(e);
			}
		});

		// Clear synced files button
		var clearSyncedButton = document.getElementById('nuclia-clear-synced-button');
		if (clearSyncedButton) {
			clearSyncedButton.addEventListener('click', handleClearSyncedClick);
		}

		// Label reprocessing button
		var reprocessButton = document.querySelector('.nuclia-reprocess-button');
		if (reprocessButton) {
			reprocessButton.addEventListener('click', handleReprocessLabelsClick);
		}

		// Cancel reprocessing button
		var cancelReprocessButton = document.querySelector('.nuclia-cancel-reprocess-button');
		if (cancelReprocessButton) {
			cancelReprocessButton.addEventListener('click', handleCancelReprocessClick);
		}

		// Start reprocess polling if active
		var reprocessStatus = document.querySelector('.nuclia-reprocess-status .spinner');
		if (reprocessStatus) {
			startReprocessStatusPolling();
		}

		// Copy buttons (delegate for dynamically added buttons)
		document.addEventListener('click', function(e) {
			if (e.target && e.target.classList.contains('pl-nuclia-copy-btn')) {
				handleCopyButtonClick(e);
			}
		});
	});

	/**
	 * Handle schedule indexing button click
	 */
	function handleScheduleButtonClick(e) {
		e.preventDefault();
		var clickedButton = e.currentTarget;
		var postType = clickedButton.dataset.postType;
		
		if (!postType) {
			console.error('Clicked button has no "data-post-type" set.');
			return;
		}

		clickedButton.disabled = true;
		clickedButton.textContent = 'Scheduling...';

		var formData = new FormData();
		formData.append('action', 'nuclia_schedule_indexing');
		formData.append('post_type', postType);
		formData.append('nonce', getNonce());

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				clickedButton.textContent = 'Scheduled!';
				updateUIFromStatus(response.data.status, response.data.per_type_pending || {});
				startStatusPolling();
				
				// Refresh after a short delay to show updated counts
				setTimeout(function() {
					location.reload();
				}, 2000);
			} else {
				clickedButton.textContent = 'Error';
				clickedButton.disabled = false;
				console.error('Schedule failed:', response);
			}
		})
		.catch(function(error) {
			clickedButton.textContent = 'Error';
			clickedButton.disabled = false;
			console.error('Schedule error:', error);
		});
	}

	/**
	 * Handle cancel button click (per post type)
	 */
	function handleCancelButtonClick(e) {
		e.preventDefault();
		var clickedButton = e.currentTarget;
		var postType = clickedButton.dataset.postType;
		
		if (!postType) {
			console.error('Clicked button has no "data-post-type" set.');
			return;
		}

		if (!confirm('Are you sure you want to cancel pending indexing for this post type?')) {
			return;
		}

		clickedButton.disabled = true;
		clickedButton.textContent = 'Cancelling...';

		var formData = new FormData();
		formData.append('action', 'nuclia_cancel_indexing');
		formData.append('post_type', postType);
		formData.append('nonce', getNonce());

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				// Refresh page to show updated state
				location.reload();
			} else {
				clickedButton.textContent = 'Error';
				clickedButton.disabled = false;
				console.error('Cancel failed:', response);
			}
		})
		.catch(function(error) {
			clickedButton.textContent = 'Error';
			clickedButton.disabled = false;
			console.error('Cancel error:', error);
		});
	}

	/**
	 * Handle cancel all button click
	 */
	function handleCancelAllButtonClick(e) {
		e.preventDefault();
		var clickedButton = e.currentTarget;

		if (!confirm('Are you sure you want to cancel ALL pending indexing jobs?')) {
			return;
		}

		clickedButton.disabled = true;
		clickedButton.textContent = 'Cancelling all...';

		var formData = new FormData();
		formData.append('action', 'nuclia_cancel_indexing');
		formData.append('nonce', getNonce());

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				stopStatusPolling();
				// Refresh page to show updated state
				location.reload();
			} else {
				clickedButton.textContent = 'Error';
				clickedButton.disabled = false;
				console.error('Cancel all failed:', response);
			}
		})
		.catch(function(error) {
			clickedButton.textContent = 'Error';
			clickedButton.disabled = false;
			console.error('Cancel all error:', error);
		});
	}

	/**
	 * Start polling for status updates
	 */
	function startStatusPolling() {
		if (isPolling) {
			return;
		}
		isPolling = true;
		pollStatus();
		statusPollInterval = setInterval(pollStatus, 5000); // Poll every 5 seconds
	}

	/**
	 * Stop polling for status updates
	 */
	function stopStatusPolling() {
		isPolling = false;
		if (statusPollInterval) {
			clearInterval(statusPollInterval);
			statusPollInterval = null;
		}
	}

	/**
	 * Poll for current indexing status
	 */
	function pollStatus() {
		var formData = new FormData();
		formData.append('action', 'nuclia_get_indexing_status');
		formData.append('nonce', getNonce());

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				updateUIFromStatus(response.data.status, response.data.per_type_pending);
				
				// Stop polling if no more pending or running jobs
				if (!response.data.status.is_active) {
					stopStatusPolling();
				}
			}
		})
		.catch(function(error) {
			console.error('Status poll error:', error);
		});
	}

	/**
	 * Update UI elements based on status
	 */
	function updateUIFromStatus(status, perTypePending) {
		// Update overall status
		var pendingEl = document.getElementById('nuclia-status-pending');
		var runningEl = document.getElementById('nuclia-status-running');
		var failedEl = document.getElementById('nuclia-status-failed');

		if (pendingEl) {
			pendingEl.textContent = status.pending + ' pending';
		}
		if (runningEl) {
			runningEl.textContent = status.running + ' running';
		}
		if (failedEl) {
			failedEl.textContent = status.failed + ' failed';
		}

		// Update per-post-type pending counts
		if (perTypePending) {
			Object.keys(perTypePending).forEach(function(postType) {
				var count = perTypePending[postType];
				var statusEl = document.querySelector('.nuclia-pending-status[data-post-type="' + postType + '"]');
				
				if (statusEl) {
					if (count > 0) {
						statusEl.innerHTML = '<span class="spinner is-active pl-nuclia-inline-spinner"></span>' +
							'<span class="nuclia-pending-count">' + count + ' pending</span>';
					} else {
						statusEl.innerHTML = '';
					}
				}

				// Enable/disable schedule button
				var scheduleBtn = document.querySelector('.nuclia-schedule-button[data-post-type="' + postType + '"]');
				if (scheduleBtn) {
					scheduleBtn.disabled = count > 0;
					if (count === 0) {
						scheduleBtn.textContent = 'Schedule indexing';
					}
				}
			});
		}
	}

	/**
	 * Handle labelset select change
	 */
	function handleLabelsetChange(e) {
		var select = e.target;
		var taxonomy = select.dataset.taxonomy || '';
		var labelset = select.value || '';

		if (!taxonomy) {
			return;
		}

		updateLabelSelects(taxonomy, labelset);
	}

	/**
	 * Handle fallback labelset select change
	 */
	function handleFallbackLabelsetChange(e) {
		var select = e.target;
		var taxonomy = select.dataset.taxonomy || '';
		var labelset = select.value || '';
		if (!taxonomy) {
			return;
		}
		updateFallbackLabelSelects(taxonomy, labelset);
	}

	/**
	 * Handle add mapping button click
	 */
	function handleAddMappingClick() {
		var taxonomySelect = document.getElementById('nuclia_add_taxonomy_select');
		var container = document.getElementById('nuclia-mapping-container');
		if (!taxonomySelect || !container) {
			return;
		}

		var taxonomyKey = taxonomySelect.value;
		if (!taxonomyKey) {
			return;
		}

		if (container.querySelector('.nuclia-mapping-block[data-taxonomy="' + taxonomyKey + '"]')) {
			return;
		}

		var block = buildMappingBlock(taxonomyKey);
		if (!block) {
			return;
		}

		container.appendChild(block);

		var selectedOption = taxonomySelect.querySelector('option[value="' + taxonomyKey + '"]');
		if (selectedOption) {
			selectedOption.remove();
			taxonomySelect.value = '';
		}
	}

	/**
	 * Handle remove mapping button click
	 */
	function handleRemoveMappingClick(e) {
		var block = e.target.closest('.nuclia-mapping-block');
		if (!block) {
			return;
		}

		var taxonomyKey = block.dataset.taxonomy || '';
		block.remove();

		if (!taxonomyKey) {
			return;
		}

		var taxonomySelect = document.getElementById('nuclia_add_taxonomy_select');
		var data = getMappingData();
		if (taxonomySelect && data.taxonomies && data.taxonomies[taxonomyKey]) {
			var option = document.createElement('option');
			option.value = taxonomyKey;
			option.textContent = data.taxonomies[taxonomyKey].label || taxonomyKey;
			taxonomySelect.appendChild(option);
		}
	}

	/**
	 * Build a new mapping block for a taxonomy
	 */
	function buildMappingBlock(taxonomyKey) {
		var data = getMappingData();
		if (!data.taxonomies || !data.taxonomies[taxonomyKey]) {
			return null;
		}

		var taxonomy = data.taxonomies[taxonomyKey];
		var labelsets = data.labelsets || [];

		var block = document.createElement('div');
		block.className = 'nuclia-mapping-block pl-nuclia-section-card';
		block.dataset.taxonomy = taxonomyKey;

		var header = document.createElement('div');
		header.className = 'pl-nuclia-flex-between';

		var headerText = document.createElement('div');
		var title = document.createElement('h4');
		title.className = 'pl-nuclia-fallback-title';
		title.textContent = taxonomy.label || taxonomyKey;
		var slug = document.createElement('p');
		slug.className = 'pl-nuclia-muted';
		slug.textContent = taxonomyKey;
		headerText.appendChild(title);
		headerText.appendChild(slug);

		var removeButton = document.createElement('button');
		removeButton.type = 'button';
		removeButton.className = 'button link-delete nuclia-remove-mapping';
		removeButton.textContent = 'Remove';

		header.appendChild(headerText);
		header.appendChild(removeButton);
		block.appendChild(header);

		var labelsetLabel = document.createElement('label');
		labelsetLabel.setAttribute('for', 'nuclia_labelset_' + taxonomyKey);
		labelsetLabel.textContent = 'Labelset:';
		block.appendChild(labelsetLabel);
		block.appendChild(document.createTextNode(' '));

		var labelsetSelect = document.createElement('select');
		labelsetSelect.className = 'regular-text nuclia-labelset-select';
		labelsetSelect.dataset.taxonomy = taxonomyKey;
		labelsetSelect.id = 'nuclia_labelset_' + taxonomyKey;
		labelsetSelect.name = 'nuclia_taxonomy_label_map[' + taxonomyKey + '][labelset]';

		var defaultOption = document.createElement('option');
		defaultOption.value = '';
		defaultOption.textContent = 'Select a labelset';
		labelsetSelect.appendChild(defaultOption);

		labelsets.forEach(function(labelset) {
			var option = document.createElement('option');
			option.value = labelset;
			option.textContent = labelset;
			labelsetSelect.appendChild(option);
		});

		block.appendChild(labelsetSelect);

		if (!labelsets.length) {
			var labelsetNotice = document.createElement('p');
			labelsetNotice.className = 'pl-nuclia-muted';
			labelsetNotice.textContent = 'No labelsets available. Check your Nuclia credentials.';
			block.appendChild(labelsetNotice);
		}

		var terms = taxonomy.terms || [];
		if (!terms.length) {
			var noTermsNotice = document.createElement('p');
			noTermsNotice.className = 'pl-nuclia-muted';
			noTermsNotice.textContent = 'No terms available for this taxonomy.';
			block.appendChild(noTermsNotice);
		} else {
			var table = document.createElement('table');
			table.className = 'widefat striped pl-nuclia-label-table';

			var thead = document.createElement('thead');
			var headRow = document.createElement('tr');
			var termTh = document.createElement('th');
			termTh.textContent = 'Term';
			var labelTh = document.createElement('th');
			labelTh.textContent = 'Nuclia labels';
			headRow.appendChild(termTh);
			headRow.appendChild(labelTh);
			thead.appendChild(headRow);
			table.appendChild(thead);

			var tbody = document.createElement('tbody');
			terms.forEach(function(term) {
				var row = document.createElement('tr');
				var termCell = document.createElement('td');
				termCell.textContent = term.name;
				var labelCell = document.createElement('td');
				var labelContainer = document.createElement('div');
				labelContainer.className = 'nuclia-label-checkboxes';
				labelContainer.dataset.taxonomy = taxonomyKey;
				labelContainer.dataset.termId = term.id;
				labelContainer.innerHTML = '<em>Select a labelset to load labels.</em>';
				labelCell.appendChild(labelContainer);
				row.appendChild(termCell);
				row.appendChild(labelCell);
				tbody.appendChild(row);
			});

			table.appendChild(tbody);
			block.appendChild(table);
		}

		var fallbackSection = document.createElement('div');
		fallbackSection.className = 'nuclia-fallback-section';

		var fallbackTitle = document.createElement('p');
		fallbackTitle.className = 'pl-nuclia-fallback-title';
		fallbackTitle.innerHTML = '<strong>Fallback labels (when no terms assigned)</strong>';
		fallbackSection.appendChild(fallbackTitle);

		var fallbackLabel = document.createElement('label');
		fallbackLabel.setAttribute('for', 'nuclia_fallback_labelset_' + taxonomyKey);
		fallbackLabel.textContent = 'Labelset:';
		fallbackSection.appendChild(fallbackLabel);
		fallbackSection.appendChild(document.createTextNode(' '));

		var fallbackSelect = document.createElement('select');
		fallbackSelect.className = 'regular-text nuclia-fallback-labelset-select';
		fallbackSelect.dataset.taxonomy = taxonomyKey;
		fallbackSelect.id = 'nuclia_fallback_labelset_' + taxonomyKey;
		fallbackSelect.name = 'nuclia_taxonomy_label_map[' + taxonomyKey + '][fallback][labelset]';

		var fallbackDefault = document.createElement('option');
		fallbackDefault.value = '';
		fallbackDefault.textContent = 'Select a labelset';
		fallbackSelect.appendChild(fallbackDefault);

		labelsets.forEach(function(labelset) {
			var option = document.createElement('option');
			option.value = labelset;
			option.textContent = labelset;
			fallbackSelect.appendChild(option);
		});

		fallbackSection.appendChild(fallbackSelect);

		var fallbackLabels = document.createElement('div');
		fallbackLabels.className = 'nuclia-fallback-labels pl-nuclia-muted';
		fallbackLabels.dataset.taxonomy = taxonomyKey;
		fallbackLabels.innerHTML = '<em>Select a labelset to load labels.</em>';
		fallbackSection.appendChild(fallbackLabels);

		block.appendChild(fallbackSection);

		return block;
	}

	/**
	 * Get mapping data from localization
	 */
	function getMappingData() {
		if (typeof nucliaMappingData !== 'undefined') {
			return nucliaMappingData;
		}
		return {taxonomies: {}, labelsets: []};
	}

	/**
	 * Update label selects for a taxonomy
	 */
	function updateLabelSelects(taxonomy, labelset) {
		var labelContainers = document.querySelectorAll('.nuclia-label-checkboxes[data-taxonomy="' + taxonomy + '"]');

		labelContainers.forEach(function(container) {
			container.innerHTML = '<em>Loading labels...</em>';
		});

		if (!labelset) {
			labelContainers.forEach(function(container) {
				container.innerHTML = '<em>Select a labelset to load labels.</em>';
			});
			return;
		}

		var formData = new FormData();
		formData.append('action', 'nuclia_get_labelset_labels');
		formData.append('labelset', labelset);
		formData.append('nonce', getLabelsNonce());

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			var labels = (response && response.success && response.data && response.data.labels) ? response.data.labels : [];
			labelContainers.forEach(function(container) {
				var current = [];
				var currentInputs = container.querySelectorAll('input[type="checkbox"]:checked');
				currentInputs.forEach(function(input) {
					current.push(input.value);
				});

				container.innerHTML = '';
				if (!labels.length) {
					container.innerHTML = '<em>No labels available.</em>';
					return;
				}

				labels.forEach(function(label) {
					var labelWrap = document.createElement('label');
					labelWrap.className = 'pl-nuclia-checkbox-row';

					var checkbox = document.createElement('input');
					checkbox.type = 'checkbox';
					checkbox.className = 'nuclia-label-checkbox';
					checkbox.value = label;
					checkbox.name = 'nuclia_taxonomy_label_map[' + taxonomy + '][terms][' + container.dataset.termId + '][]';
					if (current.indexOf(label) !== -1) {
						checkbox.checked = true;
					}

					labelWrap.appendChild(checkbox);
					labelWrap.appendChild(document.createTextNode(' ' + label));
					container.appendChild(labelWrap);
				});
			});
		})
		.catch(function(error) {
			console.error('Labelset fetch error:', error);
			labelContainers.forEach(function(container) {
				container.innerHTML = '<em>Select a labelset to load labels.</em>';
			});
		});
	}

	/**
	 * Update fallback labels list for taxonomy
	 */
	function updateFallbackLabelSelects(taxonomy, labelset) {
		var containers = document.querySelectorAll('.nuclia-fallback-labels[data-taxonomy="' + taxonomy + '"]');
		if (!containers.length) {
			return;
		}

		containers.forEach(function(container) {
			container.innerHTML = '<em>Loading labels...</em>';
		});

		if (!labelset) {
			containers.forEach(function(container) {
				container.innerHTML = '<em>Select a labelset to load labels.</em>';
			});
			return;
		}

		var formData = new FormData();
		formData.append('action', 'nuclia_get_labelset_labels');
		formData.append('labelset', labelset);
		formData.append('nonce', getLabelsNonce());

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			var labels = (response && response.success && response.data && response.data.labels) ? response.data.labels : [];
			containers.forEach(function(container) {
				var current = [];
				var currentInputs = container.querySelectorAll('input[type="checkbox"]:checked');
				currentInputs.forEach(function(input) {
					current.push(input.value);
				});

				container.innerHTML = '';
				if (!labels.length) {
					container.innerHTML = '<em>No labels available.</em>';
					return;
				}

				labels.forEach(function(label) {
					var labelWrap = document.createElement('label');
					labelWrap.className = 'pl-nuclia-checkbox-row';

					var checkbox = document.createElement('input');
					checkbox.type = 'checkbox';
					checkbox.className = 'nuclia-fallback-label-checkbox';
					checkbox.value = label;
					checkbox.name = 'nuclia_taxonomy_label_map[' + taxonomy + '][fallback][labels][]';
					if (current.indexOf(label) !== -1) {
						checkbox.checked = true;
					}

					labelWrap.appendChild(checkbox);
					labelWrap.appendChild(document.createTextNode(' ' + label));
					container.appendChild(labelWrap);
				});
			});
		})
		.catch(function(error) {
			console.error('Fallback labelset fetch error:', error);
			containers.forEach(function(container) {
				container.innerHTML = '<em>Select a labelset to load labels.</em>';
			});
		});
	}

	/**
	 * Handle clear synced files button click
	 */
	function handleClearSyncedClick(e) {
		e.preventDefault();
		var clickedButton = e.currentTarget;

		if (!confirm('This will clear all synced file mappings. All posts will need to be re-synced. Continue?')) {
			return;
		}

		var spinner = clickedButton.querySelector('.spinner');
		var buttonText = clickedButton.querySelector('.nuclia-clear-synced-text');

		clickedButton.disabled = true;
		if (spinner) {
			spinner.classList.add('is-active');
		}
		if (buttonText) {
			buttonText.textContent = 'Clearing...';
		}

		var nonce = clickedButton.dataset.nonce || '';

		var formData = new FormData();
		formData.append('action', 'nuclia_clear_synced_files');
		formData.append('nonce', nonce);

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				if (buttonText) {
					buttonText.textContent = 'Cleared!';
				}
				// Refresh page to show updated counts
				setTimeout(function() {
					location.reload();
				}, 1500);
			} else {
				if (buttonText) {
					buttonText.textContent = 'Error';
				}
				clickedButton.disabled = false;
				console.error('Clear synced files failed:', response);
				if (response.data && response.data.message) {
					alert('Error: ' + response.data.message);
				}
			}
		})
		.catch(function(error) {
			if (buttonText) {
				buttonText.textContent = 'Error';
			}
			clickedButton.disabled = false;
			console.error('Clear synced files error:', error);
			alert('An error occurred while clearing synced files.');
		})
		.finally(function() {
			if (spinner) {
				spinner.classList.remove('is-active');
			}
		});
	}

	/**
	 * Get the nonce value
	 */
	function getNonce() {
		return (typeof nucliaReindex !== 'undefined' ? nucliaReindex.nonce : '');
	}

	/**
	 * Get nonce for labelset fetch
	 */
	function getLabelsNonce() {
		return (typeof nucliaReindex !== 'undefined' ? nucliaReindex.labelsNonce : '');
	}

	/**
	 * Handle copy button click for code blocks
	 */
	function handleCopyButtonClick(e) {
		var button = e.currentTarget;
		var textToCopy = '';

		// Check for data-copy-text attribute (direct text)
		if (button.dataset.copyText) {
			textToCopy = button.dataset.copyText;
		}
		// Check for data-copy-target attribute (copy from element)
		else if (button.dataset.copyTarget) {
			var targetEl = document.getElementById(button.dataset.copyTarget);
			if (targetEl) {
				textToCopy = targetEl.textContent.trim();
			}
		}

		if (!textToCopy) {
			return;
		}

		var originalText = button.textContent;
		navigator.clipboard.writeText(textToCopy).then(function() {
			button.textContent = __('copied', 'Copied!');
			setTimeout(function() {
				button.textContent = originalText;
			}, 2000);
		}).catch(function() {
			button.textContent = __('copyFailed', 'Copy failed');
			setTimeout(function() {
				button.textContent = originalText;
			}, 2000);
		});
	}

	/**
	 * Handle reprocess labels button click
	 */
	function handleReprocessLabelsClick(e) {
		e.preventDefault();
		var clickedButton = e.currentTarget;

		var syncedCount = document.getElementById('nuclia-synced-count');
		var count = syncedCount ? parseInt(syncedCount.textContent, 10) : 0;

		var confirmMsg = __('confirmReprocess', 'This will update labels for %d synced resource(s) with the current taxonomy mapping. Continue?');
		if (!confirm(confirmMsg.replace('%d', count))) {
			return;
		}

		clickedButton.disabled = true;
		clickedButton.textContent = 'Scheduling...';

		var formData = new FormData();
		formData.append('action', 'nuclia_reprocess_labels');
		formData.append('nonce', getLabelsNonce());

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				clickedButton.textContent = 'Scheduled!';
				if (response.data && response.data.message) {
					alert(response.data.message);
				}
				startReprocessStatusPolling();
				// Refresh after a short delay to show updated status
				setTimeout(function() {
					location.reload();
				}, 2000);
			} else {
				clickedButton.textContent = 'Error';
				clickedButton.disabled = false;
				console.error('Reprocess failed:', response);
				if (response.data && response.data.message) {
					alert('Error: ' + response.data.message);
				}
			}
		})
		.catch(function(error) {
			clickedButton.textContent = 'Error';
			clickedButton.disabled = false;
			console.error('Reprocess error:', error);
		});
	}

	/**
	 * Handle cancel reprocess button click
	 */
	function handleCancelReprocessClick(e) {
		e.preventDefault();
		var clickedButton = e.currentTarget;

		if (!confirm('Are you sure you want to cancel the label reprocessing?')) {
			return;
		}

		clickedButton.disabled = true;
		clickedButton.textContent = 'Cancelling...';

		var formData = new FormData();
		formData.append('action', 'nuclia_cancel_reprocess_labels');
		formData.append('nonce', getLabelsNonce());

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			if (response.success) {
				stopReprocessStatusPolling();
				// Refresh page to show updated state
				location.reload();
			} else {
				clickedButton.textContent = 'Error';
				clickedButton.disabled = false;
				console.error('Cancel reprocess failed:', response);
			}
		})
		.catch(function(error) {
			clickedButton.textContent = 'Error';
			clickedButton.disabled = false;
			console.error('Cancel reprocess error:', error);
		});
	}

	/**
	 * Start polling for reprocess status updates
	 */
	function startReprocessStatusPolling() {
		if (isReprocessPolling) {
			return;
		}
		isReprocessPolling = true;
		pollReprocessStatus();
		reprocessPollInterval = setInterval(pollReprocessStatus, 5000); // Poll every 5 seconds
	}

	/**
	 * Stop polling for reprocess status updates
	 */
	function stopReprocessStatusPolling() {
		isReprocessPolling = false;
		if (reprocessPollInterval) {
			clearInterval(reprocessPollInterval);
			reprocessPollInterval = null;
		}
	}

	/**
	 * Poll for current reprocess status
	 */
	function pollReprocessStatus() {
		var formData = new FormData();
		formData.append('action', 'nuclia_get_reprocess_status');
		formData.append('nonce', getLabelsNonce());

		fetch(ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
		.then(function(response) {
			if (!response.ok) {
				throw new Error('Network response was not ok');
			}
			return response.json();
		})
		.then(function(response) {
			if (response.success && response.data && response.data.reprocessStatus) {
				updateReprocessUIFromStatus(response.data.reprocessStatus);

				// Stop polling if no more pending or running jobs
				if (!response.data.reprocessStatus.is_active) {
					stopReprocessStatusPolling();
					// Refresh the page to show the reprocess button again
					setTimeout(function() {
						location.reload();
					}, 1500);
				}
			}
		})
		.catch(function(error) {
			console.error('Reprocess status poll error:', error);
		});
	}

	/**
	 * Update reprocess UI elements based on status
	 */
	function updateReprocessUIFromStatus(status) {
		var pendingEl = document.getElementById('nuclia-reprocess-pending');
		var runningEl = document.getElementById('nuclia-reprocess-running');
		var failedEl = document.getElementById('nuclia-reprocess-failed');

		if (pendingEl) {
			pendingEl.textContent = status.pending + ' pending';
		}
		if (runningEl) {
			runningEl.textContent = status.running + ' running';
		}
		if (failedEl && status.failed > 0) {
			failedEl.textContent = status.failed + ' failed';
			failedEl.style.display = 'inline';
		}
	}

})();
