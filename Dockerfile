# 使用官方 PHP Apache 镜像作为基础
FROM php:7.4-apache

# 安装必要的 PHP 扩展（如果需要，目前 speedtest-x 主要是原生 PHP）
# RUN docker-php-ext-install bcmath

# 将仓库内所有代码复制到容器的 Web 根目录
COPY . /var/www/html/

# 设置目录权限，确保 www-data 用户可以读取
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 暴露 80 端口
EXPOSE 80

# 启动命令（基础镜像已自带，通常不需要写）
