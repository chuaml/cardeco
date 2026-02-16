# setup development container

use vscode with `ms-vscode-remote.remote-containers` extension, the `./.devcontainer/devcontainer.json` combine and bundle all necessary configurations to run and setup a dev container environment

otherwise manually spin up a dev container
```bash
docker build --target dev_app -t cardeco:dev .
docker compose -p cardeco_dev down
docker compose --file compose.yml -p cardeco_dev up --watch
```


to stop container and delete everything --including volumn---
```bash
docker compose -p cardeco_dev down
```