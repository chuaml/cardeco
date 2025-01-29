@REM # build image locally
@REM ## build staging image
docker build --target production_app -t cardeco:production .

@REM # deploy staging stack
docker compose -p cardeco_production down
docker compose --file compose.production.yml -p cardeco_production up -d
