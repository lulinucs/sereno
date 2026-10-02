# Monitor de câmaras frias

ESP8266 + BME280 envia temperatura, umidade e pressão para a API PHP. O SQLite em `data/camara.sqlite` guarda leituras, configurações, estado do Alert Engine, eventos e o estado de leitura do centro de notificações. O dashboard consome somente a API. `monitor.php` verifica offline por tarefa agendada.

## Estrutura

| Arquivo | Função |
| --- | --- |
| `api.php` | Ingestão e consultas HTTP, sem mudança no protocolo do ESP |
| `index.php` | Dashboard operacional, histórico, configurações e notificações |
| `monitor.php` | Verificação offline via CLI/cron |
| `src/database.php` | Conexão PDO SQLite, schema e transações |
| `src/storage.php` | Leituras e consultas por dispositivo/período |
| `src/device_repository.php` / `src/devices.php` | Persistência e validação de configurações |
| `src/alert_engine.php` / `src/alert_persistence.php` | Regras existentes e persistência transacional |
| `src/notifications.php` | Eventos e leitura no dashboard |
| `scripts/migrate_sqlite.php` | Importação explícita dos JSONs antigos |
| `scripts/add_device.php` | Cadastro CLI de um novo dispositivo |
| `src/migration.php` | Validação, importação idempotente e comparação |
| `src/admin_auth.php` | Sessão administrativa, CSRF e limite de tentativas |

O schema tem `devices`, `readings`, `alert_states`, `events`, `notification_reads` e `admin_login_attempts`. Timestamps originais são preservados; colunas de época permitem consulta eficiente por período. Há índices `(device_id, recorded_epoch)` em leituras e `(device_id, created_epoch)` em eventos. A leitura de notificações é separada dos eventos; uma futura entrega por Telegram poderá ter tabela própria, sem alterar esses registros.

## Nova instalação ou migração

Em uma instalação nova, crie primeiro um banco vazio com o schema atual:

```bash
php scripts/init_db.php
```

O comando cria `data/camara.sqlite` sem dispositivos, leituras, eventos ou configurações. O cadastro posterior continua sendo feito pelos mecanismos existentes do projeto.

1. Confirme `pdo_sqlite` no PHP usado pelo site e pelo CLI: `php -r "var_export(in_array('sqlite', PDO::getAvailableDrivers(), true));"`. No servidor, confirme também no PHP web, por exemplo com `phpinfo()` temporário protegido.
2. Faça backup dos cinco JSONs em `data/`: `leituras.json`, `events.json`, `state.json`, `devices.json` e `notification_state.json`. Pare temporariamente envios e o cron durante a migração para ter um recorte consistente.
3. Configure `.env` a partir de `.env.example`. `SQLITE_FILE` tem padrão `data/camara.sqlite`; mantenha `DEFAULT_DEVICE` igual ao usado no histórico legado. Tokens do ESP permanecem nas variáveis `DEVICE_<ID>_TOKEN`.
4. Execute `php scripts/migrate_sqlite.php`. Para importar um diretório de backup específico, use `php scripts/migrate_sqlite.php /caminho/dos/jsons /caminho/do/camara.sqlite camara-01`.
5. Leia os totais verificados do comando: dispositivos, leituras por câmara, eventos, estados e notificações lidas. Repita o comando: os totais de origem devem permanecer iguais, sem duplicação no banco. Um JSON inválido causa erro e a importação transacional não é aplicada.
6. Reative envios e cron; consulte `api.php?action=overview`, `api.php?action=data&device_id=camara-01&hours=24`, o dashboard e o centro de notificações. Teste um envio real ou controlado antes de encerrar a manutenção.

O script importa com `INSERT OR IGNORE`: uma segunda execução não sobrescreve configurações ou estados que tenham mudado no SQLite. JSONs antigos não são lidos pela operação normal, nem apagados pela migração. Após validar e manter uma cópia de backup, podem ser arquivados manualmente. A antiga cópia `leituras.json` da raiz não participa da migração.

## API e Alert Engine

O ESP mantém o mesmo `POST api.php?action=push`, corpo JSON (`device_id`, `temperatura`, `umidade`, `pressao`) e cabeçalho opcional `X-Device-Token`. Validações, códigos HTTP e formato da resposta são mantidos. A API grava a leitura e aplica o Alert Engine dentro de uma transação. O motor conserva os estados `NORMAL`, `TEMP_ALTA`, `TEMP_BAIXA`, `OFFLINE`, a tolerância, histerese e os eventos `TEMP_HIGH`, `TEMP_LOW`, `NORMALIZED`, `OFFLINE`, `ONLINE`.

O dashboard usa `GET action=devices`, `GET/POST action=device_config`, `GET action=overview`, `GET action=data&device_id=...&hours=1|6|24|168`, `GET action=events` e os POSTs de notificação existentes. `action=data` consulta somente o período pedido (no mínimo 24h para preservar a tabela de leituras recentes), mais a última leitura da câmara. Tokens de dispositivos não são retornados pela API.

## Acesso administrativo

O painel e as consultas continuam públicos. Ao abrir configurações ou usar ações que alteram o estado compartilhado das notificações, o painel pede a senha administrativa uma vez por sessão. O backend exige sessão administrativa **e** CSRF para `POST action=device_config`, `notification_read`, `notifications_read_all` e `test_notification`. `POST action=push` do ESP continua independente da sessão; o token opcional do dispositivo permanece como antes. `POST action=login`, `POST action=logout` e `GET action=auth_status` completam o fluxo.

Defina `ADMIN_PASSWORD_HASH` no `.env` privado. Para gerar o hash da senha escolhida, execute no terminal:

```bash
php -r "echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), PHP_EOL;"
```

Digite a senha quando o comando aguardar entrada e copie **somente o hash** exibido para `ADMIN_PASSWORD_HASH=`. Não copie a senha em texto puro para o `.env` nem para arquivos publicados. O `.env.example` contém apenas um placeholder. Se o hash não estiver configurado, consultas e telemetria seguem funcionando, mas o login administrativo retorna indisponível.

A sessão usa cookie `HttpOnly`, `SameSite=Strict` e `Secure` quando a requisição chega por HTTPS, com regeneração do ID no login/logout e expiração após 8 horas sem ação administrativa. Cinco falhas de senha em uma janela de 15 minutos bloqueiam novos logins do mesmo endereço por 5 minutos. O bloqueio fica no mesmo SQLite. Ações de leitura de notificações requerem login porque o estado de leitura é global neste projeto, não pessoal por usuário.

`monitor.php` continua sendo executado por cron ou tarefa agendada aproximadamente a cada minuto, por exemplo `* * * * * /usr/bin/php /caminho/do/projeto/monitor.php`. Ele usa a mesma base e transações da ingestão. Não há daemon nem dependência de SQLite CLI.

## Permissões e proteção

`data/.htaccess` nega acesso HTTP direto ao diretório em Apache 2.4. Em Nginx, adicione regra equivalente. O `.htaccess` da raiz protege `.env`. A visualização do dashboard é pública por decisão do projeto; restrinja a rede ou use autenticação HTTP adicional se a telemetria também precisar ser privada.

- **VPS Apache/PHP com `www-data`:** dê ao usuário do PHP permissão de leitura e escrita em `data/`, e de criação de arquivos auxiliares SQLite no diretório. Um proprietário/grupo apropriado e modo `2775` no diretório, `664` no banco, podem ser usados quando `www-data` pertence ao grupo. Ajuste à política local e evite `777`.
- **HostGator/hospedagem compartilhada:** use o gerenciador de arquivos ou painel para que o usuário da conta executando PHP tenha escrita em `data/` e no banco. Normalmente `755` no diretório e `644` ou `664` no banco funcionam quando o proprietário é o usuário PHP; confirme a identidade real do processo. Não assuma root nem grupo `www-data`.

SQLite usa `busy_timeout=5000`, `foreign_keys=ON` e journal `DELETE`, adequado ao armazenamento local simples e evitando dependência de WAL/SHM persistentes em hospedagem compartilhada. Ainda assim, o diretório deve permitir a criação de journal temporário. Não coloque o banco em filesystem remoto sem verificar o suporte a locks.

## Backup e rollback

Faça backup SQLite-safe com a API de backup do SQLite ou `VACUUM INTO` em uma conexão SQLite, quando suportado. Não copie `camara.sqlite` diretamente enquanto o PHP escreve. Preserve também os JSONs originais como retrato pré-migração. Para rollback manual, pare envios e cron, restaure a versão anterior da aplicação com os JSONs de backup e reconcilie separadamente os dados produzidos desde a migração; voltar apenas o código perde leituras novas. Não há rollback automático.

## Novo dispositivo

Crie um arquivo JSON temporário com os mesmos campos de uma configuração de `data/devices.json` legado (por exemplo `nome`, `habilitado`, limites, tolerância, tempo offline, histerese e `monitoramentos`). Execute `php scripts/add_device.php camara-03 /caminho/config.json`. O script valida e insere no SQLite; depois remova o arquivo temporário se não for necessário. Configure `DEVICE_CAMARA_03_TOKEN` no `.env` caso a autenticação seja usada. O painel atual permite editar dispositivos já cadastrados. Ajuste o `DEVICE_ID` e token do ESP correspondente; o protocolo HTTP permanece igual.

## Testes

Os testes criam bancos SQLite próprios em memória ou diretórios temporários e não tocam `data/camara.sqlite`:

```bash
php tests/alert_engine_test.php
php tests/panel_notifications_test.php
php tests/sqlite_migration_test.php
php tests/monitor_sqlite_test.php
php tests/api_sqlite_test.php
php tests/admin_auth_test.php
```

Para lint: `php -l api.php`, `php -l index.php`, `php -l monitor.php` e `php -l` nos arquivos de `src/`, `scripts/` e `tests/`.
