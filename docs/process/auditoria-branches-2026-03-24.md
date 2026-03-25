# Auditoria de Branches (2026-03-24)

## Resultado da auditoria local
No checkout atual foi encontrada apenas uma branch local:
- `work`

Nao ha remotes configurados neste ambiente no momento.

## Impacto
Sem remotes e sem outras branches locais/remotas visiveis, nao e possivel abrir PRs reais para `develop` a partir deste ambiente.

## Sao importantes essas branches alem de main/develop?
Sim, em geral sao importantes quando seguem o fluxo definido pelo projeto:
- branches de sprint (`autor/cCC-sSS-*`) carregam entregas incrementais;
- hotfixes (`autor/hotfix-*`) tratam correcao urgente;
- ambas devem convergir por PR para `develop` (ou `main` no fluxo de hotfix).

## Como proceder para abrir PRs de todas para develop
1. Configurar remote do repositorio (GitHub/Git provider).
2. Sincronizar refs remotas (`git fetch --all --prune`).
3. Listar branches candidatas e filtrar:
   - excluir `main`, `develop` e branch atual.
4. Para cada branch candidata, abrir PR para `develop` com:
   - resumo da entrega,
   - riscos,
   - testes executados.

## Recomendacao imediata
Assim que o remote estiver configurado, executar um lote de PRs por ordem de sprint (mais antiga -> mais nova) para reduzir conflito e facilitar review.
