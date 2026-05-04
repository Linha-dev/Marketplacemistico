# PR Rules do Projeto

## Objetivo
Definir um fluxo unico para submissao de PR, com seguranca para PROD e previsibilidade para DEV.

## Branches oficiais
- `main`: producao (estavel).
- `develop`: integracao de desenvolvimento.
- `autor/cCC-sSS-nome-curto`: feature por sprint.
- `autor/hotfix-YYYYMMDD-descricao`: correcao urgente de producao.

## Regra de naming (obrigatoria)

### Sprint
- `autor/cCC-sSS-nome-curto`
- `autor` = nick do desenvolvedor/IA.
- `CC` = ciclo com 2 digitos.
- `SS` = sprint com 2 digitos.
- `nome-curto` = resumo tecnico da entrega.

Exemplos:
- `codex/c02-s26-motor-custo-total`
- `VictorDG00/c02-s27-segregacao-quote`

### Hotfix
- `autor/hotfix-YYYYMMDD-descricao`
- Exemplo: `VictorDG00/hotfix-20260318-falha-webhook`

## Regras obrigatorias
- Nao fazer push direto em `main`.
- Nao fazer push direto em `develop`.
- NUNCA fazer merge de feature direto em `main`.
- Toda mudanca entra por Pull Request.

## Politica de merge por branch
- `main` so aceita merge manual por PR apos validacao completa.
- `develop` so aceita merge por PR com checks obrigatorios aprovados.
- Branch de sprint sempre abre PR para `develop`.
- `develop -> main` e sempre manual.

## Fluxo padrao
1. Criar branch de trabalho a partir de `develop` seguindo `autor/cCC-sSS-nome-curto`.
2. Implementar mudanca e abrir PR para `develop`.
3. Corrigir ate CI ficar verde.
4. Em release, abrir PR da `develop` para `main`.
5. PR `develop -> main` exige aprovacao manual.

## Fluxo de hotfix
1. Criar `autor/hotfix-*` a partir de `main`.
2. Corrigir e validar rapidamente.
3. PR para `main`.
4. Apos merge, PR de sincronizacao `main -> develop`.

## Requisitos minimos para aprovar PR
- Descricao clara: objetivo, escopo e impacto.
- Lista de testes executados.
- Evidencia de validacao (saida de testes, screenshot ou logs quando aplicavel).
- Sem quebra de contrato publico da API sem justificativa.
- Sem segredos no codigo.

## Checks obrigatorios (CI)
- `Branch Policy`: obrigatorio.
- `lint`: obrigatorio.
- `unit-tests`: obrigatorio.
- `integration-tests`: obrigatorio.
- `e2e-tests`: obrigatorio para PR `develop -> main`.

## Protecao obrigatoria no GitHub
Aplicar em `main` e `develop`:
- `Require a pull request before merging`.
- `Require status checks to pass before merging`.
- `Do not allow bypassing the above settings`.
- Bloquear push direto.

## Convencao de merge
- `Squash merge` para feature PRs em `develop`.
- `Merge commit` para `develop -> main`.
- `Merge commit` para `main -> develop` apos hotfix.

## Ambientes e variaveis
- `main` = Production.
- `develop` e branches de sprint/hotfix = Preview.
- Variaveis separadas por ambiente (`Preview` e `Production`).

## Rollback
- Rollback de producao por revert de commit ou rollback de deploy.
- Apos rollback, manter sincronia entre `main` e `develop` via PR.
