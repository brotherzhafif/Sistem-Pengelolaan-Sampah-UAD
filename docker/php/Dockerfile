FROM node:22-bookworm-slim AS node

FROM php:8.3-fpm-bookworm

ARG HOST_UID=1000
ARG HOST_GID=1000

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip curl bash \
 && rm -rf /var/lib/apt/lists/* \
 && install-php-extensions @composer pdo_mysql gd zip intl bcmath exif pcntl opcache

# Node + npm (Breeze & Vite butuh ini di dalam container app)
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

RUN groupadd -o -g ${HOST_GID} app \
 && useradd -o -u ${HOST_UID} -g app -m -s /bin/bash app \
 && mkdir -p /var/www/html && chown app:app /var/www/html

COPY local.ini /usr/local/etc/php/conf.d/zz-local.ini
COPY --chmod=0755 entrypoint.sh /usr/local/bin/entrypoint.sh
# jaga-jaga kalau file ke-save CRLF dari Windows
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
USER app

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
