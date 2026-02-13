# Integração de Dashboard

## Objetivo
Alinhar frontend e backend dos dashboards aos módulos do sistema, garantindo consistência de dados no fluxo:
`PPA -> Programa -> Acao -> Projeto -> Etapa -> Tarefa` (com Metas e Saude).

## Escopo
- Dashboards por perfil: `prefeito`, `secretario`, `coordenador`, `tecnico`, `controlador`.
- Contratos de API e mapeamento de dados no frontend.
- Filtros, agregações, rollups e indicadores de saúde.
- Qualidade: testes e critérios de aceite.

## Fora de Escopo
- Redesenho visual completo das telas.
- Mudanças de regra de negócio fora do dashboard.

## Fase 0 - Baseline e Contrato Canônico
- [ ] Definir payload canônico para cada perfil (`/v1/dashboard` e endpoints específicos).
- [ ] Documentar campos obrigatórios, opcionais e defaults por perfil.
- [ ] Mapear divergências atuais entre campos backend e consumo frontend.
- [ ] Definir política para fallback (quando pode existir, quando deve falhar).

## Fase 1 - Correções Estruturais no Backend
- [ ] Corrigir inconsistência de tabela PPA (`dotp_ppa` vs `dotp_ppas`) no dashboard.
- [ ] Fortalecer `checkModernTables()` para validar dependências reais por perfil.
- [ ] Normalizar retorno dos controladores para o contrato canônico.
- [ ] Revisar filtros de status usados no dashboard vs filtros aceitos em `/v1/projects`.
- [ ] Garantir invalidação de cache de dashboard nos pontos de mutação.

## Fase 2 - Integração do Frontend ao Contrato
- [x] Atualizar `DashboardPrefeito` para consumir payload real sem depender de mocks.
- [x] Atualizar `DashboardSecretario` para usar `programas/projetos_resumo/projetos_atencao`.
- [x] Atualizar `DashboardCoordenador` para usar `resumo/projetos/equipe`.
- [x] Atualizar `DashboardTecnico` para usar `resumo/tarefas_prioritarias/produtividade_30d`.
- [x] Atualizar `DashboardControlador` para usar `alertas_conformidade/panorama_secretarias`.
- [x] Remover fallback silencioso onde mascarar erro de integração.
- [x] Padronizar rotas/filtros de navegação dos cards para filtros válidos no backend.

## Fase 3 - Perfil e Roteamento
- [x] Unificar lógica de perfil entre frontend (`detectProfile`) e backend (`getDashboardType`).
- [x] Decidir fonte única de verdade para perfil efetivo no dashboard.
- [x] Validar comportamento de `/` e `/dashboard/*` para evitar rotas mortas/confusas.

## Fase 4 - Testes e Qualidade
- [x] Criar testes de contrato para cada dashboard controller (shape e tipos).
- [x] Criar testes de integração com fixture DB para dados reais por perfil.
- [x] Criar testes frontend de adaptação de payload para cada tela.
- [x] Criar smoke E2E por perfil (carrega sem erro e com dados coerentes).
  Entregue em `frontend/e2e/dashboard-smoke.spec.js` com Playwright.
  No WSL atual, execução fica `skipped` quando faltam libs nativas do Chromium (ex.: `libnspr4`).
- [ ] Garantir execução de testes em ambiente com conexão DB inicializada.
  Nota: os testes de integração de dashboard agora pulam com mensagem explícita quando o DB não está inicializado.

## Fase 5 - Go-Live Controlado
- [ ] Publicar com feature flag de contrato novo (se aplicável).
- [ ] Monitorar erros de API dashboard e tempo de resposta.
- [ ] Monitorar discrepâncias de métricas entre telas e banco.
- [ ] Desativar legado/fallback após estabilização.

## Critérios de Aceite
- [x] Cada perfil renderiza com dados reais do backend sem depender de mock local.
- [ ] Campos exibidos em tela correspondem ao contrato canônico definido.
- [ ] Filtros de clique dos cards retornam listas coerentes no módulo de destino.
- [ ] KPIs de saúde, execução e orçamento conferem com consultas de base.
- [ ] Testes de contrato, integração e smoke passam no pipeline.

## Riscos e Mitigações
- [ ] Risco: contratos quebrados por campos opcionais ausentes.
- [ ] Mitigação: adapter com defaults explícitos e validação de schema.
- [ ] Risco: fallback ocultar erro de integração.
- [ ] Mitigação: fallback controlado por flag e log obrigatório.
- [ ] Risco: cache servir dado obsoleto.
- [ ] Mitigação: invalidar cache em toda mutação relevante.

## Ordem Recomendada de Execução
- [ ] 1) Fase 0
- [ ] 2) Fase 1
- [x] 3) Fase 2
- [x] 4) Fase 3
- [ ] 5) Fase 4
- [ ] 6) Fase 5
