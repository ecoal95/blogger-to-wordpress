(function() {
	var _slice = Array.prototype.slice;
	Migrator.Step = function(description, cb) {
		this.description = description;
		this.done = false;
		this.callback = cb;
	}

	Migrator.Step.prototype = new Migrator.EventListener();

	Migrator.Utils.extend(Migrator.Step.prototype, {
		description: null,
		done: false,
		callback: null,

		/**
		 * Run a handler with a custom number of arguments passed to callback
		 */
		run: function() {
			var args = _slice.call(arguments);

			args.unshift(this);

			this.callback.apply(this, args);
		},

		/**
		 * Finish an step, must be called from callback
		 */
		resolve: function(data) {
			this.done = true;
			this.responseData = data;
			this.trigger('resolve', data);
		},

		error: function(data) {
			this.trigger('error', data);
		},

		toString: function() {
			return this.description;
		}
	});
}());
