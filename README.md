改了下
ip.sb我这失效了
ipinfo.io要钱
所以用本地的qqway.ipdb

这里下载：https://github.com/nmgliangwei/qqwry.ipdb

解析类库用的是这个（不用下载）：
Reader.php
https://github.com/ipipdotnet/ipdb-php

使用方式，用docker-compose：
> ```
>services:
>  speedtest:
>    image: azurelynn/spt-xl:latest
>    container_name: speedtest-x
>    restart: always
>    ports:
>      - "80:80"
>    volumes:
>      - ./data:/var/www/html/backend/data
>      - ./qqwry.ipdb:/var/www/html/backend/qqwry.ipdb:ro  #把下载好的qqwry.ipdb放到docker-compose.yml同样目录
>    environment:
>      - MAX_LOG_COUNT=100        # 最大保留的测速记录数量（可选）
>      - IP_SERVICE=local         # 本地 IP 归属地查询服务
> ```

===============================================================================================================


