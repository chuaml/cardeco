@REM build image locally
docker swarm init

@REM build staging image
docker build --target production_app -t cardeco:staging .

@REM deploy staging stack
docker stack rm cardeco_staging 
docker stack deploy --compose-file=compose.staging.yml cardeco_staging --detach=false
