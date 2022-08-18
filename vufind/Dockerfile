FROM ubuntu:22.04

ENV TZ=America/Detroit
# TODO upgrade to latest version
ENV VUFIND_VERSION=8.0.4
ENV MYSQL_ROOT_PASSWORD=12345

ENV DEBIAN_FRONTEND=noninteractive
ENV VUFIND_LOCAL_DIR=/usr/local/vufind/local
ENV VUFIND_HOME=/usr/local/vufind
ENV JAVA_HOME=/usr/lib/jvm/default-java

RUN \
    # Perform updates
    apt update && \
    apt install wget vim lsof apache2 xml-twig-tools curl cron rsyslog jq htop moreutils -y && \
    # Setup Timezone
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone && \
    # Get the package
    wget https://github.com/vufind-org/vufind/releases/download/v${VUFIND_VERSION}/vufind_${VUFIND_VERSION}.deb && \
    # Install the package
    service apache2 restart && \
    apt install ./vufind_${VUFIND_VERSION}.deb -y && \
    apt clean

# Build Arguments
ARG DEBUG=false
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
ARG SOLR_URL=http://solr:8983/solr
ARG EDS_USER
ARG EDS_PASS
ARG EDS_PROFILE
ARG EDS_ORG

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
    SOLR_URL=$SOLR_URL \
    EDS_USER=$EDS_USER \
    EDS_PASS=$EDS_PASS \
    EDS_PROFILE=$EDS_PROFILE \
    EDS_ORG=$EDS_ORG

# Setup Apache
RUN echo "ServerName catalog.lib.msu.edu" >> /etc/apache2/sites-available/000-default.conf && \
    echo "LogLevel warn" >> /etc/apache2/sites-enabled/000-default.conf && \
    echo "ErrorLog /proc/self/fd/2" >> /etc/apache2/sites-enabled/000-default.conf && \
    echo "CustomLog /proc/self/fd/1 combined" >> /etc/apache2/sites-enabled/000-default.conf

# Add file customizations
COPY local/ /usr/local/vufind/local
COPY themes/msul /usr/local/vufind/themes/msul
COPY startup.sh /startup.sh
COPY startup-cron.sh /startup-cron.sh
COPY harvest-and-import.sh /harvest-and-import.sh

# Setup cron and logging for cron
COPY cron.d/ /etc/cron.d/
RUN chmod -R g-w,o-w /etc/cron.d/ && \
    sed -i "/imklog/d" /etc/rsyslog.conf

WORKDIR /usr/local/vufind

RUN \
    # Remove core config files that prevent local overrides
    rm import/marc_local.properties && \
    # Replace environment variables in template ini files
    cat local/config/vufind/config.ini | envsubst | sponge local/config/vufind/config.ini && \
    cat local/config/vufind/Folio.ini | envsubst | sponge local/config/vufind/Folio.ini && \
    cat local/config/vufind/EDS.ini | envsubst | sponge local/config/vufind/EDS.ini && \
    cat local/harvest/oai.ini | envsubst | sponge local/harvest/oai.ini && \
    # Enable debug mode
    if [ "$DEBUG" = 1 ] ; \
    then sed -i "/SetEnv VUFIND_ENV development/s/#//g" ${VUFIND_LOCAL_DIR}/httpd-vufind.conf; fi && \
    # TODO: Hopefully can fix this in the Folio driver.
    # Had to change the id from 1 to a real id in folio to get the getIlsStatus call working.
    # We could override this function in the driver to switch the call it makes to something less
    # dependent on an ID.
    sed -i "/getStatus/s/1/${FOLIO_REC_ID}/g" ${VUFIND_HOME}/module/VuFind/src/VuFind/ILS/Connection.php && \
    # Set required environment variables
    echo JAVA_HOME="$JAVA_HOME" >> /etc/environment && \
    echo VUFIND_HOME="$VUFIND_HOME"  >> /etc/environment && \
    echo VUFIND_LOCAL_DIR="$VUFIND_LOCAL_DIR"  >> /etc/environment

EXPOSE 80

CMD /startup.sh
