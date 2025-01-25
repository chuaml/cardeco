@REM build image locally
docker swarm init

@REM build production image
docker build --target production_app -t cardeco:production .

@REM deployment
@REM deploy production stack
docker stack rm cardeco_production && docker stack deploy --compose-file=compose.production.yml cardeco_production -d
