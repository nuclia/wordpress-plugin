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
	 * Get the nonce value
	 */
	function getNonce() {
		return (typeof nucliaReindex !== 'undefined' ? nucliaReindex.nonce : '');
	}

})();
