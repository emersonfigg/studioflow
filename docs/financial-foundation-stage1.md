# Etapa 1 — Fundação e compatibilidade

Implementação em 20/10/2026. Esta etapa não habilita checkout, não processa webhooks novos, não concilia cobranças e não altera elegibilidade. A Etapa 2 não foi iniciada.

## Estrutura entregue

- Interface opcional `Contracts/V2/RecurringPaymentGatewayInterface` e DTOs em `Services/Payments/V2`. A interface V1 e os três adapters existentes permanecem intactos. A seleção V2 falha explicitamente enquanto o adapter não tiver essa capacidade.
- `FinancialAccountContext`: identidade imutável contendo empresa, integração, provedor, ambiente e conta externa, sem segredos. `FinancialAccountResolver`: resolução explícita, sem fallback para outra conta. Conta legada não é considerada verificada apenas por possuir um rótulo em account_identifier.
- Identidade verificada em CompanyPaymentIntegration não pode ser reatribuída pelo model. Credenciais podem ser rotacionadas e a integração pode ser desconectada. Eventos históricos podem resolver a identidade mesmo desconectada; iniciar recorrência exige integração ativa.
- `LegacyMembershipClassifier` e `memberships:inspect-legacy {company}`: inventário JSON somente leitura por empresa. Classifica evidência de pagamento positivo confirmado, venda presencial com comanda paga e entrada no caixa, origem indeterminada ou inconsistência. Não comprova cobertura atual e nunca presume autorização de recorrência. Inconsistências têm precedência.
- Models e estruturas para faturas, intenções, clientes por conta, eventos e histórico. Referências são validadas por empresa, cliente, assinatura e conta no Eloquent; FKs compostas protegem empresa também contra inserções diretas nas novas estruturas. Restrições de mesma conta/cliente entre entidades permanecem na camada de domínio e devem ser usadas pelos futuros serviços; SQL direto não substitui essa validação.

## Migrations

1. `2026_10_20_000001_add_membership_financial_foundation.php`: adapta company_payment_integrations, customer_memberships, membership_payments e membership_usages; cria payment_account_customers, membership_checkout_intentions e membership_invoices.
2. `2026_10_20_000002_create_membership_financial_audit_tables.php`: cria payment_webhook_events e subscription_status_history.

São aditivas: não renomeiam/apagam tabelas, não atualizam registros, não fazem backfill e não vinculam automaticamente contratos às contas. Colunas acrescentadas aos contratos antigos ficam NULL. Mantidos os índices e campos financeiros legados. IDs externos novos têm unicidade no contexto da conta; a conta verificada identifica um ambiente imutável.

Os métodos down recusam rollback para impedir perda de histórico financeiro. Uma reversão futura exigirá migration compensatória revisada. Nenhuma dessas migrations foi aplicada ao banco do repositório original ou à produção; execução ocorreu somente em SQLite em memória nos testes.

## Validação executada

Na cópia isolada, sem .env e com bootstrap que força APP_ENV=testing, SQLite :memory:, cache/sessão em array, mail array, logs nulos e chave fictícia:

```powershell
php vendor/phpunit/phpunit/phpunit --bootstrap tests/foundation-bootstrap.php --do-not-cache-result tests/Feature/MembershipFoundationTest.php tests/Feature/MembershipFoundationUpgradeTest.php
```

Resultado final da fundação: **20 testes, 113 assertions, todos passaram**. Inclui upgrade com contratos nos seis estados existentes, identidade financeira, ambiente, isolamento de empresa e conta, unicidade de eventos/faturas/transações, compatibilidade dos adapters, inventário sem escrita e ausência de requisições HTTP.

Regressão executada: MembershipFoundationTest, MembershipFoundationUpgradeTest, MembershipPaymentGatewayTest, CompanyPaymentIntegrationTest, StudioFlowEnhancementTest, Phase2RetentionTest, PdvSaleTest, PublicBookingTest, PaymentTest, FinanceReportTest e CashRegisterTest. Resultado: **163 testes, 846 assertions, todos passaram**. Depois foram acrescentados três testes de unicidade/histórico, cobertos pela execução final de 20 testes acima. Não se afirma execução da suíte inteira do projeto.

Sintaxe: 26 arquivos PHP verificados sem erros. Pint executado apenas nos arquivos desta etapa.

Falhas intermediárias corrigidas: fixture de caixa sem cash_register_id e comparação entre model recém-criado e linha recarregada com atributos/defaults do banco. A execução inicial do PHPUnit/Pint foi impedida pela avaliação de permissões de arquivo do PHP no sandbox; ambos executaram na cópia isolada com a permissão necessária. Não restam falhas nas execuções finais.

## Arquivos

Existentes alterados:

- app/Models/CompanyPaymentIntegration.php
- app/Models/CustomerMembership.php
- app/Models/MembershipPayment.php
- app/Models/MembershipUsage.php
- app/Services/Payments/PaymentGatewayManager.php

Novos:

- app/Enums/LegacyMembershipClassification.php
- app/Models/Concerns/ValidatesFinancialCompany.php
- app/Models/MembershipCheckoutIntention.php
- app/Models/MembershipInvoice.php
- app/Models/PaymentAccountCustomer.php
- app/Models/PaymentWebhookEvent.php
- app/Models/SubscriptionStatusHistory.php
- app/Services/LegacyMembershipClassifier.php
- app/Console/Commands/InspectLegacyMemberships.php
- app/Services/Payments/Contracts/V2/RecurringPaymentGatewayInterface.php
- app/Services/Payments/V2/CheckoutRequest.php
- app/Services/Payments/V2/FinancialAccountContext.php
- app/Services/Payments/V2/FinancialAccountResolver.php
- app/Services/Payments/V2/GatewayResource.php
- app/Services/Payments/V2/NormalizedEvent.php
- app/Services/Payments/V2/SubscriptionCheckout.php
- As duas migrations acima.
- tests/foundation-bootstrap.php
- tests/Feature/MembershipFoundationTest.php
- tests/Feature/MembershipFoundationUpgradeTest.php
- docs/financial-foundation-stage1.md

## Pendências e limites

- Validar DDL/índices no mesmo motor e versão do banco de implantação, em homologação. Esta validação foi feita em SQLite, não em MySQL produtivo.
- Aplicação das migrations ao ambiente de implantação deve ser uma operação separada. Não rodar migrate:fresh nem rollback dessas estruturas em dados reais.
- Inventariar contratos e revisar evidências antes de qualquer gravação de classificação, cobertura ou alteração de elegibilidade. O comando entregue não escreve os campos de classificação; eles ficam reservados para um processo revisado futuro. Nenhum inventário foi executado contra dados reais nesta entrega.
- Verificação de conta externa e onboarding Asaas continuam pendentes. Nenhuma conta foi marcada como verificada automaticamente.
- Permanecem os comportamentos legados de pagamento/benefícios e as lacunas da auditoria inicial. Eles não foram corrigidos nesta etapa para preservar contratos financeiros atuais.
- Implementação de adapter recorrente, ingestão autenticada, filas, webhooks, conciliação, cobertura e políticas financeiras ficam para etapas posteriores. Não há bindings nem rotas que ativem a interface V2 nesta entrega.
- Não foram alterados AsaasGateway, MercadoPagoGateway, GalaxyPayGateway, BookingPaymentService, controllers, rotas, scheduler, configuração produtiva ou banco persistente.
