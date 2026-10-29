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

# Microsoft PHP drivers 5.13.3 support PHP 8.5. Keep download integrity checked.
RUN --mount=type=secret,id=proxy_ca \
    cp /etc/ssl/certs/ca-certificates.crt /tmp/original-ca.crt && \
    if [ -f /run/secrets/proxy_ca ]; then cat /run/secrets/proxy_ca >> /etc/ssl/certs/ca-certificates.crt; fi && \
    curl -fsSL https://packages.microsoft.com/config/debian/12/packages-microsoft-prod.deb -o /tmp/microsoft-prod.deb && \
    dpkg -i /tmp/microsoft-prod.deb && apt-get update && \
    ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 unixodbc-dev && \
    curl -fsSL https://codeload.github.com/microsoft/msphpsql/tar.gz/refs/tags/v5.13.3 -o /tmp/msphpsql.tar.gz && \
    echo '0fa6db7c7df8927ddb816c6184d129026338cfa6fe2721f9db41d91290be2cdc  /tmp/msphpsql.tar.gz' | sha256sum -c - && \
    tar -xzf /tmp/msphpsql.tar.gz -C /tmp && \
    cp -a /tmp/msphpsql-5.13.3/source/shared /tmp/msphpsql-5.13.3/source/sqlsrv/shared && \
    cp -a /tmp/msphpsql-5.13.3/source/shared /tmp/msphpsql-5.13.3/source/pdo_sqlsrv/shared && \
    cd /tmp/msphpsql-5.13.3/source/sqlsrv && phpize && ./configure --enable-sqlsrv && make -j2 && make install && \
    cd /tmp/msphpsql-5.13.3/source/pdo_sqlsrv && phpize && ./configure --with-pdo_sqlsrv && make -j2 && make install && \
    docker-php-ext-enable sqlsrv pdo_sqlsrv && \
    rm -rf /var/lib/apt/lists/* /tmp/msphpsql* /tmp/microsoft-prod.deb && \
    mv /tmp/original-ca.crt /etc/ssl/certs/ca-certificates.crt
