FROM docker.io/php:8.1-apache AS base

COPY apache-vhost.conf /etc/apache2/sites-available/app.conf
RUN \
    chmod 0444 /etc/apache2/sites-available/app.conf && \
    a2enmod rewrite && \
    a2dissite 000-default.conf && \
    a2ensite app.conf

WORKDIR /var/www/html/

HEALTHCHECK --interval=10s --timeout=30s --retries=1 --start-period=5s \
    CMD curl --fail http://localhost/ping

################################################################################

FROM base AS build

RUN mkdir web
COPY index.php web/
COPY app.ini .
RUN chmod -R u=rwX,go=rX .
