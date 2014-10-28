(function() {
	var _slice = Array.prototype.slice;
	Migrator.Step = function(description, cb) {
		Migrator.EventListener.apply(this, arguments);
		this.description = description;
		this.done = false;
		this.callback = cb;
	}

	Migrator.Step.prototype = Object.create(Migrator.EventListener.prototype);

	Migrator.Utils.extend(Migrator.Step.prototype, {
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
