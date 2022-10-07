FROM ubuntu:22.04

ENV TZ=America/Detroit
ENV MYSQL_ROOT_PASSWORD=12345
ENV DEBIAN_FRONTEND=noninteractive
ENV VUFIND_LOCAL_DIR=/usr/local/vufind/local
ENV VUFIND_HOME=/usr/local/vufind
ENV JAVA_HOME=/usr/lib/jvm/default-java
ENV VUFIND_LOCAL_MODULES=Catalog

ARG VUFIND_VERSION
ENV VUFIND_VERSION=$VUFIND_VERSION

RUN \
    # Perform updates
    apt update && \
    apt install wget vim-nox lsof apache2 xml-twig-tools curl cron rsyslog jq htop moreutils \
        gettext-base locales msmtp-mta rsync \
        # VuFind dependencies; modified to not include php-dev, mysql-server, or Java JDK (using JRE instead)
        mysql-client default-jre-headless apache2 libapache2-mod-php php-pear php php-curl \
        php-gd php-intl php-json php-ldap php-mbstring php-mysql php-soap php-xml -y && \
    # Setup Timezone
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone && \
    # Get the package
    wget https://github.com/vufind-org/vufind/releases/download/v${VUFIND_VERSION}/vufind_${VUFIND_VERSION}.deb && \
    # Install the package
    service apache2 restart && \
    dpkg -i --ignore-depends=php-dev,default-jdk,java-compiler,mysql-server,virtual-mysql-server-core \
        ./vufind_${VUFIND_VERSION}.deb && \
    apt clean && \
    rm vufind_${VUFIND_VERSION}.deb && \
    # Remove unused dependencies after Vufind is installed
    mv /usr/local/vufind/solr /tmp && \
    mkdir -p /usr/local/vufind/solr/vendor/dist && \
    mkdir -p /usr/local/vufind/solr/vendor/contrib/analysis-extras && \
    mv /tmp/solr/vendor/dist/solr* /usr/local/vufind/solr/vendor/dist/ && \
    mv /tmp/solr/vendor/contrib/analysis-extras/lib /usr/local/vufind/solr/vendor/contrib/analysis-extras && \
    rm -rf /tmp/solr && \
    # Create log file and redirect to stdout
    mkdir /var/log/vufind && \
    touch /var/log/vufind/vufind.log && \
    chown -R 33:33 /var/log/vufind

# Build Arguments
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
ARG FEEDBACK_EMAIL
ARG SOLR_URL=http://solr:8983/solr
ARG EDS_USER
ARG EDS_PASS
ARG EDS_PROFILE
ARG EDS_ORG
ARG RECAPTCHA_SITE_KEY
ARG RECAPTCHA_SECRET_KEY

ENV DEBUG=$DEBUG \
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
    FEEDBACK_EMAIL=$FEEDBACK_EMAIL \
    SOLR_URL=$SOLR_URL \
    EDS_USER=$EDS_USER \
    EDS_PASS=$EDS_PASS \
    EDS_PROFILE=$EDS_PROFILE \
    EDS_ORG=$EDS_ORG \
    RECAPTCHA_SITE_KEY=$RECAPTCHA_SITE_KEY \
    RECAPTCHA_SECRET_KEY=$RECAPTCHA_SECRET_KEY

# Setup Apache
RUN echo "ServerName catalog.lib.msu.edu" > /etc/apache2/sites-available/000-default.conf && \
    echo "<VirtualHost *:80>" >> /etc/apache2/sites-available/000-default.conf && \
    echo "    LogLevel warn" >> /etc/apache2/sites-enabled/000-default.conf && \
    echo "    ErrorLog /proc/self/fd/2" >> /etc/apache2/sites-enabled/000-default.conf && \
    echo "    CustomLog /proc/self/fd/1 combined" >> /etc/apache2/sites-enabled/000-default.conf && \
    echo "</VirtualHost>" >> /etc/apache2/sites-enabled/000-default.conf

# Add file customizations
COPY local/ /usr/local/vufind/local
COPY themes/msul /usr/local/vufind/themes/msul
COPY module/Catalog /usr/local/vufind/module/Catalog
COPY startup.sh /startup.sh
COPY startup-cron.sh /startup-cron.sh
COPY harvest-and-import.sh /harvest-and-import.sh
COPY msmtp/aliases /etc/aliases
COPY msmtp/msmtprc /etc/msmtprc

# Setup cron and logging for cron
COPY cron.d/ /etc/cron.d/
RUN chmod -R g-w,o-w /etc/cron.d/ && \
    sed -i "/imklog/d" /etc/rsyslog.conf && \
    sed -i "/PrivDrop/d" /etc/rsyslog.conf && \
    echo "*.*;auth,authpriv.none /proc/1/fd/1" >> /etc/rsyslog.d/50-default.conf

WORKDIR /usr/local/vufind

RUN \
    # Remove core config files that prevent local overrides
    rm import/marc_local.properties && \
    # Replace environment variables in template ini files
    envsubst < local/config/vufind/config.ini | sponge local/config/vufind/config.ini && \
    envsubst < local/config/vufind/Folio.ini | sponge local/config/vufind/Folio.ini && \
    envsubst < local/config/vufind/EDS.ini | sponge local/config/vufind/EDS.ini && \
    envsubst < local/harvest/oai.ini | sponge local/harvest/oai.ini && \
    envsubst < /etc/aliases | sponge /etc/aliases && \
    # Enable module
    sed -i "s/#SetEnv VUFIND_LOCAL_MODULES VuFindLocalTemplate/SetEnv VUFIND_LOCAL_MODULES Catalog/g" ${VUFIND_LOCAL_DIR}/httpd-vufind.conf && \
    # Set required environment variables
    echo JAVA_HOME="$JAVA_HOME" >> /etc/environment && \
    echo VUFIND_HOME="$VUFIND_HOME"  >> /etc/environment && \
    echo VUFIND_LOCAL_DIR="$VUFIND_LOCAL_DIR"  >> /etc/environment

EXPOSE 80

HEALTHCHECK --interval=1m --timeout=10s --start-period=60s --retries=6 \
   CMD curl --fail -s http://localhost:80/vufind || exit 1  

CMD /startup.sh
