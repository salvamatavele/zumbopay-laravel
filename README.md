# ZumboPay Laravel

Pacote oficial e independente para processamento de pagamentos móveis (**M-Pesa**, **e-Mola**, **mKesh**) e **Cartões Bancários** em aplicações Laravel via gateway **ZumboPay**.

---

## Recursos Principais

- **Suporte Multicanal Completo em Moçambique**:
  - Vodacom **M-Pesa** (`84`, `85`)
  - Movitel **e-Mola** (`86`, `87`)
  - Tmcel **mKesh** (`82`, `83`)
  - **Cartões Bancários** (Visa, Mastercard via Hosted Checkout)
- **Descoberta Dinâmica de Carteiras (Wallets)**: Não precisa mapear UUIDs manualmente; o cliente consulta a API e resolve a carteira ativa correta.
- **Normalização Automática de Telefones**: Trata números digitados nos mais diversos formatos e normaliza para o padrão moçambicano exigido de 12 dígitos (`258XXXXXXXXX`).
- **Tolerância a Timeouts USSD**: O timeout de rede durante a espera pelo PIN não aborta o pagamento; ele é marcado como `pending` para reconciliação via polling ou webhook.
- **Webhook Blindado com HMAC-SHA256**: Validação rigorosa com proteção contra ataques de temporização (`hash_equals`) e despacho de eventos tipados do Laravel.
- **Componente React 18/19 Exportável**: Modal com abas, identificação visual de operadoras e polling automático em tempo real.

---

## Instalação

### Opção A: Como Repositório Local (Path Repository)
No `composer.json` da sua aplicação Laravel:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/zumbopay-laravel"
    }
],
"require": {
    "salvamatavele/zumbopay-laravel": "@dev"
}
```

Em seguida, execute:
```bash
composer update salvamatavele/zumbopay-laravel
```

### Opção B: Publicação no Packagist
Se o pacote for publicado no GitHub / Packagist:
```bash
composer require salvamatavele/zumbopay-laravel
```

---

## Configuração

Publique o ficheiro de configuração:
```bash
php artisan vendor:publish --tag="zumbopay-config"
```

No seu ficheiro `.env`, defina as suas credenciais obtidas no painel da ZumboPay:
```env
ZUMBOPAY_BASE_URL="https://zumbopay.com/api/public/v1"
ZUMBOPAY_API_KEY="sua_api_key_aqui"
ZUMBOPAY_MERCHANT_ID="seu_merchant_id_aqui"
ZUMBOPAY_WEBHOOK_SECRET="seu_webhook_secret_aqui"

# Opcional (Se vazio, o pacote auto-resolve os UUIDs da sua conta):
ZUMBOPAY_WALLET_MPESA=""
ZUMBOPAY_WALLET_EMOLA=""
ZUMBOPAY_WALLET_MKESH=""
ZUMBOPAY_WALLET_CARD=""

# Silenciador / Kill-Switch (Opcional - Padrão: true):
ZUMBOPAY_ENABLED=true
ZUMBOPAY_DISABLED_MESSAGE="Os pagamentos via ZumboPay encontram-se temporariamente suspensos para manutenção."
```

---

### Silenciador / Kill-Switch (Resiliência Operacional)

Se a ZumboPay ou as operadoras móveis entrarem em manutenção programada ou instabilidade imprevista, você pode desativar as cobranças imediatamente:

1. **Via `.env`**:
   ```env
   ZUMBOPAY_ENABLED=false
   ```
2. **Programaticamente em tempo de execução**:
   ```php
   use ZumboPay\Laravel\Facades\ZumboPay;

   // Desativa cobranças (nenhuma chamada HTTP externa será disparada)
   ZumboPay::disable();

   // Verifica estado
   if (!ZumboPay::isEnabled()) {
       // Silenciado
   }

   // Reativa cobranças
   ZumboPay::enable();
   ```
Quando silenciado, `stkPush()`, `charge()` e `createCheckout()` retornam `success: false` com `code: 'GATEWAY_DISABLED'` e a mensagem amigável de manutenção, sem quebrar o fluxo do sistema nem gerar timeouts.

---

## Exemplos de Uso

### 1. Disparo de STK Push (M-Pesa / e-Mola / mKesh)

A operadora é detetada automaticamente pelo número:

```php
use ZumboPay\Laravel\Facades\ZumboPay;

// Disparo direto
$response = ZumboPay::stkPush(
    phone: '841234567', // ou 86XXXXXXX / 82XXXXXXX
    amount: 1500.00,    // Valor em Meticais
    reference: 'FATURA-2026-001',
    customerName: 'Manuel Cossa'
);

if ($response->isPaid()) {
    // Pagamento confirmado imediatamente (código INS-0)
} elseif ($response->isPending()) {
    // Pedido USSD enviado ao telemóvel do cliente aguardando PIN
} else {
    // Falha ou recusa: $response->message
}
```

### 2. Geração de Checkout Hospedado (Cartão Bancário)

```php
use ZumboPay\Laravel\Facades\ZumboPay;

$checkout = ZumboPay::createCheckout(
    amount: 3500.00,
    reference: 'FATURA-2026-002',
    returnUrl: route('pagamento.concluido')
);

if ($checkout->success) {
    return redirect($checkout->checkoutUrl);
}
```

### 3. Consulta de Estado (Polling)

```php
use ZumboPay\Laravel\Facades\ZumboPay;

$status = ZumboPay::status('FATURA-2026-001');

if ($status->isPaid) {
    // Pagamento confirmado!
}
```

---

## Escuta de Webhooks e Eventos

O pacote já disponibiliza a rota padrão `/api/webhooks/zumbopay` que valida a assinatura criptográfica e dispara eventos do Laravel:

No seu `EventServiceProvider` ou listener:

```php
use ZumboPay\Laravel\Events\ZumboPayPaymentSucceeded;
use ZumboPay\Laravel\Events\ZumboPayPaymentFailed;
use Illuminate\Support\Facades\Event;

Event::listen(ZumboPayPaymentSucceeded::class, function (ZumboPayPaymentSucceeded $event) {
    // $event->reference (ex: 'FATURA-2026-001')
    // $event->amount    (ex: 1500.00)
    // $event->channel   (ex: 'mpesa', 'emola', 'mkesh', 'card')
    // $event->payload   (dados brutos da ZumboPay)

    // Atualize o estado da sua fatura na base de dados:
    Fatura::where('numero', $event->reference)->update(['paga' => true]);
});
```

### Isenção de CSRF no Webhook
No Laravel 11/12/13 (`bootstrap/app.php`):
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'api/webhooks/zumbopay',
    ]);
})
```

---

## Componente React Reutilizável

Para exportar o componente React para o seu projeto:
```bash
php artisan vendor:publish --tag="zumbopay-react"
```

O componente será criado em `resources/js/components/ZumboPayModal.tsx` e pode ser usado da seguinte forma:

```tsx
import ZumboPayModal from '@/components/ZumboPayModal';
import { useState } from 'react';

export default function Exemplo() {
    const [open, setOpen] = useState(false);

    return (
        <ZumboPayModal
            open={open}
            onOpenChange={setOpen}
            amount={2500.00}
            reference="FAT-1002"
            initialPhone="841234567"
            endpoints={{
                stkUrl: '/api/pagamentos/stk',
                checkoutUrl: '/api/pagamentos/checkout',
                statusUrl: '/api/pagamentos/status?ref=FAT-1002',
            }}
            onSuccess={() => alert('Pagamento efetuado com sucesso!')}
        />
    );
}
```

---

## Licença
MIT. Desenvolvido para o ecossistema moçambicano.
