ARG VUFIND_VERSION=11.0.1

FROM msulibraries/vufind-extended:$VUFIND_VERSION

ARG VUFIND_VERSION
ENV VUFIND_VERSION=$VUFIND_VERSION
# Install SimpleSAMLphp and ModSecurity
ARG SIMPLESAMLPHP_VERSION
ENV SIMPLESAMLPHP_HOME=/usr/local/simplesamlphp \
    SIMPLESAMLPHP_CUSTOM_DIR=/usr/local/simplesamlphp/local \
    SIMPLESAMLPHP_CONFIG_DIR=/usr/local/simplesamlphp/local/config \
    SIMPLESAMLPHP_VERSION=$SIMPLESAMLPHP_VERSION

RUN wget https://github.com/simplesamlphp/simplesamlphp/releases/download/v${SIMPLESAMLPHP_VERSION}/simplesamlphp-${SIMPLESAMLPHP_VERSION}.tar.gz && \
    tar -C /tmp/ -xzf simplesamlphp-${SIMPLESAMLPHP_VERSION}.tar.gz && \
    mv /tmp/simplesamlphp-${SIMPLESAMLPHP_VERSION} ${SIMPLESAMLPHP_HOME} && \
    rm simplesamlphp-${SIMPLESAMLPHP_VERSION}.tar.gz
COPY apache2/conf-available/ /etc/apache2/conf-available/
COPY apache2/sites-available/ /etc/apache2/sites-available/
COPY modsecurity/ /etc/modsecurity/
COPY libapache2-mod-log-ipmask_1.0.0_amd64.deb /tmp/
COPY simplesamlphp/local/ ${SIMPLESAMLPHP_CUSTOM_DIR}/
RUN envsubst < /etc/apache2/conf-available/simplesamlphp.conf | sponge /etc/apache2/conf-available/simplesamlphp.conf && \
    a2enmod remoteip && \
    a2enconf simplesamlphp.conf && \
    chmod -R a-w ${SIMPLESAMLPHP_CUSTOM_DIR} && \
    # Add VuFind to Apache as well before testing config
    ln -s /usr/local/vufind/local/httpd-vufind.conf /etc/apache2/conf-enabled/vufind.conf && \
    a2enmod rewrite && \
    # Add in modsecurity
    (umask 027; mkdir -p /opt/modsecurity/tmp \
        /opt/modsecurity/var/data \
        /opt/modsecurity/var/upload \
        /opt/modsecurity/var/audit \
        /opt/modsecurity/var/log) && \
    chmod -R g-w,o-rwx /etc/modsecurity && \
    chgrp www-data /opt/modsecurity /opt/modsecurity/var && \
    # modsecurity/var/log/ excluded from chown intentionally (logs are root owned)
    chown -R www-data: /opt/modsecurity/tmp \
        /opt/modsecurity/var/data \
        /opt/modsecurity/var/upload \
        /opt/modsecurity/var/audit && \
    apt-get install -y /tmp/libapache2-mod-log-ipmask_1.0.0_amd64.deb && \
    a2enmod security2 && \
    a2enconf catalog.conf && \
    apachectl configtest

# Build Arguments
ARG SOLR_URL=http://solr:8983/solr

ENV SOLR_URL=$SOLR_URL \
    VUFIND_LOCAL_MODULES=Catalog

# Add file customizations
COPY local/ /usr/local/vufind/local
COPY themes/msul /usr/local/vufind/themes/msul
COPY module/Catalog /usr/local/vufind/module/Catalog
COPY tests/ /tests
COPY composer.local.json /tmp/composer.local.json
COPY tests/.eslintrc.js /usr/local/vufind/.eslintrc.js
COPY robots.txt /usr/local/vufind/public/robots.txt
COPY public/index.php  /usr/local/vufind/public/index.php
COPY build.xml /usr/local/vufind/build.xml
COPY scripts /usr/local/bin
COPY entrypoint.sh startup.sh startup-build.sh startup-cron.sh startup-croncache.sh backup.sh restore.sh /
COPY msmtp/aliases msmtp/msmtprc /etc/

# Setup cron and logging for cron
COPY cron.d/ /etc/cron.d/
COPY croncache.d/ /etc/croncache.d/
RUN chmod -R g-w,o-w /etc/cron.d/ && \
    sed -i "/imklog/d" /etc/rsyslog.conf && \
    sed -i "/PrivDrop/d" /etc/rsyslog.conf && \
    echo "*.*;auth,authpriv.none /proc/1/fd/1" >> /etc/rsyslog.d/50-default.conf && \
    # Fix permissions on scripts
    chmod o-w /usr/local/bin/* && \
    chmod o-w /*.sh

WORKDIR /usr/local/vufind

RUN \
    # Prepare cache directory
    mkdir -p ${VUFIND_CACHE_DIR} && \
    chown -R 33:33 ${VUFIND_CACHE_DIR} && \
    install -d -o 1000 -g 1000 ${VUFIND_CACHE_DIR}/cli && \
    # Install local composer dependencies
    mv /tmp/composer.local.json /usr/local/vufind/composer.local.json && \
    composer --working-dir=/usr/local/vufind/ config audit.block-insecure false && \
    composer --working-dir=/usr/local/vufind/ update && \
    # Remove allfields field (PC-1393)
    sed -i '/^allfields =/d' /usr/local/vufind/import/marc.properties && \
    # Set permissions on local, theme, and Catalog
    chown -R 1000:1000 ${VUFIND_LOCAL_DIR} && \
    chown -R 33:1000 ${VUFIND_HOME}/themes/msul && \
    chown -R 1000:1000 ${VUFIND_HOME}/module/Catalog && \
    rsync -rp --chmod=D2775,F664 ${VUFIND_CACHE_DIR}/ ${VUFIND_CACHE_DIR}/ && \
    rsync -rp --chmod=D2775,F664 ${VUFIND_LOCAL_DIR}/ ${VUFIND_LOCAL_DIR}/ && \
    rsync -rp --chmod=D2775,F664 ${VUFIND_HOME}/themes/msul/ ${VUFIND_HOME}/themes/msul/ && \
    rsync -rp --chmod=D2775,F664 ${VUFIND_HOME}/module/Catalog/ ${VUFIND_HOME}/module/Catalog/ && \
    # Set permissions on Robots.txt
    chown 1000:1000 ${VUFIND_HOME}/public/robots.txt && \
    chmod 664 ${VUFIND_HOME}/public/robots.txt && \
    # Set permissions on build.xml
    chown 1000:1000 ${VUFIND_HOME}/build.xml && \
    chmod 664 ${VUFIND_HOME}/build.xml && \
    # Set permissions on tests dir
    chown -R 1000:1000 /tests && \
    chmod -R 664 /tests && \
    mv /tests/* ${VUFIND_HOME}/tests/ && \
    # Install grunt
    npm install -g grunt-cli && \
    npm run install:scss-dependencies && \
    grunt scss

EXPOSE 80

# --timeout=12s: because 5s solr timeout * 2 attempts + 2s wait between attempts
# --interval=10s: so the checks start sooner after the container starts
# --start-period=90s: so failures before 90s + (3*10s) don't kill the container
#   particularly on devel envs where they need more startup time
# --retries=3: will have 3*10s to recover before killed after healthy
# checking the search results page to make sure solr connection is working and kill
# container if we have a stale solr connection from previous deploy.
HEALTHCHECK --interval=10s --timeout=12s --start-period=90s --retries=3 \
   CMD curl --fail -s http://localhost:80/ || exit 1

ENTRYPOINT [ "/entrypoint.sh" ]
CMD [ "/startup.sh" ]
