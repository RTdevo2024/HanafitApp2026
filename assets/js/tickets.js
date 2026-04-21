/* FitnessPro — Tickets, Notification Bell & Admin Monitor (Vanilla OOP) */
/* global fp_tickets_config, fp_tickets_admin_config */
( function () {
	'use strict';

	// ── Shared helpers ──────────────────────────────────────────────────────

	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function formatTime( datetime ) {
		if ( ! datetime ) return '';
		const ts   = new Date( datetime ).getTime();
		if ( isNaN( ts ) ) return '';
		const diff = Math.floor( ( Date.now() - ts ) / 1000 );
		if ( diff < 60 )    return 'همین الان';
		if ( diff < 3600 )  return Math.floor( diff / 60 ) + ' دقیقه پیش';
		if ( diff < 86400 ) return Math.floor( diff / 3600 ) + ' ساعت پیش';
		return new Date( datetime ).toLocaleDateString( 'fa-IR' );
	}

	// ── Public ticket thread ─────────────────────────────────────────────────

	class FitnessProTickets {
		constructor( cfg ) {
			this._ajax    = cfg.ajax_url;
			this._nonce   = cfg.nonce;
			this._userId  = cfg.user_id;
			this._s       = cfg.strings || {};
			this._file    = null;
			this._polling = null;

			document.addEventListener( 'DOMContentLoaded', () => this._init() );
		}

		_init() {
			if ( ! document.getElementById( 'fp-thread' ) ) return;
			this._scrollToBottom();
			this._bindForm();
			this._startPolling();
		}

		_bindForm() {
			const form    = document.getElementById( 'fp-msg-form' );
			const fileIn  = document.getElementById( 'fp-file-input' );
			const preview = document.getElementById( 'fp-attach-preview' );
			const prevImg = document.getElementById( 'fp-preview-img' );
			const removeBtn = document.getElementById( 'fp-attach-remove' );
			const textarea  = document.getElementById( 'fp-msg-textarea' );

			if ( fileIn ) {
				fileIn.addEventListener( 'change', () => {
					const f = fileIn.files[ 0 ];
					if ( ! f ) return;
					const ok = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ].includes( f.type )
					        && f.size <= 5 * 1024 * 1024;
					if ( ! ok ) {
						alert( this._s.file_error || 'فایل نامعتبر است.' );
						fileIn.value = '';
						return;
					}
					this._file = f;
					const reader = new FileReader();
					reader.onload = ( e ) => {
						if ( prevImg ) prevImg.src = e.target.result;
						if ( preview ) preview.hidden = false;
					};
					reader.readAsDataURL( f );
				} );
			}

			if ( removeBtn ) {
				removeBtn.addEventListener( 'click', () => this._clearAttach() );
			}

			if ( textarea ) {
				textarea.addEventListener( 'input', () => {
					textarea.style.height = 'auto';
					textarea.style.height = Math.min( textarea.scrollHeight, 120 ) + 'px';
				} );
				textarea.addEventListener( 'keydown', ( e ) => {
					if ( e.key === 'Enter' && ! e.shiftKey ) {
						e.preventDefault();
						form?.dispatchEvent( new Event( 'submit', { bubbles: true } ) );
					}
				} );
			}

			if ( form ) {
				form.addEventListener( 'submit', ( e ) => {
					e.preventDefault();
					this._send();
				} );
			}
		}

		async _send() {
			const textarea = document.getElementById( 'fp-msg-textarea' );
			const sendBtn  = document.getElementById( 'fp-send-btn' );
			const message  = ( textarea?.value || '' ).trim();

			if ( ! message && ! this._file ) return;

			if ( sendBtn ) sendBtn.disabled = true;

			const body = new FormData();
			body.append( 'action',  'fp_send_ticket' );
			body.append( 'nonce',   this._nonce );
			body.append( 'message', message );
			if ( this._file ) {
				body.append( 'attachment', this._file, this._file.name );
			}

			try {
				const res  = await fetch( this._ajax, { method: 'POST', body } );
				const json = await res.json();

				if ( json?.success ) {
					if ( textarea ) { textarea.value = ''; textarea.style.height = 'auto'; }
					this._clearAttach();
					this._stopPolling();
					this._appendMsg( json.data, true );
					this._scrollToBottom();
					this._startPolling();
				} else {
					alert( json?.data?.message || this._s.error || 'خطا در ارسال.' );
				}
			} catch {
				alert( this._s.error || 'خطا در ارسال.' );
			} finally {
				if ( sendBtn ) sendBtn.disabled = false;
			}
		}

		_clearAttach() {
			this._file = null;
			const fileIn  = document.getElementById( 'fp-file-input' );
			const preview = document.getElementById( 'fp-attach-preview' );
			const prevImg = document.getElementById( 'fp-preview-img' );
			if ( fileIn )  fileIn.value = '';
			if ( prevImg ) prevImg.src = '';
			if ( preview ) preview.hidden = true;
		}

		_startPolling() {
			this._stopPolling();
			this._polling = setInterval( () => this._poll(), 15000 );
		}

		_stopPolling() {
			if ( this._polling ) { clearInterval( this._polling ); this._polling = null; }
		}

		async _poll() {
			const thread = document.getElementById( 'fp-thread' );
			if ( ! thread ) return;
			const lastId = parseInt( thread.dataset.lastId || '0', 10 );

			const body = new FormData();
			body.append( 'action',   'fp_get_tickets' );
			body.append( 'nonce',    this._nonce );
			body.append( 'since_id', lastId );

			try {
				const res  = await fetch( this._ajax, { method: 'POST', body } );
				const json = await res.json();
				if ( json?.success && json.data?.messages?.length ) {
					const wasBottom = this._isAtBottom();
					json.data.messages.forEach( ( msg ) => {
						const isMe = parseInt( msg.sender_id, 10 ) === this._userId;
						this._appendMsg( msg, isMe );
					} );
					if ( wasBottom ) this._scrollToBottom();
				}
			} catch { /* network */ }
		}

		_appendMsg( msg, isMe ) {
			const thread = document.getElementById( 'fp-thread' );
			if ( ! thread ) return;

			document.getElementById( 'fp-thread-empty' )?.remove();

			const div  = document.createElement( 'div' );
			div.className  = 'fp-msg ' + ( isMe ? 'fp-msg--me' : 'fp-msg--them' );
			div.dataset.id = msg.id;

			let inner = '<div class="fp-msg-bubble">';
			if ( msg.message ) {
				inner += '<p class="fp-msg-text">' + esc( msg.message ).replace( /\n/g, '<br>' ) + '</p>';
			}
			if ( msg.attachment_url ) {
				inner += '<a href="' + esc( msg.attachment_url ) + '" target="_blank" rel="noopener" class="fp-msg-attachment">'
				       + '<img src="' + esc( msg.attachment_url ) + '" alt="پیوست" loading="lazy"></a>';
			}
			inner += '<time class="fp-msg-time">' + formatTime( msg.created_at ) + '</time>';
			inner += '</div>';

			div.innerHTML = inner;
			thread.appendChild( div );
			if ( msg.id ) thread.dataset.lastId = msg.id;
		}

		_isAtBottom() {
			const el = document.getElementById( 'fp-thread' );
			if ( ! el ) return true;
			return el.scrollHeight - el.scrollTop - el.clientHeight < 60;
		}

		_scrollToBottom() {
			const el = document.getElementById( 'fp-thread' );
			if ( el ) el.scrollTop = el.scrollHeight;
		}
	}

	// ── Notification Bell ────────────────────────────────────────────────────

	class FitnessProNotifBell {
		constructor( cfg ) {
			this._ajax    = cfg.ajax_url;
			this._nonce   = cfg.nonce;
			this._open    = false;
			this._polling = null;

			document.addEventListener( 'DOMContentLoaded', () => this._init() );
		}

		_init() {
			const bell = document.getElementById( 'fp-notif-bell' );
			if ( ! bell ) return;

			bell.addEventListener( 'click', () => this._toggle() );

			document.addEventListener( 'click', ( e ) => {
				if ( this._open && ! e.target.closest( '.fp-notif-wrap' ) ) {
					this._close();
				}
			} );

			document.getElementById( 'fp-notif-mark-read' )?.addEventListener( 'click', ( e ) => {
				e.stopPropagation();
				this._markRead();
			} );

			this._fetch();
			this._polling = setInterval( () => this._fetch(), 60000 );
		}

		async _fetch() {
			const body = new FormData();
			body.append( 'action', 'fp_get_notifications' );
			body.append( 'nonce',  this._nonce );
			try {
				const res  = await fetch( this._ajax, { method: 'POST', body } );
				const json = await res.json();
				if ( json?.success ) this._render( json.data );
			} catch { /* network */ }
		}

		_render( data ) {
			const bell  = document.getElementById( 'fp-notif-bell' );
			const badge = document.getElementById( 'fp-notif-badge' );
			const list  = document.getElementById( 'fp-notif-list' );
			const unread = data.unread || 0;

			if ( badge ) {
				badge.hidden      = unread === 0;
				badge.textContent = unread > 9 ? '9+' : String( unread );
			}
			if ( bell ) bell.classList.toggle( 'has-unread', unread > 0 );

			if ( list ) {
				const items = data.items || [];
				if ( ! items.length ) {
					list.innerHTML = '<div class="fp-notif-empty">اعلانی وجود ندارد.</div>';
					return;
				}
				list.innerHTML = items.map( ( n ) =>
					'<div class="fp-notif-item' + ( n.read ? '' : ' is-unread' ) + '">'
					+ '<p class="fp-notif-item__msg">' + esc( n.message || '' ) + '</p>'
					+ '<span class="fp-notif-item__time">' + formatTime( n.created_at ) + '</span>'
					+ '</div>'
				).join( '' );
			}
		}

		async _markRead() {
			const body = new FormData();
			body.append( 'action', 'fp_mark_notifications_read' );
			body.append( 'nonce',  this._nonce );
			try {
				await fetch( this._ajax, { method: 'POST', body } );
				await this._fetch();
			} catch { /* network */ }
		}

		_toggle() {
			const dropdown = document.getElementById( 'fp-notif-dropdown' );
			if ( ! dropdown ) return;
			this._open = ! this._open;
			dropdown.hidden = ! this._open;
			if ( this._open ) this._fetch();
		}

		_close() {
			const dropdown = document.getElementById( 'fp-notif-dropdown' );
			if ( dropdown ) dropdown.hidden = true;
			this._open = false;
		}
	}

	// ── Admin ticket monitor ─────────────────────────────────────────────────

	class FitnessProTicketAdmin {
		constructor( cfg ) {
			this._ajax  = cfg.ajax_url;
			this._nonce = cfg.nonce;

			document.addEventListener( 'DOMContentLoaded', () => this._init() );
		}

		_init() {
			const list = document.getElementById( 'fp-ticket-thread-list' );
			if ( ! list ) return;

			list.addEventListener( 'click', ( e ) => {
				const item = e.target.closest( '.fp-thread-item' );
				if ( ! item ) return;
				list.querySelectorAll( '.fp-thread-item' ).forEach( i => i.classList.remove( 'is-active' ) );
				item.classList.add( 'is-active' );
				this._loadThread(
					parseInt( item.dataset.userId,  10 ),
					parseInt( item.dataset.coachId, 10 ),
					item.dataset.userName  || '',
					item.dataset.coachName || ''
				);
			} );
		}

		async _loadThread( userId, coachId, userName, coachName ) {
			const viewer = document.getElementById( 'fp-ticket-thread-viewer' );
			if ( ! viewer ) return;

			viewer.innerHTML = '<div style="padding:24px;text-align:center;color:#888;">در حال بارگذاری...</div>';

			const body = new FormData();
			body.append( 'action',   'fp_admin_get_ticket_thread' );
			body.append( 'nonce',    this._nonce );
			body.append( 'user_id',  userId );
			body.append( 'coach_id', coachId );

			try {
				const res  = await fetch( this._ajax, { method: 'POST', body } );
				const json = await res.json();

				if ( ! json?.success ) {
					viewer.innerHTML = '<div style="padding:24px;text-align:center;color:#c00;">خطا در بارگذاری.</div>';
					return;
				}

				const messages = json.data.messages || [];
				let html = '<div class="fp-ticket-viewer-header">'
				         + esc( userName ) + ' ↔ ' + esc( coachName )
				         + '</div><div class="fp-ticket-viewer-thread">';

				if ( ! messages.length ) {
					html += '<div style="text-align:center;color:#aaa;padding:24px;">پیامی وجود ندارد.</div>';
				} else {
					messages.forEach( ( msg ) => {
						const isUser = parseInt( msg.sender_id, 10 ) === userId;
						const side   = isUser ? 'fp-msg--me' : 'fp-msg--them';
						html += '<div class="fp-msg ' + side + '">'
						      + '<div class="fp-msg-bubble">';
						if ( msg.message ) {
							html += '<p class="fp-msg-text">'
							      + esc( msg.message ).replace( /\n/g, '<br>' )
							      + '</p>';
						}
						if ( msg.attachment_url ) {
							html += '<a href="' + esc( msg.attachment_url ) + '" target="_blank" rel="noopener" class="fp-msg-attachment">'
							      + '<img src="' + esc( msg.attachment_url ) + '" alt="پیوست" loading="lazy"></a>';
						}
						html += '<time class="fp-msg-time">'
						      + esc( isUser ? userName : coachName ) + ' · '
						      + formatTime( msg.created_at )
						      + '</time>';
						html += '</div></div>';
					} );
				}

				html += '</div>';
				viewer.innerHTML = html;
				const threadEl = viewer.querySelector( '.fp-ticket-viewer-thread' );
				if ( threadEl ) threadEl.scrollTop = threadEl.scrollHeight;

			} catch {
				viewer.innerHTML = '<div style="padding:24px;text-align:center;color:#c00;">خطا در ارتباط با سرور.</div>';
			}
		}
	}

	// ── Bootstrap ────────────────────────────────────────────────────────────

	if ( window.fp_tickets_config ) {
		new FitnessProTickets( window.fp_tickets_config );
		new FitnessProNotifBell( window.fp_tickets_config );
	}

	if ( window.fp_tickets_admin_config ) {
		new FitnessProTicketAdmin( window.fp_tickets_admin_config );
	}

} )();
