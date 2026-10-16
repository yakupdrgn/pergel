/**
 * Estimated delivery date (Pergel\Woo\Delivery): counts the dates from the
 * rules on the block, in the site's time zone, so a cached page shows
 * today's. Hides while the chosen variation is not shipped or cannot be
 * bought.
 */
const DAY = 86400000;

/** Today's date (as midnight UTC) and the minutes past midnight, on the site's clock. */
const siteNow = ( { tz, offset } ) => {
	const now = new Date();
	if ( tz ) {
		try {
			const parts = {};
			new Intl.DateTimeFormat( 'en-US', { timeZone: tz, year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', hourCycle: 'h23' } )
				.formatToParts( now )
				.forEach( ( { type, value } ) => ( parts[ type ] = Number( value ) ) );
			return { day: Date.UTC( parts.year, parts.month - 1, parts.day ), minutes: ( parts.hour % 24 ) * 60 + parts.minute };
		} catch {
			// An unknown zone: the offset below.
		}
	}
	const shifted = new Date( now.getTime() + offset * 60000 );
	return {
		day: Date.UTC( shifted.getUTCFullYear(), shifted.getUTCMonth(), shifted.getUTCDate() ),
		minutes: shifted.getUTCHours() * 60 + shifted.getUTCMinutes(),
	};
};

const key = ( day ) => new Date( day ).toISOString().slice( 0, 10 );

/** The delivery dates and whether an order now ships today; null when they cannot be counted. */
const estimate = ( rules, now ) => {
	const working = ( day ) => {
		const k = key( day );
		return rules.days.includes( new Date( day ).getUTCDay() ) && ! rules.off.some( ( [ from, to ] ) => k >= from && k <= to );
	};
	// Days off end within a year or so, so a working day is never further.
	const next = ( day ) => {
		for ( let i = 0; i < 400; i++ ) {
			day += DAY;
			if ( working( day ) ) {
				return day;
			}
		}
		return null;
	};
	const add = ( day, count ) => {
		for ( let i = 0; i < count && null !== day; i++ ) {
			day = next( day );
		}
		return day;
	};
	const today = working( now.day ) && ( null === rules.cutoff || now.minutes < rules.cutoff );
	const received = today ? now.day : next( now.day );
	const ships = null === received ? null : add( received, rules.handling );
	const from = null === ships ? null : add( ships, rules.min );
	const to = null === ships ? null : add( ships, rules.max );
	if ( null === from || null === to ) {
		return null;
	}
	return { from, to, today: today && 0 === rules.handling && null !== rules.cutoff };
};

/** "20–22 October", or "Thursday, 22 October" for a single day. */
const format = ( { from, to }, locale ) => {
	const options = { timeZone: 'UTC', day: 'numeric', month: 'long' };
	try {
		if ( from === to ) {
			return new Intl.DateTimeFormat( locale, { ...options, weekday: 'long' } ).format( from );
		}
		return new Intl.DateTimeFormat( locale, options ).formatRange( from, to );
	} catch {
		return `${ key( from ) } – ${ key( to ) }`;
	}
};

const block = document.querySelector( '[data-pergel-delivery]' );

if ( block ) {
	let rules = null;
	try {
		rules = JSON.parse( block.dataset.pergelDelivery );
	} catch {
		// Left hidden.
	}
	const result = rules && estimate( rules, siteNow( rules ) );
	if ( result ) {
		block.querySelector( '.wp-block-pergel-delivery-estimate__range' ).textContent = format( result, rules.locale );
		const today = block.querySelector( '.wp-block-pergel-delivery-estimate__today' );
		if ( today ) {
			today.hidden = ! result.today;
		}

		// The form before the block, for a variable product's choices.
		const form = [ ...document.querySelectorAll( 'form.wp-block-woocommerce-add-to-cart-with-options' ) ]
			.filter( ( f ) => f.compareDocumentPosition( block ) & Node.DOCUMENT_POSITION_FOLLOWING )
			.pop();
		const chosen = () => {
			if ( ! form ) {
				return [];
			}
			const radios = [ ...form.querySelectorAll( '[role="radio"][aria-checked="true"]' ) ].map( ( r ) => r.value );
			const selects = [ ...form.querySelectorAll( 'select' ) ].map( ( s ) => s.value ).filter( Boolean );
			return [ ...radios, ...selects ];
		};
		const update = () => {
			const values = chosen();
			block.hidden = values.length > 0 && rules.skip.some( ( set ) => set.every( ( value ) => values.includes( value ) ) );
		};
		if ( form && rules.skip.length ) {
			new window.MutationObserver( update ).observe( form, { attributes: true, attributeFilter: [ 'aria-checked', 'class' ], subtree: true } );
			form.addEventListener( 'change', update );
		}
		update();
	}
}
