<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>MRP-Website Sync API Documentation</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body {
			padding-top: 2rem;
			padding-bottom: 2rem;
		}

		.endpoint {
			margin-bottom: 1.5rem;
			border-bottom: 1px solid #eee;
			padding-bottom: 1.5rem;
		}

		.method-get {
			color: #0d6efd;
			font-weight: bold;
		}

		.method-post {
			color: #198754;
			font-weight: bold;
		}

		.auth-required {
			color: #dc3545;
		}

		.auth-optional {
			color: #fd7e14;
		}

		.auth-none {
			color: #0dcaf0;
		}

		pre {
			background: #f8f9fa;
			padding: 1rem;
			border-radius: 0.25rem;
		}

		.try-it {
			margin-top: 1rem;
		}
	</style>
</head>

<body>
	<div class="container">
		<h1 class="mb-4">MRP-Website Sync API Documentation</h1>
		<div class="alert alert-info">
			<strong>Server time:</strong> <?php echo $current_time; ?>
		</div>

		<h2 class="mb-3">Endpoints</h2>

		<?php foreach ($endpoints as $endpoint): ?>
			<div class="endpoint">
				<h3><?php echo $endpoint['name']; ?>
					<span class="<?php echo $endpoint['method'] === 'GET' ? 'method-get' : 'method-post'; ?>">
						[<?php echo $endpoint['method']; ?>]
					</span>
				</h3>
				<div class="row">
					<div class="col-md-10">
						<strong>URL:</strong>
						<a href="<?php echo $endpoint['url']; ?>" target="_blank">
							<?php echo $endpoint['url']; ?>
						</a>
					</div>
					<div class="col-md-2">
						<strong>Auth:</strong>
						<?php
						$auth_class = 'auth-none';
						$auth_text = 'None';

						if ($endpoint['auth_required'] === true) {
							$auth_class = 'auth-required';
							$auth_text = 'Required';
						} else if (isset($endpoint['auth_required']) && $endpoint['auth_required'] === 'Optional') {
							$auth_class = 'auth-optional';
							$auth_text = 'Optional';
						}
						?>
						<span class="<?php echo $auth_class; ?>"><?php echo $auth_text; ?></span>
					</div>
				</div>

				<p class="mt-2"><?php echo $endpoint['description']; ?></p>

				<?php if (isset($endpoint['params']) && !empty($endpoint['params'])): ?>
					<div class="mt-2">
						<strong>Parameters:</strong>
						<ul>
							<?php foreach ($endpoint['params'] as $param => $desc): ?>
								<li><code><?php echo $param; ?></code>: <?php echo $desc; ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if (isset($endpoint['body_example'])): ?>
					<div class="mt-2">
						<strong>Example Request Body:</strong>
						<pre><code><?php echo $endpoint['body_example']; ?></code></pre>
					</div>
				<?php endif; ?>

				<?php if ($endpoint['method'] === 'GET' && $endpoint['auth_required'] === false): ?>
					<div class="try-it">
						<a href="<?php echo $endpoint['url']; ?>" class="btn btn-sm btn-primary" target="_blank">Try it</a>
					</div>
				<?php endif; ?>

				<?php if ($endpoint['auth_required']): ?>
					<div class="mt-2">
						<strong>Example with cURL:</strong>
						<pre><code>curl -X <?php echo $endpoint['method']; ?> \
     -H "Authorization: Basic <?php echo base64_encode('api:' . $api_key); ?>" \
<?php if ($endpoint['method'] === 'POST'): ?>
     -H "Content-Type: application/json" \
     -d '<?php echo $endpoint['body_example']; ?>' \
<?php endif; ?>
     <?php echo $endpoint['url']; ?></code></pre>

						<strong>Alternative with URL parameter:</strong>
						<pre><code>curl -X <?php echo $endpoint['method']; ?> "<?php echo $endpoint['url']; ?>?api_key=<?php echo $api_key; ?>"<?php if ($endpoint['method'] === 'POST'): ?> \
     -H "Content-Type: application/json" \
     -d '<?php echo $endpoint['body_example']; ?>'"
<?php endif; ?></code></pre>
					</div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<hr>
		<div class="mt-4">
			<h2>Authentication</h2>
			<p>Some endpoints require authentication. You can authenticate in one of these ways:</p>
			<ol>
				<li><strong>HTTP Basic Auth:</strong> Add an Authorization header with Basic authentication using username "api" and password as your API key.</li>
				<li><strong>URL Parameter:</strong> Add <code>?api_key=<?php echo $api_key; ?></code> to the URL.</li>
				<li><strong>POST Parameter:</strong> Include <code>api_key</code> in your POST data.</li>
			</ol>
		</div>

		<div class="mt-4">
			<h2>Testing the API</h2>
			<p>You can test the API directly from this page by clicking the "Try it" buttons for public endpoints, or by using tools like:</p>
			<ul>
				<li><a href="https://www.postman.com/" target="_blank">Postman</a></li>
				<li><a href="https://insomnia.rest/" target="_blank">Insomnia</a></li>
				<li><a href="https://www.thunderclient.com/" target="_blank">Thunder Client</a> (VS Code Extension)</li>
				<li>Command line with cURL (examples provided)</li>
			</ul>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>