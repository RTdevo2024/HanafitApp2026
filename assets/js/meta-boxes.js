/* FitnessPro — Meta Boxes JS (Vanilla OOP)
 * Handles: workout tab switching, exercise repeater, wp.media picker, macro totals.
 * global fitnesspro_mb
 */
(function () {
	'use strict';

	const cfg = window.fitnesspro_mb || { strings: {} };

	// ─── Workout Planner ─────────────────────────────────────────────────────

	class WorkoutPlanner {
		constructor( container ) {
			this.container    = container;
			this._mediaFrame  = null;
			this._mediaTarget = null;
			this._init();
		}

		_init() {
			this._bindTabs();
			this._bindDelegate();
		}

		// Tab switching
		_bindTabs() {
			this.container.querySelectorAll( '.fp-tab-btn' ).forEach( btn => {
				btn.addEventListener( 'click', () => this._switchTab( btn.dataset.tab ) );
			} );
		}

		_switchTab( day ) {
			this.container.querySelectorAll( '.fp-tab-btn' ).forEach( btn => {
				const active = btn.dataset.tab === day;
				btn.classList.toggle( 'fp-tab-btn--active', active );
				btn.setAttribute( 'aria-selected', active );
			} );
			this.container.querySelectorAll( '.fp-tab-panel' ).forEach( panel => {
				panel.classList.toggle( 'fp-tab-panel--active', panel.dataset.day === day );
			} );
		}

		// Delegated click handler for the entire planner
		_bindDelegate() {
			this.container.addEventListener( 'click', e => {
				const addBtn    = e.target.closest( '.fp-add-exercise' );
				const deleteBtn = e.target.closest( '.fp-delete-row' );
				const mediaBtn  = e.target.closest( '.fp-select-media' );
				const removeBtn = e.target.closest( '.fp-remove-media' );

				if ( addBtn )    this._addRow( addBtn.dataset.day );
				if ( deleteBtn ) this._deleteRow( deleteBtn.closest( '.fp-exercise-row' ) );
				if ( mediaBtn )  this._openMedia( mediaBtn.closest( '.fp-exercise-row' ) );
				if ( removeBtn ) this._clearMedia( removeBtn.closest( '.fp-exercise-row' ) );
			} );
		}

		// Add a new exercise row from the hidden template
		_addRow( day ) {
			const tplEl = document.getElementById( 'fp-exercise-row-tpl' );
			if ( ! tplEl ) return;

			const list  = this.container.querySelector( `.fp-exercises-list[data-day="${day}"]` );
			const index = list.querySelectorAll( '.fp-exercise-row' ).length;

			const html = tplEl.innerHTML
				.replace( /\{\{DAY\}\}/g,   day )
				.replace( /\{\{INDEX\}\}/g, index );

			const temp = document.createElement( 'div' );
			temp.innerHTML = html.trim();
			const row = temp.firstElementChild;
			list.appendChild( row );
		}

		// Remove an exercise row and re-sequence remaining indexes
		_deleteRow( row ) {
			const list = row.closest( '.fp-exercises-list' );
			row.remove();
			this._reindex( list );
		}

		_reindex( list ) {
			const day = list.dataset.day;
			list.querySelectorAll( '.fp-exercise-row' ).forEach( ( row, i ) => {
				row.querySelectorAll( '[name]' ).forEach( el => {
					el.name = el.name.replace(
						new RegExp( `\\[${day}\\]\\[exercises\\]\\[\\d+\\]` ),
						`[${day}][exercises][${i}]`
					);
				} );
			} );
		}

		// ── wp.media integration ────────────────────────────────────────────

		_openMedia( row ) {
			this._mediaTarget = row;

			if ( ! this._mediaFrame ) {
				this._mediaFrame = wp.media( {
					title:    cfg.strings.select_media || 'انتخاب رسانه',
					button:   { text: cfg.strings.use_media || 'استفاده از این رسانه' },
					multiple: false,
					library:  { type: [ 'image', 'video' ] },
				} );

				this._mediaFrame.on( 'select', () => {
					const attachment = this._mediaFrame.state()
						.get( 'selection' ).first().toJSON();
					this._setMedia( this._mediaTarget, attachment );
				} );
			}

			this._mediaFrame.open();
		}

		_setMedia( row, attachment ) {
			row.querySelector( '.fp-media-id'  ).value = attachment.id;
			row.querySelector( '.fp-media-url' ).value = attachment.url;

			const preview = row.querySelector( '.fp-media-preview' );

			if ( attachment.type === 'image' ) {
				const thumb = ( attachment.sizes && attachment.sizes.thumbnail )
					? attachment.sizes.thumbnail.url
					: attachment.url;
				preview.innerHTML =
					`<img src="${thumb}" alt="" class="fp-media-thumb">`;
			} else {
				preview.innerHTML =
					`<span class="fp-video-label">${attachment.filename}</span>`;
			}

			const removeBtn = row.querySelector( '.fp-remove-media' );
			if ( removeBtn ) removeBtn.style.display = '';
		}

		_clearMedia( row ) {
			row.querySelector( '.fp-media-id'  ).value = '';
			row.querySelector( '.fp-media-url' ).value = '';
			row.querySelector( '.fp-media-preview' ).innerHTML = '';

			const removeBtn = row.querySelector( '.fp-remove-media' );
			if ( removeBtn ) removeBtn.style.display = 'none';
		}
	}

	// ─── Meal Planner — macro totals calculator ──────────────────────────────

	class MealPlanner {
		constructor( container ) {
			this.container = container;
			this._init();
		}

		_init() {
			this._renderTotalsBar();
			this._bindInputs();
		}

		_renderTotalsBar() {
			const bar = document.createElement( 'div' );
			bar.className  = 'fp-macro-totals';
			bar.id         = 'fp-macro-totals';
			bar.innerHTML  = this._buildTotalsHTML( this._calculate() );
			this.container.insertAdjacentElement( 'beforebegin', bar );
		}

		_bindInputs() {
			const bar = document.getElementById( 'fp-macro-totals' );
			this.container.addEventListener( 'input', e => {
				if ( e.target.matches( 'input[type="number"]' ) ) {
					bar.innerHTML = this._buildTotalsHTML( this._calculate() );
				}
			} );
		}

		_calculate() {
			const totals = { calories: 0, protein: 0, carbs: 0, fat: 0 };
			[ 'calories', 'protein', 'carbs', 'fat' ].forEach( key => {
				this.container.querySelectorAll( `input[name*="[${key}]"]` ).forEach( el => {
					totals[ key ] += parseFloat( el.value ) || 0;
				} );
			} );
			return totals;
		}

		_buildTotalsHTML( t ) {
			const labels = {
				calories : 'کالری کل',
				protein  : 'پروتئین',
				carbs    : 'کربوهیدرات',
				fat      : 'چربی',
			};
			const units = { calories: 'kcal', protein: 'g', carbs: 'g', fat: 'g' };
			return Object.entries( labels ).map( ( [ k, lbl ] ) =>
				`<span class="fp-macro-totals__item"><span>${lbl}:</span>${t[ k ]}${units[ k ]}</span>`
			).join( '' );
		}
	}

	// ─── Bootstrap ───────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', () => {
		const workoutPlanner = document.querySelector( '.fp-workout-planner' );
		if ( workoutPlanner ) new WorkoutPlanner( workoutPlanner );

		const mealPlanner = document.querySelector( '.fp-meal-planner' );
		if ( mealPlanner ) new MealPlanner( mealPlanner );
	} );

} )();
