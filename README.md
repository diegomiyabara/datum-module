# Miyabara_FeaturedProduct

Módulo para Magento 2.4.6 Open Source (tema Luma) que exibe um **produto em destaque na homepage**,
como primeiro elemento do conteúdo principal, ocupando toda a largura do content main. A caixa mostra
título, preço, imagem base e a **quantidade disponível para venda atualizada em tempo real** —
o número é consultado periodicamente via AJAX, sem recarregar a página. Clicar na caixa leva à
página do produto.

Nenhum arquivo de tema é alterado: tudo vive dentro do módulo.

## Requisitos

- Magento Open Source 2.4.6 com MSI habilitado (padrão)
- PHP 8.1 (neste projeto os comandos usam o binário `php8.1`)

## Instalação

```bash
clone o repositorio dentro de app/code
php8.1 bin/magento module:enable Miyabara_FeaturedProduct
php8.1 bin/magento setup:upgrade
php8.1 bin/magento cache:flush
```

Em produção, rode também `setup:di:compile` e `setup:static-content:deploy`.

## Configuração

Admin → **Stores → Configuration → Catalog → Featured Product**:

| Campo | Descrição |
|---|---|
| Enable | Liga/desliga a caixa (escopo de store view) |
| Product SKU | SKU do produto exibido |
| Refresh Interval (seconds) | Frequência do polling da quantidade (5–300s, padrão 15) |

Após salvar, limpe o cache (`php8.1 bin/magento cache:flush`) — a homepage fica no full page cache.

Se o SKU não existir ou o módulo estiver desabilitado, a caixa simplesmente não renderiza
(com warning no `var/log/system.log`) — a homepage nunca quebra.

## Como funciona

- **Inserção na homepage**: `view/frontend/layout/cms_index_index.xml` declara o bloco no container
  `content` com `before="-"` (primeiro elemento do content main). O *block argument* (view model)
  é injetado via `referenceBlock`.
- **Dados do produto** (título, preço final, imagem base, URL): resolvidos server-side pelo
  `ViewModel/FeaturedProduct`. A saída do bloco permanece cacheável pelo FPC e amigável a SEO.
- **Quantidade em tempo real**: componente Knockout (`uiComponent`) inicializado por
  `x-magento-init` + `Magento_Ui/js/core/app`, com o mapa de componentes (jsLayout) montado
  diretamente no template — os valores de runtime (URL do endpoint e intervalo) vêm de getters
  do ViewModel. Um `ko.observable` atualiza apenas o número na tela; o polling usa o intervalo
  configurado no admin.
- **Endpoint**: `GET /rest/{storeCode}/V1/featured-product/salable-qty` — service contract
  (`Api/GetStockUpdateInterface`) exposto via `webapi.xml` com acesso anônimo. Retorna
  `{version, qty}` com a quantidade *salable* via MSI (`StockResolver` + `GetProductSalableQty`),
  ou seja, respeita reservas e multi-source — é a quantidade disponível para venda de verdade.
  O endpoint não recebe SKU: ele lê o produto configurado, então não vira consulta de estoque
  arbitrária.
- **Polling barato (conditional GET / ETag)**: o validador do fluxo é HTTP puro. O token de
  mudança viaja como header `ETag`; o JS devolve-o em `If-None-Match` e, se nada mudou, um
  plugin no processador REST responde **`304 Not Modified` sem corpo, sem nem executar o
  service**. O token é invalidado por um plugin `after` (`Plugin/RefreshStockVersion`)
  registrado em dois pontos: `SourceItemsSaveInterface` (edição no admin, import, API) e
  `AppendReservationsInterface` (pedido feito/cancelado/reembolsado, que altera a qty salable
  **sem** salvar source item). O token vive num **cache customizado declarado por virtual
  type** — `TagScope` com a tag `MIYABARA_FEATURED_STOCK` sobre o cache `collections` (mesmo
  padrão do `Magento\PageBuilder\Model\Cache\Type\EditorConfig`). Dois seguros adicionais:
  tokens **expiram em 5 minutos** (proteção contra invalidação perdida, ex. reindex assíncrono)
  e a qty tem **micro-cache de 60s por token** — clientes sem ETag não conseguem estourar o MSI.
  Limpar qualquer cache só custa um lookup extra, nunca um valor obsoleto.
- **Estilo**: `view/frontend/web/css/source/_module.less`, importado automaticamente pelo Luma
  (`@magento_import`) — sem tocar no tema.

### Por que polling (e não push)?

Push de verdade (SSE/WebSocket) exigiria que cada visitante da homepage mantivesse uma conexão
aberta com o servidor — em PHP-FPM isso significa um worker preso por visitante, o que não escala
sem um pool dedicado ou um hub externo (Mercure/Pusher), ou seja, infraestrutura além do módulo.
O polling condicional (ETag/304) entrega a mesma percepção de tempo real (atraso médio de metade
do intervalo configurado) com custo por requisição próximo de zero: quando nada mudou, a resposta
é um 304 vazio resolvido no cache, sem tocar no MSI. Os plugins de invalidação já são o "publisher" — se um dia houver um hub
de push, basta trocar o canal de entrega; o frontend (observable Knockout) permanece o mesmo.

```
curl -i https://sua-loja.com/rest/V1/featured-product/salable-qty
# → HTTP/1.1 200 OK
# → ETag: "6a49cbdb634765.07448283"
# → {"version":"6a49cbdb634765.07448283","qty":42}

curl -i https://sua-loja.com/rest/V1/featured-product/salable-qty \
  -H 'If-None-Match: "6a49cbdb634765.07448283"'
# → HTTP/1.1 304 Not Modified   (sem corpo, sem executar o service)
```

## Testes

```bash
cd dev/tests/unit
../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../app/code/Miyabara/FeaturedProduct/Test/Unit/
```

## Estrutura

```
app/code/Miyabara/FeaturedProduct/
├── Api/GetStockUpdateInterface.php       # contrato do endpoint de estoque
├── Api/Data/StockUpdateInterface.php     # resposta do endpoint (version/qty)
├── Block/FeaturedProduct.php             # guarda de renderização: sem produto válido, sem caixa
├── Model/Config.php                      # gateway tipado do system config
├── Model/GetStockUpdate.php              # qty salable via MSI, curto-circuito por token e micro-cache
├── Model/StockVersion.php                # token de mudança por SKU, em cache
├── Model/Data/StockUpdate.php            # DTO imutável da resposta
├── Plugin/                               # invalidação do token (source items + reservations)
├── ViewModel/FeaturedProduct.php         # dados do produto para o template
├── etc/                                  # module, di, acl, config, system, webapi, view
├── view/frontend/
│   ├── layout/cms_index_index.xml        # referenceContainer + referenceBlock + arguments
│   ├── templates/product.phtml           # markup da caixa + x-magento-init
│   └── web/
│       ├── js/view/stock.js              # componente Knockout com polling
│       ├── template/stock.html           # template KO do badge de estoque
│       └── css/source/_module.less
├── i18n/pt_BR.csv
└── Test/Unit/                            # service, token, plugin e view model
```
