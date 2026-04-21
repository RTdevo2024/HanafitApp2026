/* FitnessPro — Coach Panel (Vanilla OOP) */
/* global fp_coach */
( function () {
	'use strict';

	// ── Coach fulfillment panel ───────────────────────────────────────────────

	class FitnessProCoachPanel {
		constructor( cfg ) {
			this._ajax      = cfg.ajax_url;
			this._nonce     = cfg.nonce;
			this._s         = cfg.strings;
			this._planId    = null;
			this._planType  = null;
			this._tplId     = null;
			this._tplData   = {};
			this._days      = Object.keys( this._s.days );
			this._meals     = Object.keys( this._s.meals );

			document.addEventListener( 'DOMContentLoaded', () => this._init() );
		}

		_init() {
			this._bindList();
			this._bindModal();
		}

		// ── Client list ─────────────────────────────────────────────────────

		_bindList() {
			const list = document.getElementById( 'fp-coach-list' );
			if ( ! list ) return;

			list.addEventListener( 'click', ( e ) => {
				const profileBtn = e.target.closest( '.fp-btn-profile' );
				const assignBtn  = e.target.closest( '.fp-btn-assign:not([disabled])' );

				if ( profileBtn ) {
					let profile = {};
					try { profile = JSON.parse( profileBtn.dataset.profile || '{}' ); } catch {}
					this._showProfile( profile, profileBtn.dataset.user || '' );
				}

				if ( assignBtn ) {
					this._planId   = parseInt( assignBtn.dataset.planId, 10 );
					this._planType = assignBtn.dataset.planType || 'workout';
					this._openModal( assignBtn.dataset.user || '' );
				}
			} );
		}

		// ── Profile side panel ───────────────────────────────────────────────

		_showProfile( profile, userName ) {
			const panel = document.getElementById( 'fp-coach-detail' );
			const inner = document.getElementById( 'fp-coach-detail-inner' );
			if ( ! panel || ! inner ) return;

			if ( ! profile || ! Object.keys( profile ).length ) {
				inner.innerHTML = '<p class="fp-profile-nodata">پروفایل سلامت برای این کاربر ثبت نشده است.</p>';
				panel.hidden = false;
				return;
			}

			const labelMap = {
				full_name:        'نام کامل',
				age:              'سن',
				gender:           'جنسیت',
				height:           'قد (cm)',
				weight:           'وزن (kg)',
				goal:             'هدف',
				activity:         'سطح فعالیت',
				food_allergies:   'آلرژی غذایی',
				diseases:         'بیماری‌ها',
				eating_disorders: 'اختلالات تغذیه',
			};

			const scalarKeys = [ 'full_name', 'age', 'gender', 'height', 'weight', 'goal', 'activity', 'food_allergies' ];
			const chipKeys   = [ 'diseases', 'eating_disorders' ];

			let html = '<h3 class="fp-profile-title">' + this._esc( userName ) + '</h3>';
			html += '<div class="fp-profile-grid">';
			scalarKeys.forEach( ( key ) => {
				const val = profile[ key ];
				if ( val === undefined || val === '' || val === null ) return;
				html += '<div class="fp-profile-field">'
					+ '<span class="fp-profile-field__key">' + ( labelMap[ key ] || key ) + '</span>'
					+ '<span class="fp-profile-field__val">' + this._esc( String( val ) ) + '</span>'
					+ '</div>';
			} );
			html += '</div>';

			chipKeys.forEach( ( key ) => {
				const arr = profile[ key ];
				if ( ! arr || ( Array.isArray( arr ) && ! arr.length ) ) return;
				const items = Array.isArray( arr ) ? arr : [ arr ];
				html += '<div style="margin-bottom:10px">'
					+ '<span class="fp-profile-field__key" style="display:block;font-size:12px;margin-bottom:4px">'
					+ ( labelMap[ key ] || key ) + '</span>'
					+ '<div class="fp-profile-chips">';
				items.forEach( ( item ) => {
					html += '<span class="fp-profile-chip">' + this._esc( item ) + '</span>';
				} );
				html += '</div></div>';
			} );

			inner.innerHTML = html;
			panel.hidden = false;
		}

		// ── Modal ────────────────────────────────────────────────────────────

		_bindModal() {
			const modal = document.getElementById( 'fp-coach-modal' );
			if ( ! modal ) return;

			const closeModal = () => this._closeModal();
			document.getElementById( 'fp-modal-close' )?.addEventListener( 'click', closeModal );
			document.getElementById( 'fp-modal-overlay' )?.addEventListener( 'click', closeModal );
			document.getElementById( 'fp-select-cancel' )?.addEventListener( 'click', closeModal );

			document.getElementById( 'fp-select-next' )?.addEventListener( 'click', () => this._goToEditStep() );
			document.getElementById( 'fp-edit-back' )?.addEventListener( 'click', () => this._goToSelectStep() );
			document.getElementById( 'fp-btn-publish' )?.addEventListener( 'click', () => this._publish() );

			// Template card selection
			document.getElementById( 'fp-tpl-list' )?.addEventListener( 'click', ( e ) => {
				const card = e.target.closest( '.fp-tpl-card' );
				if ( ! card ) return;
				document.querySelectorAll( '.fp-tpl-card' ).forEach( c => c.classList.remove( 'is-selected' ) );
				card.classList.add( 'is-selected' );
				this._tplId = parseInt( card.dataset.tplId, 10 );
				try { this._tplData = JSON.parse( card.dataset.tplData || '{}' ); } catch { this._tplData = {}; }
				document.getElementById( 'fp-select-next' ).disabled = false;
			} );

			// Day tab clicks, exercise add/remove
			modal.addEventListener( 'click', ( e ) => {
				const tab = e.target.closest( '.fp-day-tab' );
				if ( tab ) this._switchDay( tab.dataset.day );

				const remBtn = e.target.closest( '.fp-ex-row__remove' );
				if ( remBtn ) remBtn.closest( '.fp-ex-row' )?.remove();

				const addBtn = e.target.closest( '.fp-add-ex-btn' );
				if ( addBtn ) this._addExRow( addBtn.dataset.day );
			} );
		}

		async _openModal( userName ) {
			const modal = document.getElementById( 'fp-coach-modal' );
			if ( ! modal ) return;

			document.getElementById( 'fp-modal-title' ).textContent =
				'تخصیص برنامه: ' + ( userName || '' );

			this._goToSelectStep();
			modal.hidden = false;

			// Load templates for this plan type
			const list = document.getElementById( 'fp-tpl-list' );
			list.innerHTML = '<span class="fp-spinner"></span>';
			document.getElementById( 'fp-select-next' ).disabled = true;

			const resp = await this._fetch( 'fp_get_coach_templates', { plan_type: this._planType } );
			if ( ! resp || ! resp.success ) {
				list.innerHTML = '<p class="fp-tpl-empty">' + this._esc( this._s.error ) + '</p>';
				return;
			}

			const templates = resp.data || [];
			if ( ! templates.length ) {
				list.innerHTML = '<p class="fp-tpl-empty">' + this._esc( this._s.no_templates ) + '</p>';
				return;
			}

			list.innerHTML = templates.map( ( tpl ) => {
				const count = this._countItems( tpl.data, this._planType );
				return '<div class="fp-tpl-card" data-tpl-id="' + tpl.id + '" data-tpl-data=\''
					+ this._escAttr( JSON.stringify( tpl.data ) ) + '\'>'
					+ '<div class="fp-tpl-card__title">' + this._esc( tpl.title ) + '</div>'
					+ '<div class="fp-tpl-card__meta">' + count + '</div>'
					+ '</div>';
			} ).join( '' );
		}

		_countItems( data, type ) {
			if ( 'workout' === type ) {
				let n = 0;
				Object.values( data || {} ).forEach( d => { n += ( d.exercises || [] ).length; } );
				return n + ' تمرین';
			}
			return Object.keys( data || {} ).length + ' وعده';
		}

		_closeModal() {
			const modal = document.getElementById( 'fp-coach-modal' );
			if ( modal ) modal.hidden = true;
			this._planId  = null;
			this._tplId   = null;
			this._tplData = {};
		}

		_goToSelectStep() {
			document.getElementById( 'fp-step-select' ).hidden = false;
			document.getElementById( 'fp-step-edit' ).hidden   = true;
			document.querySelectorAll( '.fp-tpl-card' ).forEach( c => c.classList.remove( 'is-selected' ) );
			const nextBtn = document.getElementById( 'fp-select-next' );
			if ( nextBtn ) nextBtn.disabled = true;
			this._tplId   = null;
			this._tplData = {};
		}

		_goToEditStep() {
			if ( ! this._tplId ) {
				alert( this._s.select_tpl );
				return;
			}
			document.getElementById( 'fp-step-select' ).hidden = true;
			document.getElementById( 'fp-step-edit' ).hidden   = false;
			this._renderEditor();
		}

		// ── Plan editor ──────────────────────────────────────────────────────

		_renderEditor() {
			const el = document.getElementById( 'fp-plan-editor' );
			if ( ! el ) return;
			if ( 'workout' === this._planType ) {
				el.innerHTML = this._buildWorkoutEditor( this._tplData );
				this._switchDay( this._days[ 0 ] );
			} else {
				el.innerHTML = this._buildMealEditor( this._tplData );
			}
		}

		_buildWorkoutEditor( data ) {
			const tabsHtml = this._days.map( ( day ) =>
				'<button type="button" class="fp-day-tab" data-day="' + day + '">'
				+ this._esc( this._s.days[ day ] || day ) + '</button>'
			).join( '' );

			const panesHtml = this._days.map( ( day ) => {
				const exercises = ( data[ day ] && Array.isArray( data[ day ].exercises ) )
					? data[ day ].exercises : [];
				const rows = exercises.map( ex => this._exRowHtml( ex ) ).join( '' );
				return '<div class="fp-day-pane" data-pane="' + day + '">'
					+ '<div class="fp-exercise-rows" id="fp-exrows-' + day + '">' + rows + '</div>'
					+ '<button type="button" class="button fp-add-ex-btn" data-day="' + day + '">+ '
					+ this._esc( this._s.add_exercise ) + '</button>'
					+ '</div>';
			} ).join( '' );

			return '<div class="fp-day-tabs">' + tabsHtml + '</div>' + panesHtml;
		}

		_exRowHtml( ex ) {
			ex = ex || {};
			const f = this._s.fields;
			return '<div class="fp-ex-row">'
				+ '<input type="text" placeholder="' + this._esc( f.exercise ) + '" value="' + this._esc( ex.name || '' ) + '" data-field="name">'
				+ '<input type="number" placeholder="' + this._esc( f.sets ) + '" value="' + ( ex.sets || '' ) + '" min="0" data-field="sets">'
				+ '<input type="number" placeholder="' + this._esc( f.reps ) + '" value="' + ( ex.reps || '' ) + '" min="0" data-field="reps">'
				+ '<input type="text" placeholder="' + this._esc( f.note ) + '" value="' + this._esc( ex.note || '' ) + '" data-field="note">'
				+ '<button type="button" class="fp-ex-row__remove" aria-label="' + this._esc( this._s.remove ) + '">&#x2715;</button>'
				+ '</div>';
		}

		_addExRow( day ) {
			const container = document.getElementById( 'fp-exrows-' + day );
			if ( ! container ) return;
			const div = document.createElement( 'div' );
			div.innerHTML = this._exRowHtml( {} );
			container.appendChild( div.firstElementChild );
			container.lastElementChild.querySelector( 'input' )?.focus();
		}

		_switchDay( day ) {
			document.querySelectorAll( '.fp-day-tab' ).forEach( t =>
				t.classList.toggle( 'is-active', t.dataset.day === day )
			);
			document.querySelectorAll( '.fp-day-pane' ).forEach( p =>
				p.classList.toggle( 'is-active', p.dataset.pane === day )
			);
		}

		_buildMealEditor( data ) {
			return '<div class="fp-meal-cards">'
				+ this._meals.map( ( meal ) => {
					const s = ( data[ meal ] && typeof data[ meal ] === 'object' ) ? data[ meal ] : {};
					const f = this._s.fields;
					return '<div class="fp-meal-card" data-meal="' + meal + '">'
						+ '<div class="fp-meal-card__head">' + this._esc( this._s.meals[ meal ] || meal ) + '</div>'
						+ '<div class="fp-meal-card__body">'
						+ '<textarea data-field="items" placeholder="' + this._esc( f.items ) + '">'
						+ this._esc( s.items || '' ) + '</textarea>'
						+ '<div class="fp-meal-macros">'
						+ [ 'calories', 'protein', 'carbs', 'fat' ].map( ( m ) =>
							'<div class="fp-macro">'
							+ '<label>' + this._esc( f[ m ] || m ) + '</label>'
							+ '<input type="number" min="0" data-field="' + m + '" value="' + ( s[ m ] || 0 ) + '">'
							+ '</div>'
						).join( '' )
						+ '</div>'
						+ '<textarea data-field="note" placeholder="' + this._esc( f.note ) + '">'
						+ this._esc( s.note || '' ) + '</textarea>'
						+ '</div></div>';
				} ).join( '' )
				+ '</div>';
		}

		// ── Collect & publish ────────────────────────────────────────────────

		_collectPlanData() {
			if ( 'workout' === this._planType ) {
				const result = {};
				this._days.forEach( ( day ) => {
					const rows = document.querySelectorAll( '#fp-exrows-' + day + ' .fp-ex-row' );
					const exercises = [];
					rows.forEach( ( row ) => {
						const name = ( row.querySelector( '[data-field="name"]' )?.value || '' ).trim();
						if ( ! name ) return;
						exercises.push( {
							name: name,
							sets: parseInt( row.querySelector( '[data-field="sets"]' )?.value || '0', 10 ),
							reps: parseInt( row.querySelector( '[data-field="reps"]' )?.value || '0', 10 ),
							note: ( row.querySelector( '[data-field="note"]' )?.value || '' ).trim(),
						} );
					} );
					result[ day ] = { exercises };
				} );
				return result;
			}

			// Meal
			const result = {};
			this._meals.forEach( ( meal ) => {
				const card = document.querySelector( '.fp-meal-card[data-meal="' + meal + '"]' );
				if ( ! card ) return;
				result[ meal ] = {
					items:    ( card.querySelector( '[data-field="items"]' )?.value || '' ).trim(),
					calories: parseInt( card.querySelector( '[data-field="calories"]' )?.value || '0', 10 ),
					protein:  parseInt( card.querySelector( '[data-field="protein"]' )?.value || '0', 10 ),
					carbs:    parseInt( card.querySelector( '[data-field="carbs"]' )?.value || '0', 10 ),
					fat:      parseInt( card.querySelector( '[data-field="fat"]' )?.value || '0', 10 ),
					note:     ( card.querySelector( '[data-field="note"]' )?.value || '' ).trim(),
				};
			} );
			return result;
		}

		async _publish() {
			if ( ! window.confirm( this._s.confirm_pub ) ) return;

			const btn     = document.getElementById( 'fp-btn-publish' );
			const spinner = btn?.querySelector( '.fp-spinner' );

			if ( btn ) btn.disabled = true;
			if ( spinner ) spinner.hidden = false;

			const planData = this._collectPlanData();
			const resp = await this._fetch( 'fp_assign_plan', {
				plan_id:   this._planId,
				plan_data: JSON.stringify( planData ),
			} );

			if ( btn ) btn.disabled = false;
			if ( spinner ) spinner.hidden = true;

			if ( resp && resp.success ) {
				alert( this._s.publish_ok );
				this._closeModal();

				// Update the row in the list UI
				const row = document.getElementById( 'fp-row-' + this._planId );
				if ( row ) {
					const badges = row.querySelectorAll( '.fp-coach-row__cell .fitnesspro-badge' );
					if ( badges.length >= 2 ) {
						badges[ badges.length - 1 ].className = 'fitnesspro-badge fitnesspro-badge--active';
						badges[ badges.length - 1 ].textContent = 'فعال';
					}
					const assignBtn = row.querySelector( '.fp-btn-assign' );
					if ( assignBtn ) {
						assignBtn.disabled = true;
						assignBtn.textContent = 'منتشر شده';
					}
				}
			} else {
				const msg = resp?.data?.message || this._s.error;
				alert( msg );
			}
		}

		// ── Helpers ──────────────────────────────────────────────────────────

		async _fetch( action, data ) {
			const body = new FormData();
			body.append( 'action', action );
			body.append( 'nonce', this._nonce );
			Object.entries( data ).forEach( ( [ k, v ] ) => body.append( k, v ) );
			try {
				const res = await fetch( this._ajax, { method: 'POST', body } );
				if ( ! res.ok ) throw new Error( 'network' );
				return await res.json();
			} catch {
				return null;
			}
		}

		_esc( str ) {
			return String( str )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' )
				.replace( /"/g, '&quot;' );
		}

		_escAttr( str ) {
			return String( str ).replace( /'/g, '&#39;' );
		}
	}

	// ── Admin orders: inline coach assignment ─────────────────────────────────

	class FitnessProCoachAssign {
		constructor() {
			document.addEventListener( 'DOMContentLoaded', () => this._init() );
		}

		_init() {
			document.addEventListener( 'click', ( e ) => {
				// Open modal
				const openBtn = e.target.closest( '.fp-open-assign-btn' );
				if ( openBtn ) {
					e.preventDefault();
					const modal = document.getElementById( 'fp-assign-coach-modal' );
					if ( ! modal ) return;
					modal.querySelector( '#fp-assign-plan-id' ).value   = openBtn.dataset.planId  || '';
					modal.querySelector( '#fp-assign-coach-sel' ).value = openBtn.dataset.coachId || '0';
					modal.hidden = false;
				}

				// Close modal
				if ( e.target.id === 'fp-assign-overlay' || e.target.closest( '#fp-assign-cancel' ) ) {
					document.getElementById( 'fp-assign-coach-modal' ).hidden = true;
				}

				// Save
				if ( e.target.closest( '#fp-assign-save' ) ) {
					this._save();
				}
			} );
		}

		async _save() {
			const modal   = document.getElementById( 'fp-assign-coach-modal' );
			const planId  = modal.querySelector( '#fp-assign-plan-id' ).value;
			const coachId = modal.querySelector( '#fp-assign-coach-sel' ).value;
			const nonce   = document.getElementById( 'fp-coach-nonce-val' )?.value || '';
			const saveBtn = document.getElementById( 'fp-assign-save' );

			saveBtn.disabled = true;

			const body = new FormData();
			body.append( 'action', 'fp_assign_coach' );
			body.append( 'nonce', nonce );
			body.append( 'plan_id', planId );
			body.append( 'coach_id', coachId );

			try {
				const res  = await fetch( window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body } );
				const json = await res.json();
				if ( json && json.success ) {
					modal.hidden = true;
					location.reload();
				} else {
					alert( json?.data?.message || 'خطایی رخ داد.' );
				}
			} catch {
				alert( 'خطا در ارتباط با سرور.' );
			} finally {
				saveBtn.disabled = false;
			}
		}
	}

	// ── Coach chat panel ─────────────────────────────────────────────────────

	class FitnessProCoachChat {
		constructor( cfg ) {
			this._ajax    = cfg.ajax_url;
			this._nonce   = cfg.nonce;
			this._userId  = 0;
			this._file    = null;
			this._polling = null;

			document.addEventListener( 'DOMContentLoaded', () => this._init() );
		}

		_init() {
			const list = document.getElementById( 'fp-coach-list' );
			if ( ! list ) return;

			list.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( '.fp-btn-chat' );
				if ( btn ) {
					this._userId = parseInt( btn.dataset.userId, 10 );
					this._openChat( btn.dataset.user || '' );
				}
			} );

			document.getElementById( 'fp-coach-chat-close' )
				?.addEventListener( 'click', () => this._closeChat() );

			document.getElementById( 'fp-coach-chat-form-el' )
				?.addEventListener( 'submit', ( e ) => { e.preventDefault(); this._send(); } );

			const fileIn = document.getElementById( 'fp-chat-file-input' );
			if ( fileIn ) {
				fileIn.addEventListener( 'change', () => {
					const f  = fileIn.files[ 0 ];
					if ( ! f ) return;
					const ok = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ].includes( f.type )
					        && f.size <= 5 * 1024 * 1024;
					if ( ! ok ) {
						alert( 'فقط تصاویر (JPG, PNG, GIF, WebP) مجاز هستند. حداکثر ۵ مگابایت.' );
						fileIn.value = '';
						return;
					}
					this._file = f;
					const reader = new FileReader();
					reader.onload = ( ev ) => {
						const img = document.getElementById( 'fp-chat-preview-img' );
						if ( img ) img.src = ev.target.result;
						document.getElementById( 'fp-chat-attach-preview' ).hidden = false;
					};
					reader.readAsDataURL( f );
				} );
			}

			document.getElementById( 'fp-chat-attach-remove' )
				?.addEventListener( 'click', () => this._clearFile() );

			const ta = document.getElementById( 'fp-coach-chat-textarea' );
			if ( ta ) {
				ta.addEventListener( 'input', () => {
					ta.style.height = 'auto';
					ta.style.height = Math.min( ta.scrollHeight, 96 ) + 'px';
				} );
				ta.addEventListener( 'keydown', ( e ) => {
					if ( e.key === 'Enter' && ! e.shiftKey ) {
						e.preventDefault();
						document.getElementById( 'fp-coach-chat-form-el' )
							?.dispatchEvent( new Event( 'submit', { bubbles: true } ) );
					}
				} );
			}
		}

		async _openChat( userName ) {
			const wrap  = document.getElementById( 'fp-coach-chat-wrap' );
			const title = document.getElementById( 'fp-coach-chat-title' );
			if ( ! wrap ) return;
			if ( title ) title.textContent = 'گفتگو با: ' + userName;
			wrap.hidden = false;
			this._stopPolling();
			await this._loadAll();
			this._startPolling();
		}

		_closeChat() {
			this._stopPolling();
			const wrap = document.getElementById( 'fp-coach-chat-wrap' );
			if ( wrap ) wrap.hidden = true;
			this._userId = 0;
		}

		async _loadAll() {
			const thread = document.getElementById( 'fp-coach-thread' );
			if ( ! thread || ! this._userId ) return;

			thread.innerHTML = '<div style="text-align:center;color:#888;padding:16px;">در حال بارگذاری...</div>';
			thread.dataset.lastId = '0';

			const body = new FormData();
			body.append( 'action',   'fp_coach_get_tickets' );
			body.append( 'nonce',    this._nonce );
			body.append( 'user_id',  this._userId );
			body.append( 'since_id', '0' );

			try {
				const res  = await fetch( this._ajax, { method: 'POST', body } );
				const json = await res.json();
				if ( json?.success ) {
					thread.innerHTML = '';
					( json.data.messages || [] ).forEach( msg => {
						const isMe = parseInt( msg.sender_id, 10 ) !== this._userId;
						this._appendMsg( msg, isMe );
					} );
					thread.scrollTop = thread.scrollHeight;
				}
			} catch { /* network */ }
		}

		_startPolling() {
			this._stopPolling();
			this._polling = setInterval( () => this._pollNew(), 15000 );
		}

		_stopPolling() {
			if ( this._polling ) { clearInterval( this._polling ); this._polling = null; }
		}

		async _pollNew() {
			const thread = document.getElementById( 'fp-coach-thread' );
			if ( ! thread || ! this._userId ) return;
			const lastId = parseInt( thread.dataset.lastId || '0', 10 );

			const body = new FormData();
			body.append( 'action',   'fp_coach_get_tickets' );
			body.append( 'nonce',    this._nonce );
			body.append( 'user_id',  this._userId );
			body.append( 'since_id', lastId );

			try {
				const res  = await fetch( this._ajax, { method: 'POST', body } );
				const json = await res.json();
				if ( json?.success && json.data?.messages?.length ) {
					const wasBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 60;
					json.data.messages.forEach( msg => {
						const isMe = parseInt( msg.sender_id, 10 ) !== this._userId;
						this._appendMsg( msg, isMe );
					} );
					if ( wasBottom ) thread.scrollTop = thread.scrollHeight;
				}
			} catch { /* network */ }
		}

		async _send() {
			const ta      = document.getElementById( 'fp-coach-chat-textarea' );
			const sendBtn = document.getElementById( 'fp-coach-chat-send' );
			const message = ( ta?.value || '' ).trim();
			if ( ! message && ! this._file ) return;

			if ( sendBtn ) sendBtn.disabled = true;

			const body = new FormData();
			body.append( 'action',  'fp_coach_send_ticket' );
			body.append( 'nonce',   this._nonce );
			body.append( 'user_id', this._userId );
			body.append( 'message', message );
			if ( this._file ) {
				body.append( 'attachment', this._file, this._file.name );
			}

			try {
				const res  = await fetch( this._ajax, { method: 'POST', body } );
				const json = await res.json();
				if ( json?.success ) {
					if ( ta ) { ta.value = ''; ta.style.height = 'auto'; }
					this._clearFile();
					this._appendMsg( json.data, true );
					const thread = document.getElementById( 'fp-coach-thread' );
					if ( thread ) thread.scrollTop = thread.scrollHeight;
				} else {
					alert( json?.data?.message || 'خطا در ارسال.' );
				}
			} catch {
				alert( 'خطا در ارسال پیام.' );
			} finally {
				if ( sendBtn ) sendBtn.disabled = false;
			}
		}

		_appendMsg( msg, isMe ) {
			const thread = document.getElementById( 'fp-coach-thread' );
			if ( ! thread ) return;
			const div    = document.createElement( 'div' );
			div.className  = 'fp-msg ' + ( isMe ? 'fp-msg--me' : 'fp-msg--them' );
			div.dataset.id = msg.id || '';

			let inner = '<div class="fp-msg-bubble">';
			if ( msg.message ) {
				inner += '<p class="fp-msg-text">'
				       + String( msg.message ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
				           .replace( />/g, '&gt;' ).replace( /\n/g, '<br>' )
				       + '</p>';
			}
			if ( msg.attachment_url ) {
				const u = String( msg.attachment_url ).replace( /"/g, '&quot;' );
				inner += '<a href="' + u + '" target="_blank" rel="noopener" class="fp-msg-attachment">'
				       + '<img src="' + u + '" alt="پیوست" loading="lazy"></a>';
			}
			inner += '<time class="fp-msg-time">' + ( msg.created_at ? new Date( msg.created_at ).toLocaleTimeString( 'fa-IR', { hour: '2-digit', minute: '2-digit' } ) : '' ) + '</time>';
			inner += '</div>';

			div.innerHTML = inner;
			thread.appendChild( div );
			if ( msg.id ) thread.dataset.lastId = msg.id;
		}

		_clearFile() {
			this._file = null;
			const fileIn  = document.getElementById( 'fp-chat-file-input' );
			const preview = document.getElementById( 'fp-chat-attach-preview' );
			const prevImg = document.getElementById( 'fp-chat-preview-img' );
			if ( fileIn )  fileIn.value = '';
			if ( prevImg ) prevImg.src = '';
			if ( preview ) preview.hidden = true;
		}
	}

	// Bootstrap
	if ( typeof fp_coach !== 'undefined' ) {
		new FitnessProCoachPanel( fp_coach );
		new FitnessProCoachChat( fp_coach );
	}
	new FitnessProCoachAssign();
} )();
