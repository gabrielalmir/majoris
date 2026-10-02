# Majoris

Base drop-in do CodeIgniter 3 para aplicações que continuam no modelo CI3. Este repositório não é o CodeIgniter 3 upstream e não é o CodeIgniter 4.

O aplicativo existente troca o `system/` (ou o `$system_path` em `index.php`) e segue com o mesmo `application/`. Assinaturas públicas de `CI_*`, helpers, chaves de config já existentes e a ordem dos hooks não mudam. `system/core/CodeIgniter.php` continua sendo o bootstrap.

PHP 7.4–8.5. MIT. O mesmo `system/` de hoje, no Apache em que a aplicação já roda.

## O que este repositório é

Fork do CodeIgniter 3.2.0-dev, com a compatibilidade de PHP 7.4 a 8.5 já no `main`. O escopo daqui para a frente está em [`MAJORIS.md`](MAJORIS.md): legado CI3, SQL Server 2019, PostgreSQL, SQLite, e a performance e a usabilidade que esses três motores exigem no código atual.

`mysqli` continua funcionando. Aplicação legada que aponta para MySQL não entra no roadmap e não pode quebrar.

Não há kernel novo, pacote Composer obrigatório, árvore `src/`, CLI de scaffolding nem fila. Dependências de desenvolvimento (PHPUnit, PHP_CodeSniffer, PHPStan) ficam só em `require-dev`.

## Requisitos

- PHP 7.4 ou mais novo, até 8.5
- O servidor web em que a aplicação CI3 já roda

Extensões de banco são as do driver em uso (`mysqli`, `sqlsrv`, `pgsql`, `sqlite3`, ou o PDO correspondente).

## Instalação

O mecanismo de carga é o do CodeIgniter 3. A [seção de instalação do guia CI3](https://codeigniter.com/userguide3/installation/index.html) descreve esse mecanismo; o produto e o suporte não são os do projeto upstream.

1. Substitua o `system/` da aplicação por o deste repositório, ou aponte `$system_path` em `index.php` para cá.
2. Mantenha `application/` e a configuração que a aplicação já usa.
3. Chave nova, quando existir, nasce com o comportamento de hoje. Nada precisa ser religado para o request seguir igual.

## Desenvolvimento

```bash
composer install
composer check
```

`composer check` roda PHP_CodeSniffer, PHPStan e PHPUnit em `tests/travis/sqlite.phpunit.xml`. A Action testa PHP 7.4 e 8.5 com MySQL, PostgreSQL e SQLite.

Como contribuir está em [`contributing.md`](contributing.md). O comportamento esperado das frentes de banco e sessão está em [`MAJORIS.md`](MAJORIS.md).

## Licença

MIT. Veja [`license.txt`](license.txt).

O código deriva do CodeIgniter. Copyright (c) 2019–2022, CodeIgniter Foundation, e dos contribuidores anteriores (EllisLab, British Columbia Institute of Technology).
