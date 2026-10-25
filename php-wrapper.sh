#!/bin/bash
# 通过 podman exec 调用 nexopos-php 容器内的 PHP
podman exec -i nexopos-php php "$@"
