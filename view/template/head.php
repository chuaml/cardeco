<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<link rel="stylesheet" href="css/style.css">
<title>Cardeco</title>



<?php if (isset($_isProduction) === true && $_isProduction !== true) { ?>
	<script>
		window.dataLayer = window.dataLayer || [];

		function gtag() {
			console.log(arguments);
			dataLayer.push(arguments);
		}
		gtag('js', new Date());
	</script>

	<div style="padding: 0.5em;background-color: green;color:white;">test server: dev</div>
<?php } else { ?>

	<!-- Google tag (gtag.js) -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=G-139719LGJQ"></script>
	<script>
		window.dataLayer = window.dataLayer || [];

		function gtag() {
			dataLayer.push(arguments);
		}
		gtag('js', new Date());

		gtag('config', 'G-139719LGJQ');
	</script>


	<link rel="shortcut icon" href="view/icon.png" type="image/png" />


<?php } ?>

<script>
	window.app = {}; // global placeholder
	window.app.hasError = '<?= error_get_last() === null ?>' === '1';
</script>

<!-- smooth page loading transition -->
<style>
	#page-loader {
		z-index: 1000;
		position: fixed;
		height: 100%;
		width: 100%;
		padding: 0;
		margin: 0;
		top: 0;
		left: 0;
		justify-content: center;
		align-items: center;
		font-size: 16px;
		cursor: progress;

		background-color: hsl(0deg 0% 50% / 0%);
	}

	#page-loader * {
		opacity: 0;
	}

	#page-loader.loading {
		transition: .5s ease;
		transition-delay: .5s;
		background-color: hsl(0deg 0% 50% / 25%);
		display: flex;
	}

	#page-loader.loading * {
		opacity: 1;
		transition-delay: .75s;
	}
</style>
<div id="page-loader" style="display: none;">
	<p><i>loading...</i></p>
</div>
<script>
	// toggle show loading
	window.addEventListener('beforeunload', e => {
		console.log(e.type);
		requestAnimationFrame(_ => {
			const loader = document.getElementById('page-loader');
			loader.style['display'] = 'flex';
			setTimeout(() => {
				loader.classList.toggle('loading');
			}, 50);
		});
	});

	// hide loading when returning current loading page
	window.addEventListener('pageshow', function(e) {
		console.log(e.type);
		requestAnimationFrame(_ => {
			const loader = document.getElementById('page-loader');
			loader.style['display'] = 'none';
			setTimeout(() => {
				loader.classList.remove('loading');
			}, 50);
		});
	});
</script>
<!-- smooth page loading transition -->

<!-- custom table sorter -->
<link rel="stylesheet" href="js/table-sorter/table-sorter.css">
<script src="js/table-sorter/table-sorter-init.js"></script>

<!-- custom ajax form handling [cd-ajax] -->
<script>
	// override form submission, listen network response of form submit and retrigger customer event of resposne result
	document.body.addEventListener('submit', async e => {
		// exclude form with file
		if (e.target.matches('form[cd-ajax]') === false) return;
		e.preventDefault();
		const form = e.target;
		const formData = new FormData(form);

		return await fetch(form.action, {
				method: form.method,
				body: formData
			})
			.then(response => {
				// console.log({
				// 	form,
				// 	response
				// });
				if (response.ok) {
					form.dispatchEvent(new CustomEvent('submitted', {
						bubbles: true,
						detail: response
					}));
					console.log('form submitted success ' + response.status, {
						form,
						response
					});
				} else {
					console.error('form submitted but server failed ' + response.status, {
						form,
						response
					});
				}
			})
			.catch(error => {
				form.dispatchEvent(new CustomEvent('not-submitted', {
					bubbles: true
				}));
				return false;
			});
	});
	document.body.addEventListener('submit', e => {
		requestAnimationFrame(_ => {
			document.body.classList.add('submitting-form');
		});
	});
	document.body.addEventListener('submitted', e => {
		requestAnimationFrame(_ => {
			document.body.classList.remove('submitting-form');
		});
	});
	document.body.addEventListener('not-submitted', e => {
		requestAnimationFrame(_ => {
			document.body.classList.remove('submitting-form');
		});
	});
</script>


<script src="js/vendor/quicklink.umd.js" async></script>
<script>
	window.addEventListener('load', function(e) {
		quicklink.listen({
			delay: 250,
			limit: 16,
			throttle: 4,
			origins: [
				location.origin // prefetch self origin only
			],
			el: document.querySelector('body > nav'), // observe and prefetech only links in this element
		});
	});
</script>


<script>
	window.addEventListener('DOMContentLoaded', function(ev) {

		const doSave = _ => {
			const form = document.body.querySelector('form[method=post]');
			console.log(form);
			if (form !== null) form.requestSubmit();
		};
		document.addEventListener('keyup', function(e) {
			if (e.ctrlKey === false) return;
			if (e.code !== 'KeyS') return;
			if (e.isTrusted)
				setTimeout(doSave, 0);
		});
		document.addEventListener('keydown', function(e) {
			if (e.ctrlKey === false) return;
			if (e.code !== 'KeyS') return;
			e.preventDefault();
		});

	});
</script>