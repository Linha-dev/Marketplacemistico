# Branching Strategy

## Objetivo
Definir o fluxo oficial de desenvolvimento para separar ambiente de producao e ambiente de desenvolvimento.

## Ambientes por branch
- `main`: ambiente de producao.
- `develop`: ambiente de desenvolvimento/integracao.

## Regras operacionais
1. Toda sprint nasce de `develop`.
2. Toda branch de sprint deve seguir o padrao: `sprint/NN-descricao`.
3. Cada sprint abre PR para `develop`.
4. Merge em `develop` somente com CI verde.
5. `main` recebe mudancas apenas via PR manual de `develop` para `main`.
6. Nenhuma sprint abre PR direto para `main`.

## Fluxo da sprint
```bash
git checkout develop
git pull origin develop
git checkout -b sprint/13-alertas-runbook

# desenvolvimento...
git add .
git commit -m "feat: entrega sprint 13"
git push -u origin sprint/13-alertas-runbook
```

Depois:
1. Abrir PR `sprint/...` -> `develop`.
2. Aguardar CI (lint + testes + politica de branch).
3. Corrigir o que falhar e atualizar PR.
4. Fazer merge apenas com CI aprovado.

## Fluxo de release para producao
1. Abrir PR `develop` -> `main`.
2. Revisao e aprovacao manual no GitHub.
3. Merge manual.

## Protecoes recomendadas no GitHub
Configurar em `Settings > Branches`:

### `main`
- Require a pull request before merging.
- Require approvals (minimo 1).
- Require status checks to pass before merging.
  - `Branch Policy`
  - `Lint and Tests`
- Restrict pushes diretos.

### `develop`
- Require status checks to pass before merging.
  - `Branch Policy`
  - `Lint and Tests`
- Opcional: bloquear merge com conversa nao resolvida.
