FROM ubuntu:22.04

ENV TZ=America/Detroit
ENV DEBIAN_FRONTEND=noninteractive
ENV VUFIND_LOCAL_DIR=/usr/local/vufind/local
ENV VUFIND_HOME=/usr/local/vufind
ENV JAVA_HOME=/usr/lib/jvm/default-java
ENV VUFIND_CACHE_DIR=/mnt/vufind_cache

# TEMP(PART1): Patch and replace marc4j due to linked field bug https://github.com/marc4j/marc4j/pull/99
COPY patches/ /tmp/patches/
# END TEMP(PART1)
RUN \
    # Perform updates
    apt update && \
    apt install -y wget vim-nox lsof apache2 xml-twig-tools curl cron rsyslog jq htop moreutils \
        gettext-base locales msmtp-mta rsync screen libxml-xpath-perl \
        nodejs npm git pigz libxml2-utils libmarc-xml-perl \
        # VuFind dependencies; modified to not include php-dev, mysql-server, or Java JDK (using JRE instead)
        mysql-client default-jre-headless apache2 libapache2-mod-php php-pear php \
        php-curl php-gd php-intl php-json php-ldap php-mbstring php-mysql php-soap php-xml \
        libapache2-mod-security2 modsecurity-crs && \
    # TEMP(PART2): Patch and replace marc4j due to linked field bug https://github.com/marc4j/marc4j/pull/99
    apt install -y ant && \
    git clone --depth 1 --branch 2.9.4 https://github.com/marc4j/marc4j.git /tmp/marc4j && \
    cd /tmp/marc4j/ && \
    git apply /tmp/patches/marc4j_fieldMatches_LNK.patch && \
    rm -r /tmp/patches && \
    ant jar && \
    apt purge -y ant && \
    # END TEMP(PART2)
    apt clean && \
    rm -rf /var/lib/apt/lists/* && \
    (umask 077; mkdir -p \
        /opt/modsecurity/tmp /opt/modsecurity/var/data \
        /opt/modsecurity/var/upload /opt/modsecurity/var/audit) && \
    a2enmod security2

COPY modsecurity/modsecurity.conf /etc/modsecurity/
COPY libapache2-mod-log-ipmask_1.0.0_amd64.deb /tmp/
RUN apt install -y /tmp/libapache2-mod-log-ipmask_1.0.0_amd64.deb

ARG GITHUB_USER_TOKEN
ARG VUFIND_VERSION
ENV VUFIND_VERSION=$VUFIND_VERSION

COPY install-composer.sh /install-composer.sh

RUN \
    # Setup Timezone
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone && \
    # Install Composer
    /install-composer.sh && \
    mv /composer.phar /usr/local/bin/composer && \
    rm /install-composer.sh && \
    # Get the VuFind package
    wget https://github.com/vufind-org/vufind/releases/download/v${VUFIND_VERSION}/vufind_${VUFIND_VERSION}.deb && \
    # Install the package
    service apache2 restart && \
    dpkg -i --ignore-depends=php-dev,default-jdk,java-compiler,mysql-server,virtual-mysql-server-core \
        ./vufind_${VUFIND_VERSION}.deb && \
    apt clean && \
    rm vufind_${VUFIND_VERSION}.deb && \
    # Update some dependencies
    sed -i 's/"php": "7\.4\.1"/"php": "8.0.0"/' /usr/local/vufind/composer.json && \
    sed -i 's/"laminas\/laminas-diactoros": "2\.17\.0"/"laminas\/laminas-diactoros": "2.18.1"/' /usr/local/vufind/composer.json && \
    sed -i 's/"league\/oauth2-server": "8\.4\.1"/"league\/oauth2-server": "8.4.2"/' /usr/local/vufind/composer.json && \
    # Install VuFind dev dependencies, for the tests
    composer --working-dir=/usr/local/vufind/ update && \
    # Remove unused dependencies after Vufind is installed
    # Disabled for upgrade to Solr 9
    # mv /usr/local/vufind/solr /tmp && \
    # mkdir -p /usr/local/vufind/solr/vendor/dist && \
    # mkdir -p /usr/local/vufind/solr/vendor/contrib/analysis-extras && \
    # mv /tmp/solr/vendor/dist/solr* /usr/local/vufind/solr/vendor/dist/ && \
    # mv /tmp/solr/vendor/contrib/analysis-extras/lib /usr/local/vufind/solr/vendor/contrib/analysis-extras && \
    # rm -rf /tmp/solr && \
    # Create user for grunt
    groupadd -g 1000 msuldevs && \
    useradd msuldevs -u 1000 -g 1000 && \
    mkdir /home/msuldevs && \
    chown 1000:1000 /home/msuldevs

# TEMP(PART3): Patch and replace marc4j due to linked field bug https://github.com/marc4j/marc4j/pull/99
RUN \
    rm /usr/local/vufind/import/lib/marc4j*.jar && \
    mv /tmp/marc4j/build/marc4j*.jar /usr/local/vufind/import/lib/ && \
    rm -r /tmp/marc4j/
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
ARG SIMPLESAMLPHP_SALT
ARG SIMPLESAMLPHP_ADMIN_PW
ENV SIMPLESAMLPHP_HOME=/usr/local/simplesamlphp \
    SIMPLESAMLPHP_CUSTOM_DIR=/usr/local/simplesamlphp/local \
    SIMPLESAMLPHP_CONFIG_DIR=/usr/local/simplesamlphp/local/config \
    SIMPLESAMLPHP_VERSION=$SIMPLESAMLPHP_VERSION \
    SIMPLESAMLPHP_SALT=$SIMPLESAMLPHP_SALT \
    SIMPLESAMLPHP_ADMIN_PW=$SIMPLESAMLPHP_ADMIN_PW

RUN wget https://github.com/simplesamlphp/simplesamlphp/releases/download/v${SIMPLESAMLPHP_VERSION}/simplesamlphp-${SIMPLESAMLPHP_VERSION}.tar.gz && \
    tar -C /tmp/ -xzf simplesamlphp-${SIMPLESAMLPHP_VERSION}.tar.gz && \
    mv /tmp/simplesamlphp-${SIMPLESAMLPHP_VERSION} ${SIMPLESAMLPHP_HOME} && \
    rm simplesamlphp-${SIMPLESAMLPHP_VERSION}.tar.gz
COPY apache2/conf-available/ /etc/apache2/conf-available/
COPY apache2/sites-available/ /etc/apache2/sites-available/
COPY simplesamlphp/local/ ${SIMPLESAMLPHP_CUSTOM_DIR}/
RUN envsubst '${SIMPLESAMLPHP_SALT} ${SIMPLESAMLPHP_ADMIN_PW}' < ${SIMPLESAMLPHP_CONFIG_DIR}/config.php | sponge ${SIMPLESAMLPHP_CONFIG_DIR}/config.php && \
    envsubst < /etc/apache2/conf-available/simplesamlphp.conf | sponge /etc/apache2/conf-available/simplesamlphp.conf && \
    a2enmod remoteip && \
    a2enconf simplesamlphp.conf && \
    chmod -R a-w ${SIMPLESAMLPHP_CUSTOM_DIR} && \
    a2enconf catalog.conf

# Build Arguments
ARG SITE_HOSTNAME
ARG FOLIO_URL
ARG FOLIO_USER
ARG FOLIO_PASS
ARG FOLIO_TENANT
ARG FOLIO_REC_ID
ARG FOLIO_CANCEL_ID
ARG OAI_URL
ARG EMAIL
ARG MAIL_HOST
ARG MAIL_PORT
ARG MAIL_USERNAME
ARG MAIL_PASSWORD
ARG MARIADB_VUFIND_PASSWORD
ARG FEEDBACK_EMAIL
ARG SOLR_URL=http://solr:8983/solr
ARG EDS_USER
ARG EDS_PASS
ARG EDS_PROFILE
ARG EDS_ORG
ARG RECAPTCHA_SITE_KEY
ARG RECAPTCHA_SECRET_KEY
ARG FTP_USER
ARG FTP_PASSWORD
ARG AUTH_FTP_USER
ARG AUTH_FTP_PASSWORD
ARG DEPLOY_KEY
ARG BROWZINE_LIBRARY
ARG BROWZINE_TOKEN
ARG MATOMO_URL
ARG MATOMO_SITE_ID
ARG MATOMO_SEARCHBACKEND_DIMENSION


ENV SITE_HOSTNAME=$SITE_HOSTNAME \
    FOLIO_URL=$FOLIO_URL \
    FOLIO_USER=$FOLIO_USER \
    FOLIO_PASS=$FOLIO_PASS \
    FOLIO_TENANT=$FOLIO_TENANT \
    FOLIO_REC_ID=$FOLIO_REC_ID \
    FOLIO_CANCEL_ID=$FOLIO_CANCEL_ID \
    OAI_URL=$OAI_URL \
    EMAIL=$EMAIL \
    MAIL_HOST=$MAIL_HOST \
    MAIL_PORT=$MAIL_PORT \
    MAIL_USERNAME=$MAIL_USERNAME \
    MAIL_PASSWORD=$MAIL_PASSWORD \
    MARIADB_VUFIND_PASSWORD=$MARIADB_VUFIND_PASSWORD \
    FEEDBACK_EMAIL=$FEEDBACK_EMAIL \
    SOLR_URL=$SOLR_URL \
    EDS_USER=$EDS_USER \
    EDS_PASS=$EDS_PASS \
    EDS_PROFILE=$EDS_PROFILE \
    EDS_ORG=$EDS_ORG \
    RECAPTCHA_SITE_KEY=$RECAPTCHA_SITE_KEY \
    RECAPTCHA_SECRET_KEY=$RECAPTCHA_SECRET_KEY \
    FTP_USER=$FTP_USER \
    FTP_PASSWORD=$FTP_PASSWORD \
    AUTH_FTP_USER=$AUTH_FTP_USER \
    AUTH_FTP_PASSWORD=$AUTH_FTP_PASSWORD \
    DEPLOY_KEY=$DEPLOY_KEY \
    VUFIND_LOCAL_MODULES=Catalog \
    BROWZINE_LIBRARY=$BROWZINE_LIBRARY \
    BROWZINE_TOKEN=$BROWZINE_TOKEN \
    MATOMO_URL=$MATOMO_URL \
    MATOMO_SITE_ID=$MATOMO_SITE_ID \
    MATOMO_SEARCHBACKEND_DIMENSION=$MATOMO_SEARCHBACKEND_DIMENSION


# Add file customizations
COPY local/ /usr/local/vufind/local
COPY themes/msul /usr/local/vufind/themes/msul
COPY module/Catalog /usr/local/vufind/module/Catalog
COPY tests/ /tests
COPY robots.txt /usr/local/vufind/public/robots.txt
COPY build.xml /usr/local/vufind/build.xml
COPY scripts /usr/local/bin
COPY startup.sh /startup.sh
COPY startup-cron.sh /startup-cron.sh
COPY startup-croncache.sh /startup-croncache.sh
COPY harvest-and-import.sh /harvest-and-import.sh
COPY import-marc.sh /usr/local/vufind/import-marc.sh
COPY batch-import-marc.sh /usr/local/vufind/harvest/batch-import-marc.sh
COPY hlm-harvest-and-import.sh /hlm-harvest-and-import.sh
COPY authority-harvest-and-import.sh /authority-harvest-and-import.sh
COPY backup.sh /backup.sh
COPY restore.sh /restore.sh
COPY msmtp/aliases /etc/aliases
COPY msmtp/msmtprc /etc/msmtprc

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
    # Replace environment variables in template ini files
    envsubst < local/config/vufind/config.ini | sponge local/config/vufind/config.ini && \
    envsubst < local/config/vufind/contentsecuritypolicy.ini | sponge local/config/vufind/contentsecuritypolicy.ini && \
    envsubst < local/config/vufind/folio.ini | sponge local/config/vufind/folio.ini && \
    envsubst < local/config/vufind/EDS.ini | sponge local/config/vufind/EDS.ini && \
    envsubst < local/config/vufind/BrowZine.ini | sponge local/config/vufind/BrowZine.ini && \
    envsubst < local/harvest/oai.ini | sponge local/harvest/oai.ini && \
    envsubst < /etc/aliases | sponge /etc/aliases && \
    # Set required environment variables so cron jobs have access to them
    echo JAVA_HOME="$JAVA_HOME" >> /etc/environment && \
    echo VUFIND_HOME="$VUFIND_HOME"  >> /etc/environment && \
    echo VUFIND_LOCAL_DIR="$VUFIND_LOCAL_DIR" >> /etc/environment && \
    echo VUFIND_CACHE_DIR="$VUFIND_CACHE_DIR" >> /etc/environment && \
    echo VUFIND_LOCAL_MODULES="Catalog" >> /etc/environment && \
    echo FTP_USER="$FTP_USER" >> /etc/environment && \
    echo FTP_PASSWORD="$FTP_PASSWORD" >> /etc/environment && \
    echo AUTH_FTP_USER="$AUTH_FTP_USER" >> /etc/environment && \
    echo AUTH_FTP_PASSWORD="$AUTH_FTP_PASSWORD" >> /etc/environment && \
    echo DEPLOY_KEY="$DEPLOY_KEY" >> /etc/environment && \
    echo BROWZINE_LIBRARY="$BROWZINE_LIBRARY" >> /etc/environment && \
    echo BROWZINE_TOKEN="$BROWZINE_TOKEN" >> /etc/environment && \
    echo STACK_NAME="$STACK_NAME" >> /etc/environment && \
    # Prepare cache directory
    mkdir -p ${VUFIND_CACHE_DIR} && \
    chown -R 33:33 ${VUFIND_CACHE_DIR} && \
    # Set permissions on local, theme, and Catalog
    chown -R 1000:1000 ${VUFIND_LOCAL_DIR} && \
    chown -R 33:1000 ${VUFIND_HOME}/themes/msul && \
    chown -R 1000:1000 ${VUFIND_HOME}/module/Catalog && \
    rsync -aip --chmod=D2775,F664 ${VUFIND_CACHE_DIR}/ ${VUFIND_CACHE_DIR}/ && \
    rsync -aip --chmod=D2775,F664 ${VUFIND_LOCAL_DIR}/ ${VUFIND_LOCAL_DIR}/ && \
    rsync -aip --chmod=D2775,F664 ${VUFIND_HOME}/themes/msul/ ${VUFIND_HOME}/themes/msul/ && \
    rsync -aip --chmod=D2775,F664 ${VUFIND_HOME}/module/Catalog/ ${VUFIND_HOME}/module/Catalog/ && \
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
    grunt less

# Debugging; temporary files overriding core libraries
COPY debug/ /usr/local/vufind/

EXPOSE 80

HEALTHCHECK --interval=1m --timeout=40s --start-period=30s --retries=6 \
   CMD curl --fail -s http://localhost:80/ || exit 1

CMD /startup.sh
