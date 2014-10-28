/**
 * Base event listener interface
 *
 * Anithing with custom events should inherit from this class
 */
Migrator.EventListener = function() {
	this.handlers = {};
};
Migrator.Utils.extend(Migrator.EventListener.prototype, {
	/**
	 * Register event listener
	 *
	 * @param {String} type
	 * @param {Function} callback
	 */
	on: function(type, callback) {
		if ( this.handlers[type] === undefined ) {
			this.handlers[type] = [];
		}

		this.handlers[type].push(callback);
	},

	/**
	 * Trigger an event
	 *
	 * @param {String} type
	 */
	trigger: function(type) {
		var i = 0,
			callbacks = this.handlers[type],
			len,
			args;

		if ( ! callbacks ) {
			return;
		}

		// Get all arguments except first
		args = Array.prototype.slice.call(arguments);
		args.shift();

		len = callbacks.length;
		for ( ; i < len; i++ ) {
			callbacks[i].apply(this, args);
		}
	}
});
