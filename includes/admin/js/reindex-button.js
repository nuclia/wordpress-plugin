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
		} else {
			var table = document.createElement('table');
			table.className = 'widefat striped';
			table.style.marginTop = '10px';

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
		fallbackSection.style.marginTop = '12px';
		fallbackSection.style.paddingTop = '10px';
		fallbackSection.style.borderTop = '1px dashed #dcdcde';

		var fallbackTitle = document.createElement('p');
		fallbackTitle.style.margin = '0 0 6px 0';
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
		fallbackLabels.className = 'nuclia-fallback-labels';
		fallbackLabels.dataset.taxonomy = taxonomyKey;
		fallbackLabels.style.marginTop = '8px';
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
					labelWrap.style.display = 'block';
					labelWrap.style.margin = '2px 0';

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
					labelWrap.style.display = 'block';
					labelWrap.style.margin = '2px 0';

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
