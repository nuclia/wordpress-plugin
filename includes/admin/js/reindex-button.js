(function() {
	'use strict';

	var statusPollInterval = null;
	var isPolling = false;

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
						statusEl.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0 5px;"></span>' +
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
		block.className = 'nuclia-mapping-block';
		block.dataset.taxonomy = taxonomyKey;
		block.style.marginTop = '15px';
		block.style.padding = '10px';
		block.style.background = '#fff';
		block.style.border = '1px solid #dcdcde';

		var header = document.createElement('div');
		header.style.display = 'flex';
		header.style.alignItems = 'center';
		header.style.justifyContent = 'space-between';

		var headerText = document.createElement('div');
		var title = document.createElement('h4');
		title.style.margin = '0 0 8px 0';
		title.textContent = taxonomy.label || taxonomyKey;
		var slug = document.createElement('p');
		slug.style.margin = '0 0 8px 0';
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
			labelsetNotice.style.margin = '8px 0 0 0';
			labelsetNotice.textContent = 'No labelsets available. Check your Nuclia credentials.';
			block.appendChild(labelsetNotice);
		}

		var terms = taxonomy.terms || [];
		if (!terms.length) {
			var noTermsNotice = document.createElement('p');
			noTermsNotice.style.margin = '8px 0 0 0';
			noTermsNotice.textContent = 'No terms available for this taxonomy.';
			block.appendChild(noTermsNotice);
			return block;
		}

		var table = document.createElement('table');
		table.className = 'widefat striped';
		table.style.marginTop = '10px';

		var thead = document.createElement('thead');
		var headRow = document.createElement('tr');
		var termTh = document.createElement('th');
		termTh.textContent = 'Term';
		var labelTh = document.createElement('th');
		labelTh.textContent = 'Nuclia label';
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
			var labelSelect = document.createElement('select');
			labelSelect.className = 'regular-text nuclia-label-select';
			labelSelect.dataset.taxonomy = taxonomyKey;
			labelSelect.name = 'nuclia_taxonomy_label_map[' + taxonomyKey + '][terms][' + term.id + ']';

			var labelDefault = document.createElement('option');
			labelDefault.value = '';
			labelDefault.textContent = 'Select a label';
			labelSelect.appendChild(labelDefault);

			labelCell.appendChild(labelSelect);
			row.appendChild(termCell);
			row.appendChild(labelCell);
			tbody.appendChild(row);
		});

		table.appendChild(tbody);
		block.appendChild(table);

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
		var labelSelects = document.querySelectorAll('.nuclia-label-select[data-taxonomy="' + taxonomy + '"]');

		labelSelects.forEach(function(select) {
			select.disabled = true;
			select.innerHTML = '<option value="">Loading labels...</option>';
		});

		if (!labelset) {
			labelSelects.forEach(function(select) {
				select.disabled = false;
				select.innerHTML = '<option value="">Select a label</option>';
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
			labelSelects.forEach(function(select) {
				var current = select.value || '';
				select.disabled = false;
				select.innerHTML = '<option value="">Select a label</option>';
				labels.forEach(function(label) {
					var option = document.createElement('option');
					option.value = label;
					option.textContent = label;
					if (current === label) {
						option.selected = true;
					}
					select.appendChild(option);
				});
			});
		})
		.catch(function(error) {
			console.error('Labelset fetch error:', error);
			labelSelects.forEach(function(select) {
				select.disabled = false;
				select.innerHTML = '<option value="">Select a label</option>';
			});
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

})();
