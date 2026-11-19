if ( ! Element.prototype.replaceChildren ) {
	Element.prototype.replaceChildren = function replaceChildren( ...nodes ) {
		while ( this.lastChild ) {
			this.removeChild( this.lastChild );
		}
		this.append( ...nodes );
	};
}

if ( ! Array.prototype.at ) {
	Array.prototype.at = function at( index ) {
		const position = index < 0 ? this.length + index : index;
		return this[ position ];
	};
}
