const isAMC = mw.config.get( 'wgMinervaFeatures', {} ).personalMenu;
const menuButton = document.querySelector( `
	.skin-vector-2022 #vector-user-links-dropdown,
	.skin-minerva ${ isAMC ? '.minerva-user-menu' : '.navigation-drawer' }
` );

if ( menuButton !== null ) {
	const dot = document.createElement( 'div' );
	dot.className = 'mw-pulsating-dot personal-dashboard-blue-dot-dropdown';
	menuButton.appendChild( dot );
}

const menuItem = document.querySelector( `
	.skin-vector #pt-personaldashboard > :first-child,
	.skin-minerva .menu__item--personaldashboard
` );

if ( menuItem !== null ) {
	const dot2 = document.createElement( 'div' );
	dot2.className = 'mw-pulsating-dot personal-dashboard-blue-dot-link';
	menuItem.parentElement.appendChild( dot2 );
}
