/* FitnessPro — AI Processing Landing Page JS */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var container = document.getElementById( 'fp-ai-container' );
		if ( ! container ) return;

		var redirectUrl = container.dataset.redirect  || '';
		var seconds     = parseInt( container.dataset.seconds, 10 ) || 10;
		var totalMs     = seconds * 1000;

		var fillEl   = document.getElementById( 'fp-ai-fill' );
		var pctEl    = document.getElementById( 'fp-ai-pct' );
		var etaEl    = document.getElementById( 'fp-ai-eta' );
		var statusEl = document.getElementById( 'fp-ai-status' );
		var barEl    = container.querySelector( '.fp-ai-progress-bar' );
		var stepItems = container.querySelectorAll( '.fp-ai-step-item' );

		var startTime = null;
		var lastPct   = 0;
		var done      = false;

		// Schedule step activations based on data-delay attributes
		stepItems.forEach( function ( item ) {
			var delay = parseInt( item.dataset.delay, 10 ) * 1000;
			setTimeout( function () {
				// Mark previous steps as done
				stepItems.forEach( function ( s ) {
					if ( s !== item && ! s.classList.contains( 'is-done' ) && ! s.classList.contains( 'is-active' ) ) {
						// Only mark as done steps that were active before
					}
					if ( s.classList.contains( 'is-active' ) ) {
						s.classList.remove( 'is-active' );
						s.classList.add( 'is-done' );
					}
				} );
				item.classList.add( 'is-active' );
				if ( statusEl ) {
					statusEl.textContent = item.querySelector( '.fp-ai-step-label' )
						? item.querySelector( '.fp-ai-step-label' ).textContent
						: '';
				}
			}, delay );
		} );

		// Mark all steps done at the end
		setTimeout( function () {
			stepItems.forEach( function ( item ) {
				item.classList.remove( 'is-active' );
				item.classList.add( 'is-done' );
			} );
			if ( statusEl ) {
				statusEl.textContent = 'برنامه اختصاصی شما آماده است!';
			}
		}, totalMs - 500 );

		// rAF progress counter
		function tick( timestamp ) {
			if ( done ) return;
			if ( ! startTime ) startTime = timestamp;

			var elapsed = timestamp - startTime;
			var pct     = Math.min( 100, Math.round( ( elapsed / totalMs ) * 100 ) );
			var secsLeft = Math.max( 0, Math.ceil( ( totalMs - elapsed ) / 1000 ) );

			if ( pct !== lastPct ) {
				lastPct = pct;
				if ( fillEl ) fillEl.style.width = pct + '%';
				if ( pctEl )  pctEl.textContent  = pct;
				if ( barEl )  barEl.setAttribute( 'aria-valuenow', pct );
				if ( etaEl )  etaEl.textContent  = secsLeft + ' ثانیه دیگر';
			}

			if ( elapsed < totalMs ) {
				requestAnimationFrame( tick );
			} else {
				done = true;
				if ( fillEl ) fillEl.style.width = '100%';
				if ( pctEl )  pctEl.textContent  = '100';
				if ( barEl )  barEl.setAttribute( 'aria-valuenow', 100 );
				if ( etaEl )  etaEl.textContent  = '۰ ثانیه دیگر';

				// Redirect after a brief pause so the user sees 100%
				setTimeout( function () {
					if ( redirectUrl ) {
						window.location.href = redirectUrl;
					}
				}, 600 );
			}
		}

		requestAnimationFrame( tick );
	} );
}() );
