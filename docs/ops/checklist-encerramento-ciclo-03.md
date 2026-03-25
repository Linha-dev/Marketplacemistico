# Checklist de Encerramento — Ciclo 03

Data de referencia: 2026-03-24

## 1) Status macro do ciclo
- [x] Sprint 1 iniciada (users/sellers profile)
- [x] Sprint 2 iniciada (products/sellers listing)
- [x] Sprint 3 iniciada (orders)
- [x] Sprint 4 iniciada (payments)
- [x] Sprint 5 iniciada (webhooks)
- [x] Sprint 6 iniciada (finance + observability ops)
- [x] Sprint 7 iniciada (hardening/documentacao/playbook)

Observacao: os itens acima refletem o estado registrado em `ciclo-03.md`.

## 2) Checklist tecnico de encerramento
### Qualidade de codigo
- [x] Backend modularizado por dominio (`controller/service/repository/schemas`).
- [x] Wrappers de rota preservando middlewares e contratos de API.
- [x] Suite de testes automatizados passando localmente (`npm test -- --runInBand`).
- [x] Lint executado sem erros (warnings remanescentes apenas em scripts de carga/smoke).

### Governanca e padrao
- [x] Documento de arquitetura modular publicado.
- [x] Playbook de novos endpoints publicado.
- [x] Progresso do ciclo atualizado no plano (`ciclo-03.md`).

### Operacao
- [ ] Smoke em ambiente real executado e anexado ao ciclo.
- [ ] Load em ambiente real executado e anexado ao ciclo.
- [ ] Evidencias de operacao (prints/logs) centralizadas para handoff.

## 3) Pode seguir para o Ciclo 04?
**Sim — pode iniciar o Ciclo 04 agora**, com a seguinte leitura:

- **Pronto no codigo (time de engenharia):** arquitetura modular e base de testes.
- **Pendente para go-live (operacao/infra):** configuracao de ambiente, segredos, deploy e validacoes em staging/producao.

## 4) O que e "todo do seu lado" no Ciclo 04?
Nao e 100% do seu lado, mas existe uma parte importante de decisao/operacao que depende de voce.

### Seu lado (produto/operacao/infra)
- [ ] Definir oficialmente stack e contas (Neon/Fly/Cloudflare ou alternativa).
- [ ] Provisionar ambientes (`staging` e `producao`) e DNS.
- [ ] Cadastrar segredos reais por ambiente (`DATABASE_URL`, `JWT_SECRET`, `EFI_*`, `MELHOR_ENVIO_*`, segredos ops).
- [ ] Aprovar politica de rollout (ondas, rollback, criterios de corte).
- [ ] Validar e assinar checklist final de go-live.

### Nosso lado (engenharia)
- [ ] Automatizar pipeline de deploy conforme politica definida.
- [ ] Executar smoke/load e anexar evidencias tecnicas.
- [ ] Ajustar observabilidade/alertas para producao.
- [ ] Suportar runbook e game day de incidente.

## 5) Gate objetivo para "encerrar ciclo 03 e abrir ciclo 04"
Considere o ciclo 03 encerrado quando TODOS abaixo estiverem OK:
- [x] Arquitetura modular aplicada nos dominios planejados.
- [x] Testes automatizados verdes.
- [x] Documentacao de arquitetura e playbook publicados.
- [ ] Smoke/load em ambiente real concluido.
- [ ] Checklist de go-live preenchido com responsaveis.

Quando os dois ultimos itens forem concluídos, o ciclo 03 pode ser considerado fechado formalmente e o ciclo 04 entra em execucao plena.
