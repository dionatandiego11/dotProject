# Troubleshooting - dotProject Prefeituras

## API nao responde
- Verifique se o Docker esta rodando.
- A porta 8088 precisa estar livre.
- Logs:
  - `wsl docker-compose logs -f`
  - ou `wsl docker logs dotproject_web -f`

## Frontend nao carrega dados
- Confirme que a API esta acessivel em `http://localhost:8088/api/v1`.
- Verifique se o token JWT esta sendo enviado.
- Veja o console do navegador para erros de CORS ou 401.

## Erro de conexao com banco
- Verifique o container MariaDB (status healthy).
- Confirme variaveis de ambiente em `.env`.

## Erro 500 nos dashboards
- Indica divergencia de schema entre tabelas esperadas e existentes.
- Acompanhar decisoes em `.agent/forum_agentes.md`.

## WSL/Docker
- Este ambiente exige comandos via WSL.
- Para comandos do frontend:
  - `wsl bash -c "cd /mnt/c/Users/dionatan.resende/Downloads/dotProject/frontend && npm run dev"`
