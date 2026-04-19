'use strict';

class FitnessProCheckoutFlow {

	constructor( container ) {
		this._el          = container;
		this._ajaxUrl     = container.dataset.ajaxUrl;
		this._nonce       = container.dataset.nonce;
		this._isLoggedIn  = container.dataset.loggedIn === '1';
		this._startStep   = parseInt( container.dataset.startStep, 10 );
		this._totalSteps  = parseInt( container.dataset.totalSteps, 10 );
		this._currentStep = this._startStep;
		this._data        = {};
		this._init();
	}

	_init() {
		this._showStep( this._currentStep, true );
		this._updateProgress( this._currentStep );
		this._bindEvents();
	}

	// ─── Event Delegation ─────────────────────────────────────────────────────

	_bindEvents() {
		this._el.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( '.fco-btn-next' ) )   { this._handleNext();                                            return; }
			if ( e.target.closest( '.fco-btn-back' ) )   { this._handleBack();                                            return; }
			if ( e.target.closest( '#fco-btn-pay' ) )    { this._handlePay();                                             return; }
			if ( e.target.closest( '#fco-btn-login' ) )  { this._doAuth( 'login' );                                       return; }
			if ( e.target.closest( '#fco-btn-register' ) ) { this._doAuth( 'register' );                                  return; }

			const planCard = e.target.closest( '.fco-plan-card:not(.fco-plan-card--disabled)' );
			if ( planCard )  { this._selectExclusive( planCard, '.fco-plan-card', 'plan_type', 'plan' ); return; }

			const actCard = e.target.closest( '.fco-activity-card' );
			if ( actCard )   { this._selectExclusive( actCard, '.fco-activity-card', 'activity', 'value' ); return; }

			const gender = e.target.closest( '.fco-gender-btn' );
			if ( gender )    { this._selectExclusive( gender, '.fco-gender-btn', 'gender', 'gender' );    return; }

			const chip = e.target.closest( '.fco-chip' );
			if ( chip )      { chip.classList.toggle( 'is-selected' );                                    return; }

			const authTab = e.target.closest( '.fco-auth-tab' );
			if ( authTab )   { this._switchAuthTab( authTab );                                            return; }

			const toggle = e.target.closest( '.fco-pass-toggle' );
			if ( toggle )    { this._togglePassword( toggle );                                            return; }
		} );

		// Keyboard: Enter / Space activates plan cards and activity cards
		this._el.addEventListener( 'keydown', ( e ) => {
			if ( e.key !== 'Enter' && e.key !== ' ' ) return;
			const planCard = e.target.closest( '.fco-plan-card:not(.fco-plan-card--disabled)' );
			if ( planCard ) { e.preventDefault(); this._selectExclusive( planCard, '.fco-plan-card', 'plan_type', 'plan' ); return; }
			const actCard = e.target.closest( '.fco-activity-card' );
			if ( actCard )  { e.preventDefault(); this._selectExclusive( actCard, '.fco-activity-card', 'activity', 'value' ); }
		} );
	}

	// ─── Selection Helpers ────────────────────────────────────────────────────

	_selectExclusive( el, selector, dataKey, attrKey ) {
		this._el.querySelectorAll( selector ).forEach( c => c.classList.remove( 'is-selected' ) );
		el.classList.add( 'is-selected' );
		this._data[ dataKey ] = el.dataset[ attrKey ];
	}

	_switchAuthTab( tab ) {
		this._el.querySelectorAll( '.fco-auth-tab' ).forEach( t => {
			t.classList.remove( 'is-active' );
			t.setAttribute( 'aria-selected', 'false' );
		} );
		this._el.querySelectorAll( '.fco-auth-panel' ).forEach( p => p.classList.remove( 'is-active' ) );
		tab.classList.add( 'is-active' );
		tab.setAttribute( 'aria-selected', 'true' );
		const panel = document.getElementById( 'fco-panel-' + tab.dataset.tab );
		if ( panel ) panel.classList.add( 'is-active' );
	}

	_togglePassword( btn ) {
		const input = document.getElementById( btn.dataset.target );
		if ( input ) input.type = input.type === 'password' ? 'text' : 'password';
	}

	// ─── Navigation ───────────────────────────────────────────────────────────

	async _handleNext() {
		const step = this._currentStep;
		if ( ! this._validate( step ) ) return;
		this._collect( step );

		const next = step + 1;
		if ( next > 6 ) return;

		this._showStep( next, true );
		this._currentStep = next;
		this._updateProgress( next );

		if ( next === 6 ) {
			this._renderBMI();
			this._renderSummary();
		}
	}

	_handleBack() {
		const prev = this._currentStep - 1;
		if ( prev < this._startStep ) return;
		this._showStep( prev, false );
		this._currentStep = prev;
		this._updateProgress( prev );
	}

	async _handlePay() {
		const btn    = document.getElementById( 'fco-btn-pay' );
		const notice = document.getElementById( 'fco-pay-notice' );

		if ( ! this._data.plan_type ) {
			this._showNotice( notice, 'error', 'لطفاً ابتدا نوع برنامه را انتخاب کنید.' );
			return;
		}

		this._setBtnLoading( btn, true );
		this._showNotice( notice, 'info', 'در حال پردازش...' );

		try {
			// Single combined call: saves profile transient + adds to WC cart + returns checkout URL
			const res = await this._fetch( 'fp_process_checkout', {
				profile: JSON.stringify( this._data ),
			} );

			if ( res.success ) {
				this._showNotice( notice, 'success', res.data.message );
				// Brief pause so the success message is visible before redirect
				setTimeout( () => { window.location.href = res.data.checkout_url; }, 600 );
			} else {
				this._showNotice( notice, 'error', res.data.message || 'خطایی رخ داد.' );
				this._setBtnLoading( btn, false );
			}
		} catch {
			this._showNotice( notice, 'error', 'خطا در اتصال به سرور. لطفاً دوباره تلاش کنید.' );
			this._setBtnLoading( btn, false );
		}
	}

	// ─── Step Visibility ──────────────────────────────────────────────────────

	_showStep( n, forward ) {
		const current = this._el.querySelector( '.fco-step.is-active' );
		const next    = document.getElementById( 'fco-step-' + n );
		if ( ! next ) return;

		if ( current ) {
			current.classList.remove( 'is-active' );
			current.classList.add( 'is-exiting' );
			setTimeout( () => current.classList.remove( 'is-exiting' ), 400 );
		}

		next.classList.add( 'is-active' );
		this._el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	_updateProgress( n ) {
		const idx  = n - this._startStep + 1;
		const pct  = ( idx / this._totalSteps ) * 100;
		const fill = document.getElementById( 'fco-progress-fill' );
		const cur  = document.getElementById( 'fco-step-current' );
		const wrap = this._el.querySelector( '.fco-progress-wrap' );

		if ( fill ) fill.style.width = pct + '%';
		if ( cur )  cur.textContent  = idx;
		if ( wrap ) wrap.setAttribute( 'aria-valuenow', Math.round( pct ) );
	}

	// ─── Validation ───────────────────────────────────────────────────────────

	_validate( step ) {
		const noticeIds = { 2: 'fco-plan-notice', 3: 'fco-profile-notice', 5: 'fco-activity-notice' };
		const notice    = document.getElementById( noticeIds[ step ] || '' );
		if ( notice ) this._showNotice( notice, '', '' );

		switch ( step ) {
			case 2:
				if ( ! this._data.plan_type ) {
					this._showNotice( notice, 'error', 'لطفاً نوع برنامه را انتخاب کنید.' );
					return false;
				}
				break;

			case 3: {
				const height = parseFloat( document.getElementById( 'fco-height' )?.value );
				const weight = parseFloat( document.getElementById( 'fco-weight' )?.value );
				const age    = parseInt( document.getElementById( 'fco-age' )?.value, 10 );
				if ( ! height || height < 100 || height > 250 ) {
					this._showNotice( notice, 'error', 'لطفاً قد معتبر وارد کنید (۱۰۰ تا ۲۵۰ سانتی‌متر).' );
					return false;
				}
				if ( ! weight || weight < 30 || weight > 300 ) {
					this._showNotice( notice, 'error', 'لطفاً وزن معتبر وارد کنید (۳۰ تا ۳۰۰ کیلوگرم).' );
					return false;
				}
				if ( ! age || age < 12 || age > 90 ) {
					this._showNotice( notice, 'error', 'لطفاً سن معتبر وارد کنید (۱۲ تا ۹۰ سال).' );
					return false;
				}
				break;
			}

			case 5:
				if ( ! this._data.activity ) {
					this._showNotice( notice, 'error', 'لطفاً سطح فعالیت خود را انتخاب کنید.' );
					return false;
				}
				break;
		}
		return true;
	}

	// ─── Data Collection ──────────────────────────────────────────────────────

	_collect( step ) {
		switch ( step ) {
			case 2: {
				const sel = this._el.querySelector( '.fco-plan-card.is-selected' );
				if ( sel ) this._data.plan_type = sel.dataset.plan;
				break;
			}
			case 3: {
				this._data.first_name    = document.getElementById( 'fco-firstname' )?.value.trim()    || '';
				this._data.last_name     = document.getElementById( 'fco-lastname' )?.value.trim()     || '';
				this._data.age           = parseFloat( document.getElementById( 'fco-age' )?.value )    || 0;
				this._data.height        = parseFloat( document.getElementById( 'fco-height' )?.value ) || 0;
				this._data.weight        = parseFloat( document.getElementById( 'fco-weight' )?.value ) || 0;
				this._data.target_weight = parseFloat( document.getElementById( 'fco-target-weight' )?.value ) || 0;
				this._data.goals         = Array.from(
					this._el.querySelectorAll( '#fco-goals-chips .fco-chip.is-selected' )
				).map( c => c.dataset.value );
				const g = this._el.querySelector( '.fco-gender-btn.is-selected' );
				this._data.gender = g ? g.dataset.gender : '';
				break;
			}
			case 4: {
				this._data.diseases   = Array.from(
					this._el.querySelectorAll( '#fco-diseases-chips .fco-chip.is-selected' )
				).map( c => c.dataset.value );
				this._data.eating_dis = Array.from(
					this._el.querySelectorAll( '#fco-eating-chips .fco-chip.is-selected' )
				).map( c => c.dataset.value );
				break;
			}
			case 5: {
				const sel = this._el.querySelector( '.fco-activity-card.is-selected' );
				if ( sel ) this._data.activity = sel.dataset.value;
				break;
			}
		}
	}

	// ─── AJAX Calls ───────────────────────────────────────────────────────────

	async _doAuth( action ) {
		const isLogin = action === 'login';
		const notice  = document.getElementById( 'fco-auth-notice' );
		const btn     = document.getElementById( isLogin ? 'fco-btn-login' : 'fco-btn-register' );

		this._showNotice( notice, '', '' );
		this._setBtnLoading( btn, true );

		const payload = { auth_action: action };

		if ( isLogin ) {
			payload.email    = document.getElementById( 'fco-login-email' )?.value.trim()  || '';
			payload.password = document.getElementById( 'fco-login-password' )?.value      || '';
		} else {
			payload.first_name = document.getElementById( 'fco-reg-firstname' )?.value.trim() || '';
			payload.last_name  = document.getElementById( 'fco-reg-lastname' )?.value.trim()  || '';
			payload.email      = document.getElementById( 'fco-reg-email' )?.value.trim()     || '';
			payload.phone      = document.getElementById( 'fco-reg-phone' )?.value.trim()     || '';
			payload.password   = document.getElementById( 'fco-reg-password' )?.value         || '';
		}

		try {
			const res = await this._fetch( 'fp_cof_auth', payload );
			if ( res.success ) {
				this._nonce      = res.data.nonce;
				this._isLoggedIn = true;
				this._showStep( 2, true );
				this._currentStep = 2;
				this._updateProgress( 2 );
			} else {
				this._showNotice( notice, 'error', res.data.message || 'خطایی رخ داد.' );
				this._setBtnLoading( btn, false );
			}
		} catch {
			this._showNotice( notice, 'error', 'خطا در اتصال به سرور.' );
			this._setBtnLoading( btn, false );
		}
	}

	async _doSaveProfile() {
		try {
			await this._fetch( 'fp_cof_save_profile', { profile: JSON.stringify( this._data ) } );
		} catch {
			// non-fatal — user still proceeds to step 6
		}
	}

	// ─── BMI + Summary Rendering ──────────────────────────────────────────────

	_renderBMI() {
		const h = this._data.height;
		const w = this._data.weight;
		if ( ! h || ! w ) return;

		const bmi        = w / Math.pow( h / 100, 2 );
		const bmiDisplay = Math.round( bmi * 10 ) / 10;
		const ARC        = Math.PI * 90; // arc length ≈ 282.74
		const progress   = Math.min( Math.max( ( bmi - 15 ) / 25, 0 ), 1 );
		const dashFill   = ( progress * ARC ).toFixed( 2 );

		let color, label;
		if ( bmi < 18.5 )      { color = '#4DA6FF'; label = 'کمبود وزن'; }
		else if ( bmi < 25 )   { color = '#00FF39'; label = 'وزن نرمال'; }
		else if ( bmi < 30 )   { color = '#FFB800'; label = 'اضافه وزن'; }
		else                   { color = '#FF4D4D'; label = 'چاقی'; }

		const arc    = this._el.querySelector( '.fco-bmi-arc-fill' );
		const numEl  = this._el.querySelector( '.fco-bmi-number' );
		const catEl  = this._el.querySelector( '.fco-bmi-category-text' );
		const status = document.getElementById( 'fco-bmi-status' );
		const wEl    = document.getElementById( 'fco-bmi-weight' );
		const hEl    = document.getElementById( 'fco-bmi-height' );

		if ( arc )   { arc.style.strokeDasharray = `${dashFill} ${ARC.toFixed(2)}`; arc.setAttribute( 'stroke', color ); }
		if ( numEl ) { numEl.textContent = bmiDisplay; numEl.setAttribute( 'fill', color ); }
		if ( catEl ) { catEl.textContent = label;      catEl.setAttribute( 'fill', color ); }
		if ( status ){ status.textContent = label;     status.style.color = color; }
		if ( wEl )     wEl.textContent = w + ' kg';
		if ( hEl )     hEl.textContent = h + ' cm';
	}

	_renderSummary() {
		const planNames = { workout: 'برنامه تمرینی', meal: 'برنامه تغذیه' };
		const set = ( id, val ) => { const el = document.getElementById( id ); if ( el ) el.textContent = val || '--'; };

		set( 'fco-sum-plan',     planNames[ this._data.plan_type ] );
		set( 'fco-sum-goal',     ( this._data.goals || [] ).join( '، ' ) );
		set( 'fco-sum-activity', this._data.activity );
		set( 'fco-sum-target',   this._data.target_weight ? this._data.target_weight + ' kg' : '' );
	}

	// ─── UI Utilities ─────────────────────────────────────────────────────────

	_showNotice( el, type, message ) {
		if ( ! el ) return;
		el.className   = 'fco-notice';
		el.textContent = '';
		if ( ! type || ! message ) return;
		el.classList.add( 'is-visible', 'fco-notice--' + type );
		el.textContent = message;
	}

	_setBtnLoading( btn, on ) {
		if ( ! btn ) return;
		btn.disabled = on;
		btn.classList.toggle( 'fco-btn--loading', on );
	}

	async _fetch( action, data ) {
		const body = new URLSearchParams( { action, nonce: this._nonce, ...data } );
		const res  = await fetch( this._ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		} );
		if ( ! res.ok ) throw new Error( 'Network error: ' + res.status );
		return res.json();
	}
}

document.addEventListener( 'DOMContentLoaded', () => {
	const c = document.getElementById( 'fitness-app-container' );
	if ( c ) new FitnessProCheckoutFlow( c );
} );
