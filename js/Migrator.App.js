(function() {
	var _slice = Array.prototype.slice,
		onStepResolve,
		onStepError;

	onStepResolve = function() {
		var next = this.nextStep();

		if ( ! next ) {
			this.trigger('complete');
		}

		this.trigger('step.change', next);
		next.run(_slice.call(arguments));
	}

	onStepError = function(error) {
		this.showError(error);
	}

	Migrator.App = function() {}

	/** Extend event listener */
	Migrator.App.prototype = new Migrator.EventListener();

	Migrator.Utils.extend(Migrator.App.prototype, {
		currentStep: 0,
		steps: [],

		/**
		 * Register a step
		 *
		 * @param {Function} cb
		 */
		registerStep: function(description, cb) {
			var step = new Migrator.Step(description, cb);

			step.on('resolve', onStepResolve.bind(this) );
			step.on('error', onStepError.bind(this));

			this.steps.push(step);
		},

		/**
		 * Get the next unexecuted step
		 */
		nextStep: function() {
			var i = 0,
				len = this.steps.length;

			for ( ; i < len; i++ ) {
				if ( ! this.steps[i].done ) {
					return this.steps[i];
				}
			}

			return null;
		},

		/**
		 * Show a message
		 *
		 * @param {String} msg
		 * @param {String} type
		 */
		showMessage: function(msg, type) {
			type = type || 'success';
			console.log('Migrator::App [' + type + ']: ' + msg);
			this.trigger('message', msg, type);
		},

		/**
		 * Show an error message
		 *
		 * @param {String} msg
		 */
		showError: function(msg) {
			return this.showMessage(msg, 'error');
		},

		run: function() {
			this.trigger('step.change', this.steps[0]);
			this.steps[0].run();
		}
	});

} ())
