@REM # build image locally
@REM ## build staging image
docker build --target production_app -t cardeco:staging .

@REM # deploy staging stack
docker compose -p cardeco_staging down
docker compose --file compose.staging.yml -p cardeco_staging up -d
