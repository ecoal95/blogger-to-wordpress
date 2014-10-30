var app = window.app = new Migrator.App();

app.registerStep('Parse Blogger data', function(step, posts) {
	app.registerStep('Init migration', function (s) {
		Migrator.Utils.action('init_migration', null, app, s);
	});

	try {
		posts.forEach(function(post) {
			app.registerStep('Insert post "' + post.title + '"', function(s) {
				// For now we do a massive step including all the comments
				Migrator.Utils.action('insert_post', post, app, s);
			});
		});
	} catch (ex) {
		step.error('Posts weren\'t read correctly');
		return;
	}

	app.registerStep('End migration', function(s) {
		Migrator.Utils.action('finish_migration', null, app, s);
	});

	step.resolve();
});


(function() {
	var container = document.getElementById('step'),
		elements;
	elements = {
		current: container.querySelector('.step__current'),
		stepCount: container.querySelector('.step__count'),
		description: container.querySelector('.step__description'),
		bar: container.querySelector('.step__bar')
	};

	app.on('complete', function() {
		app.showMessage('Migration completed!');
		elements.bar.value = 100;
	});

	app.on('step.change', function(step) {
		var current = app.steps.indexOf(step) + 1;

		elements.current.innerHTML = current;
		elements.stepCount.innerHTML = app.steps.length;
		elements.description.innerHTML = step.description;
		elements.bar.value = (current - 1) / app.steps.length * 100;
	});
} ());

app.on('message', (function() {
	var log = document.getElementById('log');

	return function(message, type) {
		var msg = document.createElement('div');

		msg.className = 'message message--' + type;
		msg.textContent = message;
		log.appendChild(msg);
	}
} ()));


document.getElementById('start-button').addEventListener('click', function(e) {
	var file = document.getElementById('file').files[0],
		reader;

	if ( ! file ) {
		return;
	}

	document.getElementById('start').style.display = 'none';
	document.getElementById('import').style.display = '';

	reader = new FileReader();

	reader.onload = function() {
		var result = reader.result,
			parser = new DOMParser(),
			doc = parser.parseFromString(result, "application/xml"),
			posts = (new BloggerParser(doc)).parse();

		app.run(posts);
	}

	reader.readAsText(file);

}, false);
