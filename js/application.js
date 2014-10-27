window.app = new Migrator.App();

app.registerStep('Get blogger data', function(step) {
	var request = new XMLHttpRequest();

	request.open('GET', window.BLOGGER_DATA_SRC, true);
	request.onreadystatechange = function(){
		var posts;
		if ( request.readyState !== 4 ) {
			return;
		}

		if ( request.status === 200 ) {
			posts = (new BloggerParser(request.responseXML.contentDocument)).parse();
			step.resolve(posts);
		} else {
			step.error('Couldn\'t get blogger data, are you sure that ' + window.BLOGGER_DATA_SRC + ' exists?')
		}
	}

	request.send();
});

app.registerStep('Insert data into wordpress DB', function(step, posts) {
	var insertionApp = new Migrator.App();

	insertionApp.registerStep('Init migration', function (s) {
		Migrator.Utils.action('init_migration', null, insertionApp, s);
	});

	posts.forEach(function(post) {
		insertionApp.registerStep('Insert post "' + post.title + '"', function(s) {
			// For now we do a massive step including all the comments
			Migrator.Utils.action('insert_post', post, insertionApp, s);
		});
	});

	insertionApp.registerStep('End migration', function(s) {
		Migrator.Utils.action('end_migration', null, insertionApp, s);
	});

	insertionApp.on('complete', function() {
		step.resolve();
	});

	insertionApp.on('error', function() {
		step.error('Migration failed, see errors');
	});
});


app.on('complete', function() {
	app.showMessage('Migration completed!');
});

app.on('step.change', function(step) {
	Migrator.Utils.defaultProgressUpdater(document.getElementById('main-progress'), app, step);
});

app.run();
