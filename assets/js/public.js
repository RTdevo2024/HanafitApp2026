/* FitnessPro — Public JS (Vanilla OOP) */
/* global fitnesspro_public */
(function () {
	'use strict';

	class FitnessProPublic {
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
			// Frontend event bindings will be registered here as features are added.
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

		/**
		 * Toggles a loading spinner inside a container element.
		 * @param {HTMLElement} el
		 * @param {boolean} show
		 */
		toggleSpinner(el, show) {
			let spinner = el.querySelector('.fitnesspro-spinner');
			if (!spinner) {
				spinner = document.createElement('div');
				spinner.className = 'fitnesspro-spinner';
				el.appendChild(spinner);
			}
			spinner.style.display = show ? 'block' : 'none';
		}

		/**
		 * Renders an inline notice inside a container element.
		 * @param {HTMLElement} el
		 * @param {string} message
		 * @param {'error'|'success'|'info'} type
		 */
		showNotice(el, message, type = 'info') {
			const notice = document.createElement('div');
			notice.className = `fitnesspro-notice fitnesspro-notice--${type}`;
			notice.textContent = message;
			el.prepend(notice);
			setTimeout(() => notice.remove(), 5000);
		}
	}

	if (typeof fitnesspro_public !== 'undefined') {
		window.FitnessProPublicApp = new FitnessProPublic(fitnesspro_public);
	}
})();
