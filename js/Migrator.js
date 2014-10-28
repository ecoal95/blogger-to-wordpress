window.Migrator = window.Migrator || {};

Migrator.Utils = {
	extend: function(obj1, obj2) {
		var p;

		for ( p in obj2 ) {
			if ( obj2.hasOwnProperty(p) ) {
				obj1[p] = obj2[p];
			}
		}
	},

	/**
	 * Send an ajax request to migrator url
	 * @param {String} action
	 * @param {Object} data
	 * @param {Migrator.App} app
	 * @param {Migrator.Step} step
	 */
	action: function(action, data, app, step) {
		var req = new XMLHttpRequest();

		data = data || null;

		req.open('POST', window.MIGRATOR_URL, true);

		req.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

		req.onreadystatechange = function() {
			var response;

			if ( req.readyState !== 4 ) {
				return;
			}

			if ( req.status === 200 ) {
				response = JSON.parse(req.responseText);


				if ( response.messages ) {
					response.messages.forEach(function(message) {
						app.showMessage(message.message, message.type);
					});
				}

				if ( response.fatal ) {
					step.error(step.description + ' failed. See errors.')
				} else {
					step.resolve(response);
				}
			} else {
				step.error(step.description + ' failed by an unknown error.');
			}
		}

		req.send('action=' + action + '&data=' + encodeURIComponent(JSON.stringify(data)));
	}
}
