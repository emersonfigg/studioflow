# StudioFlow — Etapa 3: contratação pública e checkout recorrente Asaas

## Resultado e limites

Implementação local para homologação isolada. Produção permanece bloqueada por ambiente, além das flags desligadas por padrão. Não foi aplicada migration à Railway, ao banco local original ou a qualquer banco produtivo. Nenhuma cobrança real foi executada. Não foram usados dados ou credenciais de produção.

O usuário confirmou não possuir credenciais Sandbox autorizadas. Por isso **não houve homologação real no Asaas**. Os testes usam o adapter concreto com HTTP simulado e bloqueio de requisições inesperadas. A compatibilidade externa ainda exige os passos do [guia da loja](asaas-merchant-setup.md).

PostgreSQL de teste: **18.6**, em `127.0.0.1:55438`, usuário exclusivo `studioflow_test`, bases novas com prefixo `studioflow_finance_test_`. A major 18 coincide com a produção informada; a versão exata produtiva continua desconhecida. Não se afirma equivalência de patch ou da configuração SSL da Railway.

## Fluxo entregue

1. `/agendar/{empresa}` mostra **CLUBE DE ASSINATURAS** antes do formulário de serviços avulsos. Cards usam o tema existente, largura adaptável, preço, periodicidade, serviços, quantidades, benefícios, condições e **Assinar agora**. O agendamento avulso continua disponível.
2. Só aparecem planos ativos, de valor positivo, com renovação automática, condições preenchidas, periodicidade suportada e conta Asaas Sandbox disponível. Plano inativo, duração personalizada incompatível, empresa inativa, serviços de outra empresa ou conta não verificada bloqueiam a oferta.
3. A escolha conduz à página de contratação. É enviada uma confirmação por e-mail; o código vale 10 minutos, exige a mesma sessão, admite até cinco tentativas e é consumido uma única vez. Rotas têm limites de requisições e proteção CSRF. A comprovação financeira de identidade dura 30 minutos e não reutiliza a sessão de identificação do agendamento.
4. A pessoa confirma nome e contato, revê preço, calendário e condições e aceita explicitamente. Telefone nunca identifica uma pessoa nem revela contratos existentes. Um cliente existente só pode ser vinculado por e-mail cuja posse foi comprovada, na mesma empresa; correspondência ambígua impede concluir.
5. O backend bloqueia a identidade e registra uma intenção única por identidade/plano, um novo contrato operacional `pending` e um snapshot comercial com termos versionados, serviços e benefícios. Valores enviados pelo navegador não determinam preço. Alteração de preço/condições exige nova revisão.
6. Uma marca durável concede uma única tentativa de criação. O adapter cria um cliente remoto dedicado à intenção, guarda seu ID e cria o checkout `CREDIT_CARD` + `RECURRENT`, exclusivamente com a credencial verificada da conta daquele estabelecimento no Sandbox. Não existe split, comissão, subconta automática ou fallback.
7. O ID do checkout é persistido antes do redirecionamento para um host Sandbox fixo. URL arbitrária retornada pelo gateway não é usada. O Asaas coleta os dados do cartão na página hospedada; o StudioFlow não coleta cartão.
8. Retornos de sucesso/cancelamento/expiração são navegação e consulta restrita à identidade comprovada. Nenhum callback confirma pagamento ou ativa assinatura.
9. Webhooks autenticados entram na infraestrutura da Etapa 2. Eventos de checkout alteram somente a situação da jornada. Eventos de assinatura exigem consulta remota; eventos financeiros exigem consulta à cobrança e à assinatura.
10. O vínculo inicial exige conta/cliente dedicado/plano/valor/periodicidade/modalidade corretos e uma cobrança retornada pela consulta oficial filtrada por **checkoutSession** e assinatura. Preço, telefone ou nome, isoladamente, não provam vínculo. Sem evidência, o evento permanece em quarentena.
11. Pagamentos confirmados/recebidos produzem faturas e projeção financeira, com cobertura contínua por meses de calendário e ajuste sem desvio no fim do mês. Renovação sem fatura local cria a fatura correspondente. Eventos antigos não rebaixam pagamento confirmado; duplicados não repetem lançamentos.
12. O scheduler existente também agenda consulta dos checkouts conhecidos ainda sem assinatura correlacionada. Assim, uma confirmação financeira pode ser recuperada sem webhook inicial. Uma vez correlacionada, a conciliação recorrente existente assume o acompanhamento. Isso nunca repete chamadas de criação.

## Benefícios e cancelamento

`financial_status = active` indica evidência financeira válida; **não altera** o `status` operacional para `active`. Os benefícios continuam indisponíveis para esses contratos até a Etapa 4 autorizada. Contratos legados não são adotados, ativados ou suspensos automaticamente. O fluxo antigo de retomar/pausar fica bloqueado apenas para os novos contratos públicos, evitando liberar benefícios pelo caminho administrativo anterior.

O administrador da empresa pode usar **Cancelar renovação** após a correlação da assinatura. A operação consulta o estado remoto e solicita `status=INACTIVE` com `updatePendingPayments=false`, sem DELETE. Só registra o sucesso após confirmar o estado remoto. Preserva faturas e cobertura paga; não confunde interrupção da renovação com extinção imediata do direito já pago. Não existe reativação automática. Antes da correlação, é necessário conferir o checkout no Asaas; o sistema não anuncia cancelamento local como se fosse remoto.

As ações anteriores dos contratos legados permanecem preservadas. O adapter V1, Mercado Pago e Galaxy Pay, PDV, caixa, relatórios e gateway dos agendamentos não foram substituídos.

## Conta, credenciais e falhas

- A seleção exige exatamente uma conta Asaas Sandbox da própria empresa marcada para assinaturas. Não escolhe outra conta quando a selecionada está indisponível.
- Exige ID externo verificado, conta ativa/conectada, aprovação, credencial não vazia e validade. Uma impressão criptográfica vincula empresa, integração, ambiente, ID externo e chave verificada. Trocar a chave bloqueia o checkout até nova verificação.
- O comando de verificação consulta `myAccount/status` e `wallets`, compara Account ID e Wallet ID e exige aprovação geral. Antes das escritas remotas, o adapter consulta novamente a identidade/aprovação da conta. Nenhuma senha bancária é solicitada.
- Timeout, resposta incerta ou falha de criação deixam a intenção em `creation_unknown`. Não há retry de POST nem nova intenção por repetir o formulário, abrir outra sessão e confirmar o mesmo e-mail.
- A criação de cliente e a criação de checkout também possuem marcas atômicas independentes, evitando reenvio direto do adapter. Exceções persistidas são códigos seguros, sem chave ou corpo remoto com dados pessoais.
- Checkout cancelado/expirado/abandonado e cartão recusado não ativam benefícios. A repetição da contratação mantém a intenção original. A reabertura automática após encerramento foi deliberadamente bloqueada: exige primeiro confirmar ausência de obrigação remota.
- Um checkout criado durante um timeout pode ser recuperado por evento autenticado com referência opaca e cliente dedicado coincidentes. Se essa evidência não vier, exige investigação manual; não há recriação às cegas.
- Eventos de checkout fora de ordem não reabrem jornada encerrada nem rebaixam jornada paga. `CHECKOUT_PAID` sozinho não cria fatura paga.
- Fila indisponível não perde o evento persistido; continua valendo a recuperação da Etapa 2. Rede indisponível pode ser repetida apenas nas consultas financeiras idempotentes, com limites e mensagens seguras.

## Migration

`database/migrations/2026_10_23_000001_add_public_recurring_enrollment.php`:

- Cria `membership_public_identities`, com empresa, e-mail criptografado, hash de pesquisa e vínculo de cliente por empresa.
- Cria `membership_identity_challenges`, com código e sessão em hash, prazo, tentativas e consumo.
- Acrescenta à tabela existente `membership_checkout_intentions`: identidade pública, plano, cliente remoto dedicado e marcas de início das três operações.
- Acrescenta unicidade identidade/plano, unicidade conta/cliente remoto e vínculo de identidade restrito à empresa.
- Todas as colunas novas em registros existentes são opcionais. Não faz backfill, DELETE, mudança de tipos ou alteração de estado de contrato/pagamento. `down()` recusa rollback automático financeiro.

O teste de upgrade original preserva os seis estados legados e os pagamentos. Um segundo teste aplica **somente a migration da Etapa 3**, sobre schema com as Etapas 1/2 e sentinelas em seis tabelas financeiras: contas, contratos, intenções, faturas, pagamentos e eventos. Confere todos os valores anteriores e as quantidades, sem adoção automática.

## Testes e evidências

| Validação final | Resultado |
|---|---|
| PostgreSQL 18.6, regressão selecionada completa | **238 testes, 1.389 assertions, passaram** |
| Suíte nova PublicRecurringCheckoutTest incluída acima | **35 testes**, cobrindo os requisitos da Etapa 3 |
| PostgreSQL: upgrade completo e upgrade isolado da Etapa 3, incluídos acima | **2 testes, 50 assertions, passaram** |
| PostgreSQL: dois workers independentes da infraestrutura financeira | **1 teste, 11 assertions, passou** |
| SQLite em memória: fluxo público, fundação e upgrade | **55 testes, 389 assertions, passaram** |
| Build Vite | **54 módulos, compilação concluída** |
| Sintaxe PHP e Pint | Todos os arquivos PHP alterados verificados, sem erros finais |

A seleção de regressão inclui PublicRecurringCheckoutTest, FinancialPipelineTest, PostgresFoundationUpgradeTest, FinancialAmountTest, MembershipFoundationTest, MembershipFoundationUpgradeTest, MembershipPaymentGatewayTest, CompanyPaymentIntegrationTest, StudioFlowEnhancementTest, Phase2RetentionTest, PdvSaleTest, PublicBookingTest, PaymentTest, FinanceReportTest e CashRegisterTest. Não se afirma execução de toda a suíte do repositório.

Evidências textuais são preservadas na workspace em `stage3-evidence/`: validação PostgreSQL, SQLite, concorrência e build. O manifesto `stage3-manifest.json` registra arquivos e hashes antes/depois; os originais substituídos ficam em `stage3-originals/`.

Cobertura da suíte nova: os 17 cenários solicitados, além de código inválido/consumido, privacidade por telefone, rotação de chave, identidade externa diferente, bloqueio em produção, alteração de preço, falta de prova do checkout, mês com menos dias, paginação, consulta de verificação sem escritas remotas, recuperação sem webhook e barreiras nas ações administrativas.

Falhas intermediárias corrigidas: os campos de ciclo do contrato existente são obrigatórios e precisaram ser preenchidos ao criar **novos** contratos pendentes, sem alterar contratos anteriores. A suíte antiga da fundação só aceitava SQLite; passou a aceitar também o PostgreSQL local de teste após conferir host, porta, prefixo da base e major 18. O teste público antigo que aceitava telefone e criava contrato sem identificação foi atualizado para comprovar o bloqueio. Não são mudanças nos contratos financeiros existentes.

O build apresentou somente aviso de base Browserslist desatualizada; dependências não foram atualizadas fora do escopo. Os templates são renderizados pelos feature tests. Não se afirma inspeção visual manual em dispositivos móveis ou validação real da página hospedada Asaas.

### Reproduzir

Usar `tests/financial-bootstrap.php`, que força ambiente testing, credenciais fictícias, conexão local e base nova; não executar testes com a configuração de produção. A instância portátil de teste está na workspace, separada do PostgreSQL Railway.

```powershell
$env:FINANCE_TEST_DRIVER='pgsql'
php -d extension=pdo_pgsql vendor/phpunit/phpunit/phpunit --bootstrap tests/financial-bootstrap.php --do-not-cache-result tests/Feature/PublicRecurringCheckoutTest.php tests/Feature/FinancialPipelineTest.php tests/Feature/PostgresFoundationUpgradeTest.php
php -d extension=pdo_pgsql vendor/phpunit/phpunit/phpunit --bootstrap tests/financial-bootstrap.php --do-not-cache-result tests/Feature/FinancialConcurrencyTest.php
```

O teste de dois processos deve ser executado separadamente. Para SQLite em memória, usar `FINANCE_TEST_DRIVER=sqlite`, sem o teste específico de PostgreSQL. Não usar `migrate:fresh` ou rollback automático.

## Pendências para homologação e ativação futura

1. A loja criar sua própria conta Sandbox e concluir a configuração do [guia](asaas-merchant-setup.md). Configurar SMTP, URL HTTPS, webhook por conta, worker `database/finance` e scheduler na homologação.
2. Validar com o Asaas real: checkout hospedado Sandbox, dados adicionais exigidos para o cliente, aprovação/recusa, expiração, entrega de eventos, filtro `checkoutSession`, confirmação financeira, renovação e interrupção sem excluir cobranças. Os testes simulados não comprovam esses contratos externos.
3. Conferir a jornada visual em celular e desktop, incluindo recebimento de e-mail e retorno após sessão expirada. Depois de expirar a sessão, a pessoa deve confirmar o mesmo e-mail novamente; a intenção única continua preservada.
4. Definir procedimento de suporte para intenções incertas e reabertura de jornada encerrada, comprovando antes ausência de assinatura/cobrança remota. Não há botão de recriar automaticamente.
5. Monitorar filas, consultas recorrentes de checkouts não correlacionados e quarentena. Rotação de `APP_KEY` exige plano para criptografia e hashes; não deve ser feita como parte desta entrega.
6. Confirmar a versão exata da produção e suas particularidades antes de qualquer implantação. A conta real e o recebimento produtivo exigem revisão e autorização separadas; os bloqueios desta entrega impedem ativar produção apenas alterando flags.
7. A liberação operacional automática dos benefícios depende da **Etapa 4**, não iniciada.

## Arquivos

### Criados

- `app/Jobs/ReconcileMembershipCheckout.php`
- `app/Mail/MembershipIdentityCode.php`
- `app/Models/MembershipPublicIdentity.php`
- `app/Services/MembershipCheckoutEventService.php`
- `app/Services/MembershipPublicIdentityService.php`
- `app/Services/PublicMembershipCatalog.php`
- `app/Services/PublicMembershipEnrollment.php`
- `app/Services/Payments/Gateways/AsaasRecurringGateway.php`
- `app/Services/Payments/V2/PublicMembershipAccount.php`
- `app/Console/Commands/VerifyAsaasSandboxAccount.php`
- `database/migrations/2026_10_23_000001_add_public_recurring_enrollment.php`
- `resources/views/public-memberships/club.blade.php`
- `resources/views/public-memberships/identity-email.blade.php`
- `resources/views/public-memberships/result.blade.php`
- `tests/Feature/PublicRecurringCheckoutTest.php`
- `docs/asaas-merchant-setup.md`
- `docs/financial-stage3.md`

### Modificados

- `app/Models/MembershipCheckoutIntention.php`
- `app/Services/FinancialEventProcessor.php`
- `app/Services/SubscriptionReconciliationService.php`
- `app/Services/WebhookIngestionService.php`
- `app/Services/Payments/FinancialErrorCode.php`
- `app/Services/Payments/FinancialGatewayRegistry.php`
- `app/Http/Controllers/CustomerMembershipController.php`
- `app/Http/Controllers/PublicBookingController.php`
- `app/Http/Controllers/PublicMembershipController.php`
- `app/Console/Commands/DispatchFinancialWork.php`
- `config/financial.php`
- `resources/views/clients/show.blade.php`
- `resources/views/public-bookings/create.blade.php`
- `resources/views/public-memberships/index.blade.php`
- `routes/web.php`
- `tests/Feature/MembershipFoundationTest.php`
- `tests/Feature/MembershipFoundationUpgradeTest.php`
- `tests/Feature/PostgresFoundationUpgradeTest.php`
- `tests/Feature/StudioFlowEnhancementTest.php`

Os arquivos gerados `public/build/manifest.json` e o CSS compilado são artefatos locais ignorados pelo Git. O build de implantação deve ser executado no ambiente apropriado no futuro. Não houve alteração de `.env`, dependências, configuração Railway, V1 ou banco produtivo.

## Documentação oficial consultada

- [Checkout recorrente](https://docs.asaas.com/docs/checkout-com-assinatura-recorrente) e [criar checkout](https://docs.asaas.com/reference/criar-novo-checkout).
- [Eventos de checkout](https://docs.asaas.com/docs/eventos-para-checkout), [eventos de assinatura](https://docs.asaas.com/docs/eventos-para-assinaturas) e [callbacks](https://docs.asaas.com/docs/link-do-checkout-e-redirecionamento-do-cliente).
- [Listagem de cobranças e filtro checkoutSession](https://docs.asaas.com/reference/listar-cobrancas).
- [Interromper renovação preservando cobranças](https://docs.asaas.com/reference/atualizar-assinatura-existente).
- [Situação cadastral](https://docs.asaas.com/reference/consultar-situacao-cadastral-da-conta), [Wallet ID](https://docs.asaas.com/reference/retrieve-walletid), [Sandbox](https://docs.asaas.com/docs/sandbox) e [chaves](https://docs.asaas.com/docs/chaves-de-api).
