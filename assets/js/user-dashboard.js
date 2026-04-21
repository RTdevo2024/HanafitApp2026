/* FitnessPro — User Dashboard (Vanilla OOP) */
/* global fp_dashboard_config */
( function () {
	'use strict';

	class FitnessProUserDashboard {
		constructor( cfg ) {
			this._ajax  = cfg.ajax_url;
			this._nonce = cfg.nonce;
			this._saving = {}; // debounce map keyed by planId+key

			document.addEventListener( 'DOMContentLoaded', () => this._init() );
		}

		_init() {
			this._animateRings();
			this._bindTabs();
			this._bindCheckboxes();
		}

		// ── Animate progress rings from 0 → target on load ──────────────────

		_animateRings() {
			document.querySelectorAll( '.fp-ring-fg' ).forEach( ( circle ) => {
				const target = parseFloat( circle.dataset.target );
				if ( isNaN( target ) ) return;
				// Small delay so CSS transition is active before we change the value
				requestAnimationFrame( () => {
					requestAnimationFrame( () => {
						circle.style.strokeDashoffset = target;
					} );
				} );
			} );
		}

		// ── Plan type tab switching ──────────────────────────────────────────

		_bindTabs() {
			document.querySelectorAll( '.fp-plan-tab' ).forEach( ( tab ) => {
				tab.addEventListener( 'click', () => {
					const targetId = tab.dataset.tab;
					document.querySelectorAll( '.fp-plan-tab' ).forEach( t =>
						t.classList.toggle( 'is-active', t === tab )
					);
					document.querySelectorAll( '.fp-plan-pane' ).forEach( p =>
						p.classList.toggle( 'is-active', p.id === targetId )
					);
				} );
			} );
		}

		// ── Checkbox: update UI + persist to server ──────────────────────────

		_bindCheckboxes() {
			document.querySelectorAll( '.fp-dash-check' ).forEach( ( cb ) => {
				cb.addEventListener( 'change', () => {
					const planId = cb.dataset.planId;
					const key    = cb.name;
					const done   = cb.checked;

					// 1. Toggle item done state
					const item = cb.closest( '.fp-task-item' );
					if ( item ) item.classList.toggle( 'is-done', done );

					// 2. Recompute progress ring
					this._updateRing( planId );

					// 3. Persist (debounced per checkbox)
					this._persist( planId, key, done );
				} );
			} );
		}

		// ── Recompute and animate the progress ring for a plan pane ─────────

		_updateRing( planId ) {
			const pane = document.getElementById( 'fp-plan-' + planId );
			if ( ! pane ) return;

			const all  = pane.querySelectorAll( '.fp-dash-check' );
			const done = pane.querySelectorAll( '.fp-dash-check:checked' );

			if ( ! all.length ) return;

			const pct  = Math.round( ( done.length / all.length ) * 100 );
			const ring = pane.querySelector( '.fp-ring-fg' );

			if ( ring ) {
				const r            = parseFloat( ring.getAttribute( 'r' ) );
				const circumference = 2 * Math.PI * r;
				const offset       = circumference * ( 1 - pct / 100 );

				ring.style.strokeDashoffset = offset;
				pane.querySelector( '.fp-progress-svg' )?.setAttribute( 'aria-valuenow', pct );

				if ( pct === 100 ) {
					ring.classList.add( 'fp-ring--complete' );
					setTimeout( () => this._showAllDone( planId ), 350 );
				} else {
					ring.classList.remove( 'fp-ring--complete' );
					document.getElementById( 'fp-all-done-' + planId )?.setAttribute( 'hidden', '' );
				}
			}

			// Update percentage counter
			const pctEl = document.getElementById( 'fp-pct-' + planId );
			if ( pctEl ) pctEl.textContent = pct;
		}

		_showAllDone( planId ) {
			const el = document.getElementById( 'fp-all-done-' + planId );
			if ( el ) el.removeAttribute( 'hidden' );
		}

		// ── Debounced AJAX save ──────────────────────────────────────────────

		_persist( planId, key, checked ) {
			const mapKey = planId + ':' + key;
			clearTimeout( this._saving[ mapKey ] );
			this._saving[ mapKey ] = setTimeout( () => {
				this._save( planId, key, checked );
			}, 400 );
		}

		async _save( planId, key, checked ) {
			const body = new FormData();
			body.append( 'action',  'fp_save_progress' );
			body.append( 'nonce',   this._nonce );
			body.append( 'plan_id', planId );
			body.append( 'key',     key );
			body.append( 'checked', checked ? '1' : '0' );
			try {
				await fetch( this._ajax, { method: 'POST', body } );
			} catch {
				// Silent fail — state already reflected in UI
			}
		}
	}

	if ( window.fp_dashboard_config ) {
		new FitnessProUserDashboard( window.fp_dashboard_config );
	}
} )();
