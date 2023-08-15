FROM bitnami/solr:9.1.1

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit
USER root

# Install additional packages, including zk-shell
RUN \
    groupadd -r -g 10001 group10001 && \
    useradd -r -u 10001 -G 0 -g 10001 user10001 && \
    apt update && \
    apt install -y wget vim-nox htop rsyslog cron moreutils gettext-base jq \
                   python3 python3-distutils python3-pip && \
    pip install zk-shell && \
    apt remove -y python3-pip && \
    apt autoremove -y && \
    rm -rf /var/lib/apt/lists/* && \
    echo 'alias solr-zk-shell="zk-shell $SOLR_ZK_HOSTS"' >> /etc/bash.bashrc && \
    (umask 0022; mkdir /.zk_shell) && \
    (umask 0133; touch /.zk_shell/config) && \
    (umask 0177; touch /.zk_shell/history) && \
    touch /test && \
    chown user10001 /test && \
    ls -l /test && \
    chown -R user10001 /.zk_shell

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

ARG VUFIND_VERSION

# Get solr configs and prepare for alphabetical browsing
RUN mkdir /solr_confs && \
    wget https://github.com/vufind-org/vufind/archive/refs/tags/v${VUFIND_VERSION}.tar.gz && \
    tar -xf v${VUFIND_VERSION}.tar.gz  && \
    cp -r /vufind-${VUFIND_VERSION}/solr/vufind/* /solr_confs && \
# We might be able to copy index-alphabetic-browse.sh again from VuFind after 9.1
#    cp /vufind-${VUFIND_VERSION}/index-alphabetic-browse.sh /solr_confs/ && \
#    chmod ugo+x /solr_confs/index-alphabetic-browse.sh && \
    mkdir -p /solr_confs/import/lib && \
#    cp /vufind-${VUFIND_VERSION}/import/browse-indexing.jar /solr_confs/import/ && \
    cp /vufind-${VUFIND_VERSION}/import/lib/marc4j-*.jar /solr_confs/import/lib/ && \
#    cp /vufind-${VUFIND_VERSION}/import/solrmarc_core*.jar /solr_confs/import/ && \
    rm v${VUFIND_VERSION}.tar.gz && \
    rm -rf /vufind-${VUFIND_VERSION} && \
    cp -r /opt/bitnami/solr/modules/analysis-extras/lib/* /opt/bitnami/solr/server/solr-webapp/webapp/WEB-INF/lib/ && \
# Let user10001 user modify biblio config to update collection name to bilio9
    chown user10001 /solr_confs/biblio/conf/solrconfig.xml && \
    ls -l /solr_confs/biblio/conf/

# Setup cron and logging for cron
COPY cron.d/ /etc/cron.d/
RUN chmod -R g-w,o-w /etc/cron.d/ && \
    sed -i "/imklog/d" /etc/rsyslog.conf && \
    sed -i "/PrivDrop/d" /etc/rsyslog.conf && \
    echo "*.*;auth,authpriv.none /proc/1/fd/1" >> /etc/rsyslog.d/50-default.conf

COPY startup.sh /startup.sh
COPY startup-cron.sh /startup-cron.sh
COPY healthcheck.sh /healthcheck.sh
COPY clusterhealth.sh /clusterhealth.sh
COPY cron-alphabrowse.sh /cron-alphabrowse.sh

HEALTHCHECK --interval=1m --timeout=10s --start-period=180s --retries=6 \
  CMD /healthcheck.sh

COPY index-alphabetic-browse.sh /solr_confs/
# Custom browse-handler.jar and browse-indexing.jar should not be needed after VuFind 9.1
COPY browse-handler.jar /solr_confs/jars/browse-handler.jar
COPY browse-indexing.jar solrmarc_core.jar /solr_confs/import/
COPY biblio_solrconfig.xml /solr_confs/biblio/conf/solrconfig.xml
RUN chmod go-w /solr_confs/index-alphabetic-browse.sh /solr_confs/import/*.jar /solr_confs/jars/*.jar \
        /solr_confs/biblio/conf/solrconfig.xml && \
    chmod ugo+x /solr_confs/index-alphabetic-browse.sh && \
    chown -R user10001 /solr_confs/index-alphabetic-browse.sh /solr_confs/import /solr_confs/jars \
        /solr_confs/biblio/conf/solrconfig.xml && \
    ln -s /solr_confs/import /opt/bitnami/solr/import && \
    ls -l /solr_confs/jars/

USER user10001:root

COPY alpha-browse.sh /alpha-browse.sh
COPY lock-state.sh /lock-state.sh

RUN ls -l /test

# Run start-up script
CMD ["/startup.sh", "/opt/bitnami/scripts/solr/run.sh"]
