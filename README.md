# Speedtest-X-local 部署配置指南

## 功能说明

* **默认 IP 查询 API**：实时调用 IP.sb 接口（`https://api.ip.sb/geoip/`）并输出完整 JSON 数据。
* **回落机制**：当在线 API 无法连接或请求超时时，自动降级切换至本地的 `qqwry.ipdb` 数据库。

---

## 准备工作

请先从 GitHub 获取本地 IP 数据库文件，并将其放置在 `docker-compose.yml` 所在的同级目录下：

* **下载地址**：[nmgliangwei/qqwry.ipdb](https://github.com/nmgliangwei/qqwry.ipdb)
* **放置路径**：`./qqwry.ipdb/qqwry.ipdb`

---

## Docker Compose 部署配置

在项目目录中新建或修改 `docker-compose.yml`：

```yaml
services:
  speedtest:
    image: azurelynn/spt-xl:latest
    container_name: speedtest-x
    restart: always
    ports:
      - "80:80"
    volumes:
      # 1. 挂载日志目录：保存测速历史记录
      - ./speedlogs:/var/www/html/backend/speedlogs
      # 2. 挂载 IP 数据库文件：将本地 qqwry.ipdb 挂载进容器内部 (只读)
      - ./qqwry.ipdb/qqwry.ipdb:/var/www/html/backend/qqwry.ipdb:ro
    environment:
      # PHP 时区设置
      - PHP_DATE_TIMEZONE=Asia/Shanghai
      # 应用配置（可根据需要取消注释并调整）
      # - MAX_LOG_COUNT=100
      # - IP_SERVICE=local
```
# Speedtest-X 部署配置指南

## 功能说明

* **默认 IP 查询 API**：实时调用 IP.sb 接口（`https://api.ip.sb/geoip/`）并输出完整 JSON 数据。
* **回落机制**：当在线 API 无法连接或请求超时时，自动降级切换至本地的 `qqwry.ipdb` 数据库。

---

## 准备工作

请先从 GitHub 获取本地 IP 数据库文件，并将其放置在 `docker-compose.yml` 所在的同级目录下：

* **下载地址**：[nmgliangwei/qqwry.ipdb](https://github.com/nmgliangwei/qqwry.ipdb)
* **放置路径**：`./qqwry.ipdb/qqwry.ipdb`

---

## Docker Compose 部署配置

在项目目录中新建或修改 `docker-compose.yml`：

```yaml
services:
  speedtest:
    image: azurelynn/spt-xl:latest
    container_name: speedtest-x
    restart: always
    ports:
      - "80:80"
    volumes:
      # 1. 挂载日志目录：保存测速历史记录
      - ./speedlogs:/var/www/html/backend/speedlogs
      # 2. 挂载 IP 数据库文件：将本地 qqwry.ipdb 挂载进容器内部 (只读)
      - ./qqwry.ipdb/qqwry.ipdb:/var/www/html/backend/qqwry.ipdb:ro
    environment:
      # PHP 时区设置
      - PHP_DATE_TIMEZONE=Asia/Shanghai
```