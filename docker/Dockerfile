FROM php:8.5-cli-bookworm
RUN --mount=type=secret,id=proxy_ca \
    cp /etc/ssl/certs/ca-certificates.crt /tmp/original-ca.crt && \
    if [ -f /run/secrets/proxy_ca ]; then cat /run/secrets/proxy_ca >> /etc/ssl/certs/ca-certificates.crt; fi && \
    apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev libxml2-dev && \
    docker-php-ext-install -j2 pdo_mysql bcmath mbstring zip && \
    rm -rf /var/lib/apt/lists/* && \
    mv /tmp/original-ca.crt /etc/ssl/certs/ca-certificates.crt
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /workspace/STOCKFLOW
