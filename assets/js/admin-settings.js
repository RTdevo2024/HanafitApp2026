/* FitnessPro — Admin Settings JS (Vanilla OOP)
 * Handles: dynamic field list management (add/remove via AJAX)
 * Depends on: fitnesspro_settings (wp_localize_script)
 * global fitnesspro_settings
 */
( function () {
	'use strict';

	const cfg = window.fitnesspro_settings || { ajax_url: '', nonce: '', strings: {} };

	class DynamicFieldManager {

		constructor( grid ) {
			this.grid = grid;
			this._bindEvents();
		}

		_bindEvents() {
			// Delegated handler for the entire grid
			this.grid.addEventListener( 'click', e => {
				const addBtn    = e.target.closest( '.fp-js-add-item' );
				const removeBtn = e.target.closest( '.fp-js-remove-item' );

				if ( addBtn )    this._handleAdd( addBtn.dataset.field );
				if ( removeBtn ) this._handleRemove( removeBtn.dataset.field, parseInt( removeBtn.dataset.index, 10 ) );
			} );

			// Enter key submits the add-input for the focused card
			this.grid.addEventListener( 'keydown', e => {
				if ( e.key !== 'Enter' ) return;
				const input = e.target.closest( '.fp-add-item-input' );
				if ( input ) {
					e.preventDefault();
					this._handleAdd( input.dataset.field );
				}
			} );
		}

		_handleAdd( fieldKey ) {
			const card  = this._card( fieldKey );
			const input = card ? card.querySelector( '.fp-add-item-input' ) : null;
			if ( ! input ) return;

			const value = input.value.trim();
			if ( ! value ) {
				this._shake( input );
				return;
			}

			const addBtn = card.querySelector( '.fp-js-add-item' );
			this._setLoading( addBtn, true );

			this._ajax( 'fp_add_field_item', { field_key: fieldKey, value } )
				.then( res => {
					if ( res.success ) {
						input.value = '';
						this._renderTags( fieldKey, res.data.items );
					} else {
						this._showError( card, res.data.message );
					}
				} )
				.finally( () => this._setLoading( addBtn, false ) );
		}

		_handleRemove( fieldKey, index ) {
			const card = this._card( fieldKey );
			if ( ! card ) return;

			const tag = card.querySelector( `.fp-tag[data-index="${index}"]` );
			if ( tag ) tag.style.opacity = '0.5';

			this._ajax( 'fp_remove_field_item', { field_key: fieldKey, index } )
				.then( res => {
					if ( res.success ) {
						this._renderTags( fieldKey, res.data.items );
					} else {
						if ( tag ) tag.style.opacity = '';
						this._showError( card, res.data.message );
					}
				} );
		}

		// Re-render the tag list for a given field card
		_renderTags( fieldKey, items ) {
			const card = this._card( fieldKey );
			if ( ! card ) return;

			const list    = card.querySelector( '.fp-tags-list' );
			const counter = card.querySelector( '.fp-item-counter' );

			if ( counter ) {
				counter.textContent = items.length + ' ' + ( cfg.strings.item_suffix || 'مورد' );
			}

			if ( ! items.length ) {
				list.innerHTML = '<span class="fp-empty-notice">'
					+ ( cfg.strings.empty_list || 'هنوز موردی اضافه نشده.' )
					+ '</span>';
				return;
			}

			list.innerHTML = items.map( ( item, i ) =>
				`<span class="fp-tag" data-index="${i}">
					<span class="fp-tag__text">${this._esc( item )}</span>
					<button type="button"
						class="fp-tag__remove fp-js-remove-item"
						data-field="${this._esc( fieldKey )}"
						data-index="${i}"
						aria-label="${cfg.strings.remove || 'حذف'}">&times;</button>
				</span>`
			).join( '' );
		}

		// ── Helpers ─────────────────────────────────────────────────────────

		_card( fieldKey ) {
			return this.grid.querySelector( `.fp-field-group-card[data-field-key="${fieldKey}"]` );
		}

		_setLoading( btn, loading ) {
			if ( ! btn ) return;
			btn.disabled = loading;
			btn.textContent = loading
				? ( cfg.strings.loading || 'در حال بارگذاری...' )
				: ( '+ ' + ( cfg.strings.add || 'افزودن' ) );
		}

		_shake( el ) {
			el.style.transition = 'border-color 0.1s ease';
			el.style.borderColor = '#d63638';
			setTimeout( () => { el.style.borderColor = ''; }, 800 );
		}

		_showError( card, message ) {
			const existing = card.querySelector( '.fp-inline-error' );
			if ( existing ) existing.remove();

			const el = document.createElement( 'p' );
			el.className = 'fp-inline-error';
			el.style.cssText = 'color:#d63638;font-size:12px;margin:4px 0 0;';
			el.textContent = message;
			card.querySelector( '.fp-add-item-row' ).after( el );
			setTimeout( () => el.remove(), 4000 );
		}

		_esc( str ) {
			return String( str )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' );
		}

		_ajax( action, data ) {
			const body = new FormData();
			body.append( 'action', action );
			body.append( 'nonce', cfg.nonce );
			Object.entries( data ).forEach( ( [ k, v ] ) => body.append( k, v ) );

			return fetch( cfg.ajax_url, { method: 'POST', body } )
				.then( r => {
					if ( ! r.ok ) throw new Error( 'network' );
					return r.json();
				} )
				.catch( () => ( { success: false, data: { message: cfg.strings.error || 'خطا' } } ) );
		}
	}

	// Bootstrap
	document.addEventListener( 'DOMContentLoaded', () => {
		const grid = document.getElementById( 'fp-field-groups-grid' );
		if ( grid ) new DynamicFieldManager( grid );
	} );

} )();
