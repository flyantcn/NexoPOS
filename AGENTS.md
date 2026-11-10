# NexoPOS 多店铺开发约束（DSH/AGENTS.md必读）

## 🚨 三条最致命铁律（违反会导致 500 或数据事故）

1. 所有 artisan 命令必须带 `-u www-data -T`，否则生成 root 属主文件 → 500。
2. 改代码后必须重启 `queue-worker`，否则队列执行旧代码。
3. 严禁触碰 `/opt/nexopos-production`、FCC 8082、LiteLLM 4000、生产 8080。

## 运行环境
- 主机：Lubuntu 192.168.1.51，用户 cui，rootless Podman
- 开发目录：/home/cui/nexopos-dev
- Compose（必须两个文件叠加，缺 override 会丢 redis 端口映射）：
  `podman-compose -p nexopos-dev -f podman-compose.dev.yml -f compose.override.lubuntu.yml`
- Web 端口 8081（生产 8080，严禁混用）
- 开发 Nginx：挂载 docker/nginx.conf → 容器内 /etc/nginx/conf.d/default.conf，fastcgi_pass php:9000（走容器网络）
- ⚠️ `docker/nginx.dev.conf` **已废弃**（当前指向 `host.containers.internal:9001`，误挂会直接 502），不要使用
- 生产 Nginx：/opt/nexopos-production/docker/nginx.conf（prod 目录内独立的文件，内容含 $php_upstream+resolver ），仅查看不修改
- 生产目录 /opt/nexopos-production、FCC 8082、LiteLLM 4000 —— 严禁触碰

## 容器名与网络（dev）

- 网络：`nexopos-net`（bridge）
- `nexopos-php`：容器内服务名 `php`，监听 9000
- `nexopos-nginx`：宿主 8081 → 容器 80，挂载 `./docker/nginx.conf → /etc/nginx/conf.d/default.conf:ro`，`fastcgi_pass php:9000`
- `nexopos-db`：宿主 3307 → 容器 3306（MariaDB 11，库 `nexopos_dev`，用户 `nexopos` / 密码 `nexopos_secret`，root 密码 `root_secret`）
- `nexopos-redis`：宿主 6380 → 容器 6379
- `nexopos-worker`：队列 worker（`php artisan queue:work`），改代码后必须 `podman-compose ... restart queue-worker`

## 铁律
1. 所有 artisan 命令必须带 `-u www-data -T`：
   `podman-compose -p nexopos-dev -f podman-compose.dev.yml -f compose.override.lubuntu.yml exec -u www-data -T php php artisan <cmd>`

2. 权限修复（rootless 下 `chown` 无效，必须用 `podman unshare chmod`）：
   ```bash
   podman unshare chmod -R 777 ~/nexopos-dev/storage ~/nexopos-dev/bootstrap/cache ~/nexopos-dev/database ~/nexopos-dev/config
   podman unshare chmod 0755 ~/nexopos-dev/public ~/nexopos-dev/public/modules ~/nexopos-dev/public/modules-lang
   
   

容器 `up -d` / `restart` 后必做一次。

1. 模块符号链接（宿主机手动 `ln -s`，不要用 `modules:symlink`，它在 rootless 下会因权限失败）：

``` bash
   cd ~/nexopos-dev
   rm -f public/modules/nsmultistore
   ln -s /var/www/html/modules/NsMultiStore/Public public/modules/nsmultistore
   podman unshare chmod 0755 public/modules
```




## .env 关键配置（dev）

必须满足，否则会 419 / `Session store not set on request` 500：

dotenv

```
APP_URL=http://192.168.1.51:8081
SESSION_DOMAIN=192.168.1.51
SANCTUM_STATEFUL_DOMAINS=192.168.1.51:8081,127.0.0.1:8081,localhost:8081
```



## 数据库凭据（dev）

- 容器内部：`DB_HOST=db`、`DB_PORT=3306`、`DB_DATABASE=nexopos_dev`、`DB_USERNAME=nexopos`、`DB_PASSWORD=nexopos_secret`
- 宿主机映射端口：3307（仅工具连用）
- root 密码：`root_secret`

## 模块系统

- 模块：NsMultiStore（已启用）
- 迁移自动发现：`NsMultiStoreServiceProvider::boot()` 已 `loadMigrationsFrom(__DIR__ . '/../Migrations')`，新增迁移后 `php artisan migrate` 即可，无需 `--path`
- 路由注册：`Route::middleware('api')->prefix('api')->group(__DIR__ . '/../Routes/api.php')`
  - `Routes/api.php` 内不要再写 `'api'` 前缀
- 控制台命令：`SyncGlobalProductsCommand`（`ns-multistore:sync-global-products`）

## 作用域注册

- `Product`：`NsMultiStoreServiceProvider::boot()` 中手动 `Product::addGlobalScope(app(StoreScope::class))`
- 会员及其他模型：通过 `StoreScoped` trait 自动挂载
- `StoreScope` 对 `nexopos_products` 放行 `store_id IS NULL`（全局商品池）

## ⚠️ StoreScope 必须保留 bound() 检查

`modules/NsMultiStore/Scopes/StoreScope.php` 第 32 行**已经改成**：

``` php
$storeIds = app()->bound( 'visible_store_ids' ) ? app( 'visible_store_ids' ) : [];
```



不要改回 `app( 'visible_store_ids' )`——CLI / 未登录场景（`Helper::installed()` 为 false，模块 provider 不加载）会抛 `BindingResolutionException`。

## ⚠️ Customer 模型的 deleted_at

`Customer` 用了 `SoftDeletes`，`deleted_at` 列已确认存在（`nexopos_users.deleted_at`，`timestamp NULL`），`migrations` 表有记录。补齐迁移文件：`database/migrations/update/2026_11_06_000000_add_soft_deletes_to_users_table.php`（带 `Schema::hasColumn` 幂等检查）。**不要删除这个迁移**，否则 dashboard 会 500。

⚠️ 该迁移位于 `database/migrations/update/` 子目录，Laravel 默认不扫描子目录，`migrate:status` 看不到它——属正常现象，不代表未执行。如需重跑或新增子目录迁移，必须显式带路径：
`php artisan migrate --path=database/migrations/update/<文件名>.php --force`

## 商品隔离业务规则

- 全局商品池：`nexopos_products.store_id IS NULL`
- 门店本地商品：`store_id = N`
- 门店上架目录：`nexopos_store_products`（`is_active` 控上下架、`quantity` 门店库存、`price_override` 可空）
- 门店下架：只改 `nexopos_store_products.is_active = false`
- 全局软下架：`nexopos_products.status = 'unavailable'`，不删记录
- 库存流水：`nexopos_products_histories` 带 `store_id`
- 店铺类型：single/franchise 强隔离，chain 通过 `computeVisibleStoreIds()` 聚合

## 队列 worker

改代码后重启 worker，否则加载旧代码：


```bash
podman-compose -p nexopos-dev -f podman-compose.dev.yml -f compose.override.lubuntu.yml restart queue-worker
```



## Git 纪律

- `master @ 108b9ced6` 是迁移完成时的**稳定快照**（同时有 tag `codebuddy-backup-*`），可作为回滚基线
- 严禁 `git reset --hard` / `git clean` 到 **`232231b74`（多店铺模块起始提交）之前**的提交
- 只 `git add` 明确改过的文件，**禁止 `git add -A` / `git add .`**（`chmod` 产生的 mode 变化会带进来）
- 若 `git status` 突然显示几百个 modified，先跑 `git config core.fileMode false`
- 不要删除 `storage/framework/*/.gitignore` 和 `storage/logs/.gitignore`（删了会导致缓存目录被误提交）
- `docker/nginx.conf` 不能被 `.gitignore` 忽略（compose 挂载的必需文件）
- 新功能从 `feature/*` 分支开发

## Agent 操作纪律
- 启动/重启/停止容器前，先说明意图并等用户确认
- 跑任何 `migrate`、`db:seed`、`migrate:fresh`、`db:wipe` 前，必须等用户明确批准
- 读取数据库只读查询（SELECT/DESCRIBE）无需确认


## 测试环境初始化（sqlite）

测试库 `tests/database.sqlite` 不在 git 中，需手动重建：

1. 建 `.env.testing`（DB_CONNECTION=sqlite、DB_DATABASE=/var/www/html/tests/database.sqlite、DB_PREFIX=ns_）
2. `podman exec -e APP_ENV=testing -e DB_CONNECTION=sqlite -e ... php artisan migrate --path=database/migrations/core`
3. 同参数 `migrate --path=database/migrations/create`
4. 同参数 `migrate --path=database/migrations/update`
5. 同参数 `migrate --path=modules/NsMultiStore/Migrations`（**不要用 modules:migrate，有 ucwords bug**）
6. 手动插入 admin 用户（用 tinker + forceFill + assignRole）
7. 跑测试

注意：`podman-compose exec` 的环境变量会被 compose 的 `environment` 覆盖，
必须用 `podman exec -e ...` 显式传入，否则会连到 dev MySQL。


## 测试

合并前必须通过多店铺测试：


```bash
podman-compose -p nexopos-dev -f podman-compose.dev.yml -f compose.override.lubuntu.yml \
  exec -u www-data -T php php ./vendor/bin/phpunit -c tests/phpunit.multistore.xml
```



## E2E 注意事项

dev（8081）和 prod（8080）同 IP，浏览器 `XSRF-TOKEN` 按域名存不按端口，两个应用会互相覆盖。跑 E2E 时不要同时开 dev 和 prod 标签页，或 dev 用无痕窗口。

## prod 部署流程（如需上线）

**永远 dev → 仓库 → prod，不要 `rsync` dev 到 prod**：

```bash
# dev 上：改代码 → git add 明确文件 → commit → push
# prod 上：
cd /opt/nexopos-production
cp .env /tmp/prod.env.bak.$(date +%F_%H%M%S)
git pull
podman-compose -p nexopos-production -f podman-compose.prod.yml exec -u www-data -T php composer install --no-dev 2>&1 | tail -5
podman-compose -p nexopos-production -f podman-compose.prod.yml exec -u www-data -T php php artisan migrate --force
podman restart nexopos-prod-php nexopos-prod-nginx
curl -I -s http://192.168.1.51:8080 | head -1
```



**prod 上禁止**：`down`、`down -v`、`ns:reset`、`migrate:fresh`、`db:seed`、直接改业务代码。
<laravel-boost-guidelines>





=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v12
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- laravel/telescope (TELESCOPE) - v5
- phpunit/phpunit (PHPUNIT) - v11
- laravel/boost (BOOST) - v2
- laravel/pint (PINT) - v1
- vue (VUE) - v3
- laravel-echo (ECHO) - v2
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

For any work touching NexoPOS modules, especially files under `modules/`, module routes, providers, listeners, settings, CRUD, permissions, migrations, POS integrations, frontend assets, Vue/TypeScript module code, menus, widgets, view injection, or module tests, you MUST load and apply the `create-nexopos-module` skill before reading or editing implementation files. Treat this as mandatory even for small fixes and convention questions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

</laravel-boost-guidelines>
