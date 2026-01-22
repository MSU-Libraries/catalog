FROM ubuntu:24.04

ENV TZ=America/Detroit \
    DEBIAN_FRONTEND=noninteractive \
    VUFIND_LOCAL_DIR=/usr/local/vufind/local \
    VUFIND_HOME=/usr/local/vufind \
    JAVA_HOME=/usr/lib/jvm/default-java \
    VUFIND_CACHE_DIR=/mnt/vufind_cache

# TEMP(PART1):
# - Patch and replace marc4j due to linked field bug https://github.com/marc4j/marc4j/pull/100
# - Patch and replace solrmarc due to delete logic hardcode to MARC 001 as id instead of .properties defined id
COPY patches/ /tmp/patches/
# END TEMP(PART1)
RUN \
    # Perform updates
    apt-get update && \
    apt-get install -y wget vim-nox lsof apache2 xml-twig-tools curl cron rsyslog jq htop moreutils \
        gettext-base locales msmtp-mta rsync screen libxml-xpath-perl xmlstarlet php-xdebug \
        nodejs npm git pigz libxml2-utils libmarc-xml-perl bash-completion gawk \
        # VuFind dependencies; modified to not include php-dev, mysql-server, or Java JDK (using JRE instead) \
        # We can switch back to default-jre-headless (openjdk-21-jre-headless) instead of openjdk-17-jre-headless \
        # when we remove the patches for marc4j & solrmarc
        mysql-client openjdk-17-jre-headless apache2 libapache2-mod-php php-pear php \
        php-curl php-gd php-intl php-json php-ldap php-mbstring php-mysql php-soap php-xml \
        libapache2-mod-security2 modsecurity-crs uuid-runtime libsaxonhe-java && \
    # We can remove the following line when switching back to default-jre-headless
    cd /usr/lib/jvm && ln -s java-17-openjdk-amd64 default-java && \
    # TEMP(PART2):
    # - Patch and replace marc4j due to linked field bug https://github.com/marc4j/marc4j/pull/100
    # - Patch and replace solrmarc due to delete logic hardcode to MARC 001 as id instead of .properties defined id
    apt-get install -y ant && \
    git clone --depth 1 --branch 2.9.4 https://github.com/marc4j/marc4j.git /tmp/marc4j && \
    cd /tmp/marc4j/ && \
    git apply /tmp/patches/marc4j_fieldMatches_LNK.patch && \
    ant jar && \
    git clone --depth 1 --branch 3.5 https://github.com/solrmarc/solrmarc.git /tmp/solrmarc && \
    cd /tmp/solrmarc/ && \
    git apply /tmp/patches/solrmarc_delete_on_docid.patch && \
    ant package && \
    rm -r /tmp/patches && \
    apt-get purge -y ant && \
    # END TEMP(PART2)
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    (umask 027; mkdir -p /opt/modsecurity/tmp \
        /opt/modsecurity/var/data \
        /opt/modsecurity/var/upload \
        /opt/modsecurity/var/audit \
        /opt/modsecurity/var/log) && \
    a2enmod security2

COPY modsecurity/ /etc/modsecurity/
COPY libapache2-mod-log-ipmask_1.0.0_amd64.deb /tmp/
RUN \
    chmod -R g-w,o-rwx /etc/modsecurity && \
    chgrp www-data /opt/modsecurity /opt/modsecurity/var && \
    # modsecurity/var/log/ excluded from chown intentionally (logs are root owned)
    chown -R www-data: /opt/modsecurity/tmp \
        /opt/modsecurity/var/data \
        /opt/modsecurity/var/upload \
        /opt/modsecurity/var/audit && \
    apt-get install -y /tmp/libapache2-mod-log-ipmask_1.0.0_amd64.deb

ARG VUFIND_VERSION
ENV VUFIND_VERSION=$VUFIND_VERSION

COPY composer.local.json /usr/local/vufind/composer.local.json
COPY install-composer.sh /install-composer.sh

RUN \
    # Setup Timezone
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone && \
    # Install Composer
    /install-composer.sh && \
    mv /composer.phar /usr/local/bin/composer && \
    rm /install-composer.sh && \
    # Get the VuFind tar
    wget https://github.com/vufind-org/vufind/releases/download/v${VUFIND_VERSION}/vufind-${VUFIND_VERSION}.tar.gz && \
    # Install VuFind
    tar -xzvf vufind-${VUFIND_VERSION}.tar.gz -C /usr/local && \
    mv /usr/local/vufind-${VUFIND_VERSION} /usr/local/vufind && \
    rm vufind-${VUFIND_VERSION}.tar.gz && \
    # Install VuFind dev dependencies, for the tests
    composer --working-dir=/usr/local/vufind/ update && \
    # Remove unused Solr dependencies after Vufind is installed
    mv /usr/local/vufind/solr /tmp && \
    mkdir -p /usr/local/vufind/solr/vendor/modules/analysis-extras /usr/local/vufind/solr/vendor/server/solr-webapp/webapp/WEB-INF && \
    mv /tmp/solr/vendor/modules/analysis-extras/lib /usr/local/vufind/solr/vendor/modules/analysis-extras && \
    mv /tmp/solr/vendor/server/solr-webapp/webapp/WEB-INF/lib/ /usr/local/vufind/solr/vendor/server/solr-webapp/webapp/WEB-INF && \
    rm -rf /tmp/solr && \
    # Fix world writable vendor dirs (TODO find out why these happen in the first place)
    find /usr/local/vufind/vendor -type d -perm -o+w -exec chmod o-w \; && \
    # File ownership of node directory
    mkdir -p /usr/local/vufind/node_modules && \
    touch /usr/local/vufind/package-lock.json && \
    chown -R 1000:1000 /usr/local/vufind/node_modules /usr/local/vufind/package-lock.json && \
    # Enable bash completion for all users
    echo ". /usr/share/bash-completion/bash_completion" >> /etc/bash.bashrc && \
    # Remove allfields field (PC-1393)
    sed -i '/^allfields =/d' /usr/local/vufind/import/marc.properties

# TEMP(PART3):
# - Patch and replace marc4j due to linked field bug https://github.com/marc4j/marc4j/pull/100
# - Patch and replace solrmarc due to delete logic hardcode to MARC 001 as id instead of .properties defined id
RUN \
    rm /usr/local/vufind/import/lib/marc4j*.jar && \
    mv /tmp/marc4j/build/marc4j*.jar /usr/local/vufind/import/lib/ && \
    rm -r /tmp/marc4j/ && \
    rm /usr/local/vufind/import/solrmarc_core_3.5.jar && \
    mv /tmp/solrmarc/dist/solrmarc_core_*.jar /usr/local/vufind/import/solrmarc_core_3.5.jar && \
    rm -r /tmp/solrmarc/
# END TEMP(PART3)

# User configs for root
COPY .vimrc /root/.vimrc

USER 1000

# Install requirements for grunt
RUN cd /usr/local/vufind && \
    npm install

USER 0

# Install SimpleSAMLphp
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
COPY simplesamlphp/local/ ${SIMPLESAMLPHP_CUSTOM_DIR}/
RUN envsubst < /etc/apache2/conf-available/simplesamlphp.conf | sponge /etc/apache2/conf-available/simplesamlphp.conf && \
    a2enmod remoteip && \
    a2enconf simplesamlphp.conf && \
    chmod -R a-w ${SIMPLESAMLPHP_CUSTOM_DIR} && \
    # Add VuFind to Apache as well before testing config
    ln -s /usr/local/vufind/local/httpd-vufind.conf /etc/apache2/conf-enabled/vufind.conf && \
    a2enmod rewrite && \
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
