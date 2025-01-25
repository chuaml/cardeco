@REM start development
docker compose --profile dev down && docker compose --profile dev up -d --build


@REM build image
@REM build staging image
docker build --target production_app -t cardeco:staging .

@REM build production image
docker build --target production_app -t cardeco:production .


@REM deployment
@REM stop and remove all existing deployed container stacks
for /f "tokens=*" %i in ('docker stack ls --format "{{.Name}}"') do docker stack rm %i


@REM deploy stacking stack
docker stack rm cardeco_staging && docker stack deploy --compose-file=compose.staging.yml cardeco_staging --detach=false

@REM deploy production stack
docker stack rm cardeco_production && docker stack deploy --compose-file=compose.production.yml cardeco_production --detach=false

docker stack ls
docker service ls
docker ps
docker volume ls