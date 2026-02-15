setup development container
```bash
docker build --target dev_app -t cardeco:dev .
docker compose -p cardeco_dev down
docker compose --file compose.yml -p cardeco_dev up -d
```