Place brand assets here (transparent background recommended):
- esticly-logo.png          (full wordmark logo)
- esticly-icon.png          (icon mark only)
- favicon.ico
- favicon-32x32.png
- favicon-16x16.png
- apple-touch-icon.png      (180x180)

After replacing files in Docker setup without code bind mount:
1) docker compose up -d --build api
2) docker exec beautycrm_api php artisan optimize:clear
