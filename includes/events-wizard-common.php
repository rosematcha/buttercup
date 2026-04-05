<?php
/**
 * Shared styles and scripts for the event wizard screens.
 *
 * @package Buttercup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output shared CSS styles for the event wizard (add-new and edit).
 */
function buttercup_wizard_shared_styles() {
	?>
	<style>
		.buttercup-wizard {
			max-width: 800px;
		}

		.buttercup-wizard__modes {
			display: flex;
			gap: 12px;
			margin: 24px 0;
		}

		.buttercup-wizard__mode-btn {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 14px 24px;
			border: 2px solid #e0e0e0;
			border-radius: 6px;
			background: #fff;
			cursor: pointer;
			font-size: 14px;
			font-weight: 600;
			color: #555;
			transition: all 0.15s ease;
		}

		.buttercup-wizard__mode-btn:hover {
			border-color: #2271b1;
			color: #2271b1;
		}

		.buttercup-wizard__mode-btn.active {
			border-color: #2271b1;
			background: #f0f6fc;
			color: #2271b1;
		}

		.buttercup-wizard__mode-btn .dashicons {
			font-size: 20px;
			width: 20px;
			height: 20px;
		}

		.buttercup-wizard__section {
			background: #fff;
			border: 1px solid #e0e0e0;
			border-radius: 6px;
			padding: 24px;
			margin-bottom: 20px;
		}

		.buttercup-wizard__section h2 {
			margin: 0 0 16px;
			font-size: 16px;
			font-weight: 600;
			padding: 0;
		}

		.buttercup-wizard__field {
			margin-bottom: 20px;
		}

		.buttercup-wizard__field:last-child {
			margin-bottom: 0;
		}

		.buttercup-wizard__field label {
			display: block;
			font-weight: 600;
			margin-bottom: 6px;
			font-size: 13px;
		}

		.buttercup-wizard__field .required {
			color: #cc1818;
		}

		.buttercup-wizard__field input[type="text"],
		.buttercup-wizard__field input[type="url"],
		.buttercup-wizard__field input[type="date"],
		.buttercup-wizard__field input[type="time"],
		.buttercup-wizard__field select {
			width: 100%;
			padding: 8px 12px;
			font-size: 14px;
			border: 1px solid #c0c0c0;
			border-radius: 4px;
		}

		.buttercup-wizard__field input[type="date"],
		.buttercup-wizard__field input[type="time"] {
			width: auto;
			min-width: 200px;
		}

		.buttercup-wizard__field-row {
			display: flex;
			gap: 20px;
		}

		.buttercup-wizard__field--half {
			flex: 1;
		}

		.buttercup-wizard__image-preview {
			margin-bottom: 8px;
		}

		.buttercup-wizard__image-preview img {
			max-width: 300px;
			max-height: 180px;
			border-radius: 4px;
			border: 1px solid #e0e0e0;
			display: block;
		}

		.buttercup-wizard__toggle {
			display: flex;
			align-items: center;
			gap: 8px;
			font-weight: 600;
			font-size: 14px;
			cursor: pointer;
		}

		.buttercup-wizard__toggle input[type="checkbox"] {
			width: 18px;
			height: 18px;
		}

		.buttercup-wizard__homepage-images {
			margin-top: 20px;
			padding-top: 20px;
			border-top: 1px solid #e8e8e8;
		}

		.buttercup-wizard__actions {
			display: flex;
			gap: 12px;
			align-items: center;
			margin-top: 8px;
		}

		.buttercup-wizard__divider {
			display: flex;
			align-items: center;
			gap: 16px;
			margin: 20px 0;
			color: #888;
			font-size: 13px;
		}

		.buttercup-wizard__divider::before,
		.buttercup-wizard__divider::after {
			content: "";
			flex: 1;
			height: 1px;
			background: #e0e0e0;
		}

		.buttercup-wizard__status-bar {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 12px 16px;
			background: #f0f0f1;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			margin-bottom: 20px;
			font-size: 13px;
		}

		.buttercup-wizard__status-label {
			font-weight: 600;
			text-transform: uppercase;
			font-size: 11px;
			letter-spacing: 0.04em;
			color: #50575e;
		}

		.buttercup-wizard__status-value {
			font-weight: 600;
		}

		.buttercup-wizard__status-value--draft {
			color: #996800;
		}

		.buttercup-wizard__status-value--published {
			color: #00a32a;
		}

		.buttercup-wizard__status-value--pending {
			color: #2271b1;
		}

		@media (max-width: 782px) {
			.buttercup-wizard__field-row {
				flex-direction: column;
				gap: 0;
			}

			.buttercup-wizard__modes {
				flex-direction: column;
			}
		}
	</style>
	<?php
}

/**
 * Output shared JS utilities for the event wizard (add-new and edit).
 */
function buttercup_wizard_shared_scripts() {
	?>
	<script>
	(function() {
		// URL label toggle — show label selector when URL is filled in.
		var urlInput = document.getElementById('event_url');
		var urlLabelField = document.getElementById('url-label-field');
		var urlLabelSelect = document.getElementById('event_url_label');
		var urlLabelCustomField = document.getElementById('url-label-custom-field');

		function updateUrlLabelFields() {
			if (!urlInput || !urlLabelField) return;
			var hasUrl = urlInput.value.trim();
			urlLabelField.style.display = hasUrl ? '' : 'none';
			if (!hasUrl) {
				urlLabelCustomField.style.display = 'none';
				return;
			}
			urlLabelCustomField.style.display = (urlLabelSelect.value === 'custom') ? '' : 'none';
		}

		if (urlInput) {
			urlInput.addEventListener('input', updateUrlLabelFields);
			updateUrlLabelFields();
		}
		if (urlLabelSelect) {
			urlLabelSelect.addEventListener('change', updateUrlLabelFields);
		}

		// Page mode toggle.
		var pageModeSelect = document.getElementById('event_page_mode');
		var linkedPageField = document.getElementById('linked-page-field');
		var customSlugField = document.getElementById('custom-slug-field');
		var linkedPageSelect = document.getElementById('event_linked_page');

		function updatePageModeFields() {
			if (!pageModeSelect || !linkedPageField || !customSlugField) return;
			var isStandalone = pageModeSelect.value === 'standalone';
			linkedPageField.style.display = isStandalone ? '' : 'none';
			// Only show slug field if standalone AND no linked page selected.
			var hasLinkedPage = linkedPageSelect && linkedPageSelect.value;
			customSlugField.style.display = (isStandalone && !hasLinkedPage) ? '' : 'none';
		}

		if (pageModeSelect) {
			pageModeSelect.addEventListener('change', updatePageModeFields);
			if (linkedPageSelect) {
				linkedPageSelect.addEventListener('change', updatePageModeFields);
			}
			updatePageModeFields();
		}

		// Homepage toggle.
		var homepageCheckbox = document.getElementById('event_homepage_enabled');
		var homepageSection = document.getElementById('homepage-images-section');

		if (homepageCheckbox && homepageSection) {
			homepageCheckbox.addEventListener('change', function() {
				homepageSection.style.display = this.checked ? '' : 'none';
			});
			homepageSection.style.display = homepageCheckbox.checked ? '' : 'none';
		}

		// Media uploader for image fields.
		var targetMap = {
			'cover-image': 'event_cover_image_id',
			'mobile-image': 'event_homepage_mobile_id',
			'desktop-image': 'event_homepage_desktop_id'
		};

		document.querySelectorAll('.buttercup-wizard__image-select').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var target = btn.getAttribute('data-target');
				var input = document.getElementById(targetMap[target]);
				var preview = document.getElementById(target + '-preview');
				var clearBtn = btn.parentNode.querySelector('.buttercup-wizard__image-clear');

				var frame = wp.media({
					title: '<?php echo esc_js( __( 'Select Image', 'buttercup' ) ); ?>',
					multiple: false,
					library: { type: 'image' }
				});

				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					var url = attachment.sizes && attachment.sizes.medium
						? attachment.sizes.medium.url
						: attachment.url;

					input.value = attachment.id;
					preview.innerHTML = '<img src="' + url + '" alt="" />';
					clearBtn.style.display = '';
				});

				frame.open();
			});
		});

		// Clear image buttons.
		document.querySelectorAll('.buttercup-wizard__image-clear').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var target = btn.getAttribute('data-target');
				var input = document.getElementById(targetMap[target]);
				var preview = document.getElementById(target + '-preview');

				input.value = '';
				preview.innerHTML = '';
				btn.style.display = 'none';
			});
		});

		// Start/end date toggle.
		var startAlldayCheckbox = document.getElementById('event_start_allday');
		var startTimeField = document.getElementById('start-time-field');

		if (startAlldayCheckbox && startTimeField) {
			function updateStartTime() {
				startTimeField.style.display = startAlldayCheckbox.checked ? 'none' : '';
			}
			startAlldayCheckbox.addEventListener('change', updateStartTime);
			updateStartTime();
		}

		var hasEndCheckbox = document.getElementById('event_has_end');
		var endFields = document.getElementById('end-fields');

		if (hasEndCheckbox && endFields) {
			function updateEndFields() {
				endFields.style.display = hasEndCheckbox.checked ? '' : 'none';
			}
			hasEndCheckbox.addEventListener('change', updateEndFields);
			updateEndFields();
		}

		var endAlldayCheckbox = document.getElementById('event_end_allday');
		var endTimeField = document.getElementById('end-time-field');

		if (endAlldayCheckbox && endTimeField) {
			function updateEndTime() {
				endTimeField.style.display = endAlldayCheckbox.checked ? 'none' : '';
			}
			endAlldayCheckbox.addEventListener('change', updateEndTime);
			updateEndTime();
		}
	})();
	</script>
	<?php
}
