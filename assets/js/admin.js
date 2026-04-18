/* FitnessPro — Admin JS (Vanilla OOP) */
/* global fitnesspro_admin */
(function () {
	'use strict';

	class FitnessProAdmin {
		constructor(config) {
			this.ajaxUrl = config.ajax_url;
			this.nonce   = config.nonce;
			this.strings = config.strings;
			this.init();
		}

		init() {
			document.addEventListener('DOMContentLoaded', () => this.bindEvents());
		}

		bindEvents() {
			// Delete-confirmation on any element with data-fitnesspro-confirm
			document.querySelectorAll('[data-fitnesspro-confirm]').forEach((el) => {
				el.addEventListener('click', (e) => {
					if (!window.confirm(this.strings.confirm_delete)) {
						e.preventDefault();
					}
				});
			});
		}

		/**
		 * Generic AJAX helper — returns a Promise resolving to the parsed JSON body.
		 * @param {string} action   WP AJAX action name
		 * @param {Object} data     Key/value payload
		 * @returns {Promise<Object>}
		 */
		ajax(action, data = {}) {
			const body = new FormData();
			body.append('action', action);
			body.append('nonce', this.nonce);
			Object.entries(data).forEach(([k, v]) => body.append(k, v));

			return fetch(this.ajaxUrl, { method: 'POST', body })
				.then((res) => {
					if (!res.ok) throw new Error('network');
					return res.json();
				})
				.catch(() => {
					alert(this.strings.error);
					return null;
				});
		}
	}

	if (typeof fitnesspro_admin !== 'undefined') {
		window.FitnessProAdminApp = new FitnessProAdmin(fitnesspro_admin);
	}
})();
