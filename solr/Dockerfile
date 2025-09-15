FROM solr:9.8

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit
ENV BITNAMI_DEBUG=true
ARG VUFIND_VERSION

USER root

RUN \
# Install additional packages, including zk-shell
    apt update && \
    apt install -y vim-nox htop rsyslog cron moreutils gettext-base jq \
                   xsltproc python3 python3-distutils python3-pip pipx && \
    pipx install zk-shell && \
    pipx ensurepath && \
    apt remove -y python3-pip && \
    apt autoremove -y && \
    rm -rf /var/lib/apt/lists/* && \
    (umask 0022; mkdir /.zk_shell) && \
    (umask 0133; touch /.zk_shell/config) && \
    (umask 0177; touch /.zk_shell/history) && \
    chown -R "${SOLR_UID}:${SOLR_GID}" /.zk_shell && \
# Setup Timezone
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

COPY ./ /tmp/build/

# Setup cron and logging for cron
RUN cp /tmp/build/cron.d/* /etc/cron.d/ && \
    chmod -R g-w,o-w /etc/cron.d/ && \
    sed -i "/imklog/d" /etc/rsyslog.conf && \
    sed -i "/PrivDrop/d" /etc/rsyslog.conf && \
    echo "*.*;auth,authpriv.none /proc/1/fd/1" >> /etc/rsyslog.d/50-default.conf

# Get solr configs and prepare for alphabetical browsing
RUN \
    cd /tmp/build/ && \
    cp startup.sh startup-cron.sh healthcheck.sh clusterhealth.sh cron-alphabrowse.sh alpha-browse.sh lock-state.sh add_generated_call_numbers.py / && \
    mkdir -p /solr_confs/import/lib && \
    wget https://github.com/vufind-org/vufind/archive/refs/tags/v${VUFIND_VERSION}.tar.gz && \
    tar -xf v${VUFIND_VERSION}.tar.gz  && \
    cp -r vufind-${VUFIND_VERSION}/solr/vufind/* /solr_confs/ && \
    cp vufind-${VUFIND_VERSION}/import/lib/marc4j-*.jar vufind-${VUFIND_VERSION}/import/lib/reload4j-*.jar /solr_confs/import/lib/ && \
    rm v${VUFIND_VERSION}.tar.gz && \
    cp vufind-${VUFIND_VERSION}/import/*.jar /solr_confs/import/ && \
    rm -rf vufind-${VUFIND_VERSION} && \
    # Overriding browse-handler.jar and browse-indexing.jar won't be needed when we use a version of VuFind with the new TitleNormalizer
    cp /tmp/build/browse-handler.jar /solr_confs/jars/ && \
    cp /tmp/build/browse-indexing.jar /solr_confs/import/ && \
    cp /tmp/build/index-alphabetic-browse.sh /solr_confs/ && \
    chmod -R go-w /solr_confs /*.sh && \
    chmod ugo+x /*.sh /solr_confs/*.sh && \
    # Empty stopwords.txt
    > /solr_confs/biblio/conf/stopwords.txt && \
    # Fix lib paths in solrconfig.xml
    # Note: the biblio9 copy of solrconfig.xml is also edited in startup.sh
    sed -i 's/<lib dir="\.\.\/\.\.\/\.\.\/import\/lib"/<lib dir="\/solr_confs\/import\/lib"/' /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i 's/<lib dir="\.\.\/\.\.\/\.\.\/import"/<lib dir="\/solr_confs\/import"/' /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i 's/<lib dir="\.\.\/\.\.\/vendor\/modules\/analysis-extras\/lib"/<lib dir="\/opt\/solr\/modules\/analysis-extras\/lib"/' \
        /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i 's/<lib dir="\.\.\/jars"/<lib dir="\/solr_confs\/jars"/' /solr_confs/biblio/conf/solrconfig.xml && \
    # Add series browse request handler PC-110
    sed -i '/hierarchy<\/str>/ r /tmp/build/series_browse.txt' /solr_confs/biblio/conf/solrconfig.xml && \
    sed -i 's/topic,author,title,lcc,dewey,hierarchy/topic,author,title,lcc,hierarchy,series/' /solr_confs/biblio/conf/solrconfig.xml && \
    # Update the field used for alpha browse PC-686, PC-110
    sed -i 's/title_fullStr/title_browse/' /solr_confs/biblio/conf/solrconfig.xml && \
    # Apply customizations to the Solr schema
    xsltproc --stringparam modFile "/tmp/build/configs-mod/biblio/schema.xml" \
        /tmp/build/configs-mod/schema.xslt /solr_confs/biblio/conf/schema.xml \
        | sponge /solr_confs/biblio/conf/schema.xml && \
    # Now rename biblio to biblio1 and duplicate it to biblio2, then update the datadir path
    # since we have two separate collections, one to build in and one for live searching
    mv /solr_confs/biblio /solr_confs/biblio1 && \
    sed -i "s/\\bbiblio\\b/biblio1/" /solr_confs/biblio1/conf/solrconfig.xml && \
    cp -r /solr_confs/biblio1 /solr_confs/biblio2 && \
    sed -i "s/\\bbiblio1\\b/biblio2/" /solr_confs/biblio2/conf/solrconfig.xml && \
    # Create solr data directory with the right permissions
    mkdir -p /var/solr/data && \
    chown -R "${SOLR_UID}:${SOLR_GID}" /var/solr && \
    # Use /mnt/logs/solr for the Solr logs
    rm -rf /var/solr/logs && \
    ln -s /mnt/logs/solr /var/solr/logs && \
    cp /tmp/build/solr-zk-shell.sh /usr/local/bin/solr-zk-shell && \
    cd /opt/solr && \
    rm -r /tmp/build/

HEALTHCHECK --interval=1m --timeout=10s --start-period=180s --retries=6 \
    CMD /healthcheck.sh

USER $SOLR_UID

# Run start-up script
CMD ["/startup.sh", "solr-foreground"]
