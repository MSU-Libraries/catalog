FROM bitnami/zookeeper:3.8

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit
USER root

# Install additional packages, including zk-shell
RUN \
    useradd --uid 1001 solrcontuser && \
    apt update && \
    apt install -y htop moreutils gettext-base jq \
                   python3 python3-distutils python3-pip && \
    pip install zk-shell && \
    apt remove -y python3-pip && \
    apt autoremove -y && \
    rm -rf /var/lib/apt/lists/* && \
    (umask 0022; mkdir /.zk_shell) && \
    (umask 0133; touch /.zk_shell/config) && \
    (umask 0177; touch /.zk_shell/history) && \
    chown -R solrcontuser /.zk_shell

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

# Set volume permissions
RUN chown -R solrcontuser /bitnami

USER solrcontuser
