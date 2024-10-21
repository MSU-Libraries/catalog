FROM bitnami/solr:9.6.1

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit
ENV BITNAMI_DEBUG=true
ARG VUFIND_VERSION

USER root

RUN \
# Install additional packages, including zk-shell
    apt update && \
    apt install -y wget vim-nox htop rsyslog cron moreutils gettext-base jq \
                   python3 python3-distutils python3-pip pipx && \
    pipx install zk-shell && \
    pipx ensurepath && \
    apt remove -y python3-pip && \
    apt autoremove -y && \
    rm -rf /var/lib/apt/lists/* && \
    (umask 0022; mkdir /.zk_shell) && \
    (umask 0133; touch /.zk_shell/config) && \
    (umask 0177; touch /.zk_shell/history) && \
    chown -R 1001 /.zk_shell && \
# Setup Timezone
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

# Setup cron and logging for cron
COPY cron.d/ /etc/cron.d/
RUN chmod -R g-w,o-w /etc/cron.d/ && \
    sed -i "/imklog/d" /etc/rsyslog.conf && \
    sed -i "/PrivDrop/d" /etc/rsyslog.conf && \
    echo "*.*;auth,authpriv.none /proc/1/fd/1" >> /etc/rsyslog.d/50-default.conf

# Get solr configs and prepare for alphabetical browsing
COPY startup.sh startup-cron.sh healthcheck.sh clusterhealth.sh cron-alphabrowse.sh alpha-browse.sh lock-state.sh /
RUN \
    mkdir -p /solr_confs/import/lib && \
    wget https://github.com/vufind-org/vufind/archive/refs/tags/v${VUFIND_VERSION}.tar.gz && \
    tar -xf v${VUFIND_VERSION}.tar.gz  && \
    cp -r /vufind-${VUFIND_VERSION}/solr/vufind/* /solr_confs/ && \
    cp /vufind-${VUFIND_VERSION}/import/lib/marc4j-*.jar /vufind-${VUFIND_VERSION}/import/lib/reload4j-*.jar /solr_confs/import/lib/ && \
    rm v${VUFIND_VERSION}.tar.gz && \
    cp /vufind-${VUFIND_VERSION}/import/*.jar /solr_confs/import/ && \
    rm -rf /vufind-${VUFIND_VERSION}
COPY index-alphabetic-browse.sh /solr_confs/
COPY series_browse.txt /tmp/
RUN \
    chmod -R go-w /solr_confs /*.sh && \
    chmod ugo+x /*.sh /solr_confs/*.sh && \
    # Change solr_confs ownership so that a biblio9 copy can be created (conditionally) in startup.sh
    chown 1001:root /solr_confs && \
    # Empty stopwords.txt
    > /solr_confs/biblio/conf/stopwords.txt && \
    # Fix lib paths in solrconfig.xml
    # Note: the biblio9 copy of solrconfig.xml is also edited in startup.sh
    sed -i 's/<lib dir="\.\.\/\.\.\/\.\.\/import\/lib"/<lib dir="\/solr_confs\/import\/lib"/' /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i 's/<lib dir="\.\.\/\.\.\/\.\.\/import"/<lib dir="\/solr_confs\/import"/' /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i 's/<lib dir="\.\.\/\.\.\/vendor\/modules\/analysis-extras\/lib"/<lib dir="\/opt\/bitnami\/solr\/modules\/analysis-extras\/lib"/' \
        /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i 's/<lib dir="\.\.\/jars"/<lib dir="\/solr_confs\/jars"/' /solr_confs/biblio/conf/solrconfig.xml && \
    # Add series browse request handler PC-110
    sed -i '/hierarchy<\/str>/ r /tmp/series_browse.txt' /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i 's/topic,author,title,lcc,dewey,hierarchy/topic,author,title,lcc,dewey,hierarchy,series/' /solr_confs/biblio/conf/solrconfig.xml && \
    # Update the field used for alpha browse PC-686, PC-110
    sed -i 's/title_fullStr/title_browse/' /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i '$ d' /solr_confs/biblio/conf/schema.xml && \
    echo ' <!-- CopyFields for Title Browse -->' >> /solr_confs/biblio/conf/schema.xml && \
    echo ' <field name="title_browse" type="string" indexed="true" stored="true" multiValued="true"/>' >> /solr_confs/biblio/conf/schema.xml && \
    echo ' <copyField source="title_fullStr" dest="title_browse"/>' >> /solr_confs/biblio/conf/schema.xml && \
    echo ' <copyField source="title_alt" dest="title_browse"/>' >> /solr_confs/biblio/conf/schema.xml && \
    echo ' <copyField source="title_auth" dest="title_browse"/>' >> /solr_confs/biblio/conf/schema.xml && \
    echo '</schema>' >> /solr_confs/biblio/conf/schema.xml && \
    echo ' <field name="title_browse_sort" type="string" indexed="true" stored="true" multiValued="true"/>' >> /solr_confs/biblio/conf/schema.xml && \
    echo ' <copyField source="title_sort" dest="title_browse_sort"/>' >> /solr_confs/biblio/conf/schema.xml && \
    echo ' <copyField source="title_alt_sort" dest="title_browse_sort"/>' >> /solr_confs/biblio/conf/schema.xml && \
    echo ' <copyField source="title_auth_sort" dest="title_browse_sort"/>' >> /solr_confs/biblio/conf/schema.xml && \
    echo '</schema>' >> /solr_confs/biblio/conf/schema.xml && \
    # Now rename biblio to biblio1 and duplicate it to biblio2, then update the datadir path
    # since we have two separate collections, one to build in and one for live searching
    mv /solr_confs/biblio /solr_confs/biblio1 && \
    sed -i "s/\\bbiblio\\b/biblio1/" /solr_confs/biblio1/conf/solrconfig.xml && \
    cp -r /solr_confs/biblio1 /solr_confs/biblio2 && \
    sed -i "s/\\bbiblio1\\b/biblio2/" /solr_confs/biblio2/conf/solrconfig.xml && \
    # Use /mnt/logs/solr for the Solr logs
    rm -rf /opt/bitnami/solr/logs && \
    ln -s /mnt/logs/solr /opt/bitnami/solr/logs

COPY solr-zk-shell.sh /usr/local/bin/solr-zk-shell

HEALTHCHECK --interval=1m --timeout=10s --start-period=180s --retries=6 \
    CMD /healthcheck.sh

USER 1001

# Run start-up script
CMD ["/startup.sh", "/opt/bitnami/scripts/solr/run.sh"]
