# NexoPOS 多店铺开发约束（Claude Code 必读）

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
- 全局软下架：`nexopos_products.is_active = false`，不删记录
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
