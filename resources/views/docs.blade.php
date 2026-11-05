<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Inventory API Docs</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
<script>
    window.ui = SwaggerUIBundle({
        url: '{{ asset('openapi.yaml') }}',
        dom_id: '#swagger-ui',
        persistAuthorization: true,
    });
</script>
</body>
</html>

