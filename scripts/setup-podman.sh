#!/usr/bin/env bash
# ====================================================================
# NexoPOS 容器一键初始化脚本 (Armbian / Linux)
#
# 用法:
#   首次部署:  bash scripts/setup-podman.sh
#   重建环境:  bash scripts/setup-podman.sh --reset
#   仅启动:    bash scripts/setup-podman.sh --up
#   仅停止:    bash scripts/setup-podman.sh --down
# ====================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()    { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }

# ---- 检查 podman ----
check_podman() {
    if ! command -v podman &> /dev/null; then
        error "未检测到 podman，请先安装 (Armbian: apt install podman podman-compose)"
    fi
    if ! podman-compose version &> /dev/null; then
        error "未检测到 podman-compose 插件，请安装: apt install podman-compose"
    fi
    info "Podman 版本: $(podman --version)"
}

# ---- 检查 env ----
check_env() {
    if [ ! -f .env ]; then
        info "复制 .env.podman → .env"
        cp .env.podman .env
    fi
}

# ---- 构建 & 启动 ----
build_and_up() {
    info "构建镜像 & 启动容器..."
    podman-compose up -d --build
    info "等待 MariaDB 就绪..."
    podman-compose exec -T db mariadb-admin ping -h localhost --wait --retry=30 --interval=2 > /dev/null 2>&1 || true
}

# ---- Laravel 初始化 ----
laravel_init() {
    info "安装 Composer 依赖..."
    podman-compose exec -T php composer install --no-interaction --prefer-dist --optimize-autoloader

    info "安装 Node 依赖 & 构建前端..."
    podman-compose exec -T php npm install --no-audit --no-fund
    podman-compose exec -T php npm run build

    info "准备 storage 权限..."
    podman-compose exec -T php sh -c "chmod -R 775 storage bootstrap/cache || true"

    info "生成应用密钥..."
    podman-compose exec -T php php artisan key:generate --force

    info "创建 SQLite 文件（如果没装 MySQL 或想用 SQLite）..."
    podman-compose exec -T php sh -c "touch database/database.sqlite" || true

    info "运行数据库迁移 + 填充..."
    podman-compose exec -T php php artisan migrate --force
    podman-compose exec -T php php artisan db:seed --force || true

    info "NexoPOS 安装器..."
    podman-compose exec -T php php artisan ns:translate --symlink --force || true
    podman-compose exec -T php php artisan modules:symlink || true
}

# ---- 重置 ----
reset_env() {
    warn "这将删除所有数据卷和容器，继续吗? [y/N]"
    read -r confirm
    [ "$confirm" = "y" ] || exit 0

    info "停止 & 移除容器 + 卷..."
    podman-compose down -v --remove-orphans
    podman rmi nexopos-php:dev 2>/dev/null || true

    info "清理 Laravel 缓存..."
    rm -rf bootstrap/cache/*.php storage/logs/*.log

    info "完成。重新运行此脚本即可重建。"
    exit 0
}

# ---- 显示状态 ----
show_status() {
    echo ""
    info "=== 容器状态 ==="
    podman-compose ps
    echo ""
    info "=== 访问地址 ==="
    local ip
    ip=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "<服务器IP>")
    echo "  浏览器:        http://${ip}:8080"
    echo "  直接 exec:     podman-compose exec php bash"
    echo "  Artisan:       podman-compose exec php php artisan <cmd>"
    echo "  Composer:      podman-compose exec php composer <cmd>"
    echo "  跑测试:        podman-compose exec php php artisan test --compact"
}

# ---- 参数解析 ----
main() {
    local action="${1:-all}"

    case "$action" in
        --reset)   reset_env ;;
        --down)    info "停止容器..." && podman-compose down && exit 0 ;;
        --up)      check_podman && check_env && podman-compose up -d && show_status; exit 0 ;;
        all)       ;;
        *)         error "未知参数: $action  (可选: --reset, --down, --up)" ;;
    esac

    check_podman
    check_env
    build_and_up
    laravel_init
    show_status
}

main "${1:-all}"
