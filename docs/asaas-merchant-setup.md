# Como a loja configura o Asaas no StudioFlow

Esta entrega funciona somente em homologação/Sandbox. Nenhuma conta foi criada para você e nenhuma chave real foi cadastrada. O recebimento será na conta da própria loja. Não existe criação automática de subconta, comissão do StudioFlow ou uso de conta de outra empresa.

## O que a loja faz

1. Acesse [Criar conta Sandbox](https://sandbox.asaas.com/) e escolha **Criar conta Sandbox**. O responsável da loja deve concluir o cadastro e os dados solicitados pelo Asaas. O Sandbox é separado da conta real, inclusive cadastro, chaves e dados. [Orientação oficial](https://docs.asaas.com/docs/sandbox).
2. No painel web do Asaas Sandbox, um administrador abre **Integrações > Chaves de API**, cria uma chave exclusiva para StudioFlow e guarda em local seguro. A chave aparece apenas uma vez. Não envie a chave por chat, e-mail ou prints. [Chaves de API](https://docs.asaas.com/docs/chaves-de-api).
3. Na instalação de homologação do StudioFlow, entre como administrador da empresa correta e abra a tela existente de **Integrações de pagamento**. Cadastre uma nova integração: gateway **Asaas**, ambiente **Sandbox**, chave no campo **API Key**, integração ativa. Use uma identificação interna que deixe claro o nome da loja e o ambiente de testes. Não altere a conexão Mercado Pago dos agendamentos.
4. Cadastre um segredo exclusivo de webhook com pelo menos 32 caracteres aleatórios no campo correspondente. Esse segredo é diferente da API Key. O responsável técnico configura o mesmo segredo no Asaas como `authToken` do webhook. Não é necessária senha bancária.
5. Solicite ao responsável técnico a verificação da conta descrita abaixo. Salvar a chave ou clicar no teste antigo de conexão V1 **não** habilita automaticamente a recorrência V2. Até a verificação e a habilitação da homologação, os planos não aparecem como contratáveis.
6. Configure um plano ativo com valor positivo, renovação automática, periodicidade mensal/trimestral/semestral/anual, serviços e condições preenchidos. Duração personalizada em dias não é suportada pelo checkout recorrente desta etapa. A recorrência usa meses de calendário.
7. Após a verificação, o responsável técnico seleciona **somente essa conta Sandbox Asaas** como padrão de assinaturas na instalação de homologação. Caso exista ambiguidade, o checkout fica bloqueado. Essa seleção não muda o gateway dos agendamentos avulsos.
8. Teste o link `/agendar/slug-da-loja`: escolha **Assinar agora**, confirme o e-mail pelo código enviado, revise valor e renovação, aceite as condições e avance para o checkout. Use somente dados e cartões de teste autorizados conforme o Asaas. Não use cartão real.

## O que o responsável técnico configura

Tudo abaixo se destina exclusivamente a uma instalação separada, com banco de homologação e `APP_ENV` diferente de `production`. Não copiar a base produtiva nem alterar variáveis da Railway de produção.

- Aplicar incrementalmente as migrations pendentes nessa instalação, após revisar os backups e a conexão. A migration nova é `2026_10_23_000001_add_public_recurring_enrollment.php`; ela depende das anteriores. Não executar `migrate:fresh`, reset de banco ou rollback financeiro automático.
- Configurar `APP_URL` com o endereço HTTPS da homologação e envio de e-mail funcional para os códigos. Manter a `APP_KEY` protegida e estável: ela protege credenciais e e-mails armazenados.
- Obter o ID externo da conta e o Wallet ID da própria conta usando as consultas oficiais `GET /v3/myAccount/status/` e `GET /v3/wallets/`. **Account ID e Wallet ID não são intercambiáveis.** A situação geral deve ser `APPROVED`. [Situação cadastral](https://docs.asaas.com/reference/consultar-situacao-cadastral-da-conta), [Wallet ID](https://docs.asaas.com/reference/retrieve-walletid).
- Executar, após conferir empresa e integração, somente na homologação:

```text
php artisan financial:verify-asaas-sandbox ID_DA_INTEGRACAO ACCOUNT_ID WALLET_ID --confirm-account-ownership
```

Esse comando consulta a API Sandbox, compara o Account ID retornado e o Wallet ID, exige aprovação e grava o vínculo verificado **no banco de homologação**. Não cria cliente, checkout, assinatura, subconta ou cobrança. Não usa a opção padrão para escolher uma conta. A identidade já verificada é imutável; outra identidade exige outro registro. Uma chave substituída exige executar novamente a verificação da mesma conta.

- No painel Asaas Sandbox, configurar o webhook HTTPS da conta:

```text
https://HOMOLOGACAO/webhooks/company-payments/asaas/ID_DA_INTEGRACAO/sandbox
```

Usar `authToken` igual ao segredo cadastrado na integração. Preferir envio sequencial. Configurar `CHECKOUT_CREATED`, `CHECKOUT_PAID`, `CHECKOUT_CANCELED`, `CHECKOUT_EXPIRED`; `SUBSCRIPTION_CREATED`, `SUBSCRIPTION_UPDATED`, `SUBSCRIPTION_INACTIVATED`, `SUBSCRIPTION_DELETED`; e os eventos de cobranças suportados: `PAYMENT_CREATED`, `PAYMENT_UPDATED`, `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED`, `PAYMENT_OVERDUE`, `PAYMENT_CREDIT_CARD_CAPTURE_REFUSED`, `PAYMENT_REFUNDED`, `PAYMENT_DELETED`, `PAYMENT_CHARGEBACK_REQUESTED`. [Eventos de checkout](https://docs.asaas.com/docs/eventos-para-checkout).

- Habilitar **apenas na homologação**:

```dotenv
FINANCIAL_RECONCILIATION_ENABLED=true
FINANCIAL_ASAAS_V2_ENABLED=true
FINANCIAL_PUBLIC_CHECKOUT_ENABLED=true
```

- Reiniciar os processos da homologação após atualizar a configuração. Iniciar o worker da conexão `database`, fila `finance`, e o scheduler Laravel. O agendamento `financial:dispatch` existente recupera eventos e concilia assinaturas correlacionadas. Revisar eventos em quarentena e falhas sem expor credenciais ou dados pessoais.
- Confirmar no Sandbox o endereço hospedado de checkout, a criação com o cliente fornecido, os dados adicionais pedidos pelo Asaas, o filtro `checkoutSession` na listagem de cobranças e a periodicidade. Essa homologação externa continua pendente porque o usuário informou que não existem credenciais autorizadas.

## Quando ocorrer falha

- Não repetir manualmente chamadas de criação após timeout. O sistema mantém a intenção única em verificação para impedir duplicação.
- Checkout expirado, abandonado ou recusado não libera benefícios. Uma nova contratação após encerramento exige primeiro conferir que não existe assinatura/cobrança autorizada. A reabertura automática não faz parte desta entrega.
- Se a conta for desconectada ou a chave for alterada, a nova contratação fica bloqueada. Reconfira a conta e execute a verificação de Sandbox.
- O retorno de sucesso no navegador não é comprovante. A confirmação depende de consulta financeira, assinatura correlacionada e cobertura válida. Os benefícios operacionais continuam sob as regras atuais até a Etapa 4.

## Conta real no futuro

A loja deverá criar e aprovar sua própria conta real no [Asaas](https://www.asaas.com/), cumprir os requisitos cadastrais/documentais solicitados e gerar uma chave própria de produção. Essa conta e a chave não devem ser cadastradas para testar esta entrega. A habilitação produtiva exige homologação concluída, revisão separada do bloqueio de produção e autorização específica. Alterar somente as três flags acima **não** libera produção. Nenhuma Etapa 4 foi iniciada.
