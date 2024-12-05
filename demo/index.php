<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation de l'API</title>
    <link type="text/css" rel="stylesheet" href="/swagger/swagger-ui.css?version=2023-03-03" />
</head>

<body>
    <div id="swagger-ui"></div>
    <script src="/swagger/swagger-ui-bundle.js?version=2023-03-03"></script>
    <script src="/swagger/swagger-ui-standalone-preset.js?version=2023-03-03"></script>
    <script>
        const DisableTryItOutPlugin = function() {
            return {
                statePlugins: {
                    spec: {
                        wrapSelectors: {
                            allowTryItOutFor: () => () => false,
                        },
                    },
                },
            };
        };

        window.ui = SwaggerUIBundle({
            url: '/api.php',
            dom_id: "#swagger-ui",
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset,
            ],
            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl,
                DisableTryItOutPlugin
            ],
            layout: "StandaloneLayout",
            tagsSorter(a, b) {
                if (a === "default") return -1;
                if (b === "default") return 1;
                return a.localeCompare(b);
            },
        });
    </script>
</body>

</html>
