# Branching Strategy e PR Rules

## Objetivo
Padronizar o fluxo de desenvolvimento para proteger producao e dar previsibilidade ao ciclo de sprints.

## Branches oficiais
- `main`: producao (estavel).
- `develop`: integracao de desenvolvimento.

## Convencao de branch

### Sprint
- Padrao obrigatorio: `autor/cCC-sSS-descricao`
- Onde:
  - `autor`: nick de quem implementou.
  - `CC`: ciclo com 2 digitos.
  - `SS`: sprint com 2 digitos.
  - `descricao`: resumo tecnico curto em minusculo e com hifen.

Exemplos:
- `codex/c02-s26-motor-custo-total`
- `VictorDG00/c02-s27-segregacao-quote`

### Hotfix
- Padrao obrigatorio: `autor/hotfix-YYYYMMDD-descricao`
- Exemplo:
  - `VictorDG00/hotfix-20260318-falha-webhook-efi`

## Regras obrigatorias
1. Nao fazer push direto em `main`.
2. Nao fazer push direto em `develop`.
3. Toda mudanca entra por Pull Request.
4. Sprint branch abre PR somente para `develop`.
5. `main` recebe mudanca apenas de `develop` (release) ou hotfix.

## Politica de merge
- `develop`: merge por PR com CI verde.
- `main`: merge manual por PR aprovado + CI verde.
- Hotfix em `main`: apos merge, abrir PR `main -> develop` para sincronizar.

## Fluxo de sprint
1. Criar branch a partir de `develop`.
2. Implementar, commitar e abrir PR para `develop`.
3. Corrigir o que falhar no CI.
4. Merge apenas com checks obrigatorios aprovados.

## Fluxo de release
1. Abrir PR `develop -> main`.
2. Validar checks obrigatorios.
3. Aprovar manualmente e fazer merge.

## Fluxo de hotfix
1. Criar `autor/hotfix-YYYYMMDD-descricao` a partir de `main`.
2. Corrigir e validar.
3. Abrir PR para `main`.
4. Apos merge, abrir PR `main -> develop`.

## Checks obrigatorios (CI)
- `Branch Policy` (valida origem/destino e padrao da branch)
- `lint`
- `unit-tests`
- `integration-tests`
- `e2e-tests` para PR `develop -> main`

## Protecao recomendada no GitHub
Configurar em `Settings > Branches`:

### `main`
- Require a pull request before merging.
- Require approvals (minimo 1).
- Require status checks to pass before merging:
  - `Branch Policy`
  - `lint`
  - `unit-tests`
  - `integration-tests`
  - `e2e-tests`
- Do not allow bypassing the above settings.
- Restrict pushes diretos.

### `develop`
- Require a pull request before merging.
- Require status checks to pass before merging:
  - `Branch Policy`
  - `lint`
  - `unit-tests`
  - `integration-tests`
- Do not allow bypassing the above settings.

## Vercel
- `main` = Production Branch.
- `develop` e branches de sprint/hotfix = Preview.
- Variaveis separadas por `Production` e `Preview`.

## Script utilitario
Para criar branch padronizada, usar:
```powershell
./scripts/new-sprint-branch.ps1 -Type sprint -AuthorNick VictorDG00 -CycleNumber 2 -SprintNumber 19 -Description "ajuste-carrinho"
```
