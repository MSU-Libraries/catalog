FROM bitnami/solr:9.1.1

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit
USER root

# Install additional packages, including zk-shell
RUN \
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
    chown -R 1001 /.zk_shell

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

ARG VUFIND_VERSION

# Get solr configs and prepare for alphabetical browsing
RUN mkdir -p /solr_confs/jars && \
    wget https://github.com/vufind-org/vufind/archive/refs/tags/v${VUFIND_VERSION}.tar.gz && \
    tar -xf v${VUFIND_VERSION}.tar.gz  && \
    cp -r /vufind-${VUFIND_VERSION}/solr/vufind/* /solr_confs && \
# We might be able to copy index-alphabetic-browse.sh again from VuFind after 9.1
#    cp /vufind-${VUFIND_VERSION}/index-alphabetic-browse.sh /solr_confs/ && \
    cp /vufind-${VUFIND_VERSION}/import/lib/marc4j-*.jar /solr_confs/jars/ && \
    rm v${VUFIND_VERSION}.tar.gz && \
    rm -rf /vufind-${VUFIND_VERSION} && \
    cp /opt/bitnami/solr/modules/analysis-extras/lib/* /solr_confs/jars/

# Setup cron and logging for cron
COPY cron.d/ /etc/cron.d/
RUN chmod -R g-w,o-w /etc/cron.d/ && \
    sed -i "/imklog/d" /etc/rsyslog.conf && \
    sed -i "/PrivDrop/d" /etc/rsyslog.conf && \
    echo "*.*;auth,authpriv.none /proc/1/fd/1" >> /etc/rsyslog.d/50-default.conf

COPY startup.sh startup-cron.sh healthcheck.sh clusterhealth.sh cron-alphabrowse.sh alpha-browse.sh lock-state.sh /

# Alphabetic browse and indexing
# Custom browse-handler.jar, browse-indexing.jar and solrmarc_core.jar should not be needed after VuFind 9.1
# (they will have to be copied from vufind like marc4j-*.jar into /solr_confs/jars/)
COPY index-alphabetic-browse.sh /solr_confs/
COPY browse-handler.jar browse-indexing.jar solrmarc_core.jar /solr_confs/jars/
COPY biblio_solrconfig.xml /solr_confs/biblio/conf/solrconfig.xml
RUN chmod -R go-w /solr_confs /*.sh && \
    chmod ugo+x /*.sh /solr_confs/*.sh && \
# Note: solrconfig.xml is also edited (conditionally) in startup.sh
    chown 1001:root /solr_confs && \
    # Remove VuFind-specific jar paths (we copied these jars into /solr_confs/jars/)
    sed -i 's/<lib dir="\.\.\/\.\.\/\.\.\/import[^>]*>//g' /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i 's/<lib dir="\.\.\/\.\.\/\.\.\/vendor[^>]*>//g' /solr_confs/biblio/conf/solrconfig.xml && \
    # Fix path for /solr_confs/jars
    sed -i 's/<lib dir="\.\.\/jars"/<lib dir="\/solr_confs\/jars"/' /solr_confs/biblio/conf/solrconfig.xml

HEALTHCHECK --interval=1m --timeout=10s --start-period=180s --retries=6 \
  CMD /healthcheck.sh

USER 1001

# Run start-up script
CMD ["/startup.sh", "/opt/bitnami/scripts/solr/run.sh"]
