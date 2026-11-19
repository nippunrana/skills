import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import groupBy from 'lodash/groupBy.js';

const assets = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..', 'assets' );

const enqueued = [ 'css/frontend.css', 'js/frontend.js', 'js/summary-toggle.js', 'js/admin.js' ];

const entries = enqueued.map( ( file ) => ( {
	file,
	type: path.extname( file ).slice( 1 ),
	hash: createHash( 'sha256' ).update( readFileSync( path.join( assets, file ) ) ).digest( 'hex' ).slice( 0, 12 ),
} ) );

console.log( JSON.stringify( groupBy( entries, 'type' ), null, 2 ) );
