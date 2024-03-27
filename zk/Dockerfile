FROM bitnami/zookeeper:3.8

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=America/Detroit
USER root

# Install additional packages, including zk-shell
RUN \
    apt update && \
    apt install -y htop moreutils gettext-base jq \
                   python3 python3-distutils python3-pip pipx && \
    # Using pipx to avoid pip's 'externally-managed-environment' error
    pipx install zk-shell && \
    apt remove -y pipx python3-pip && \
    apt autoremove -y && \
    rm -rf /var/lib/apt/lists/* && \
    (umask 0022; mkdir /.zk_shell) && \
    (umask 0133; touch /.zk_shell/config) && \
    (umask 0177; touch /.zk_shell/history) && \
    chown -R 1001 /.zk_shell && \
    ln -s /.local/bin/zk-shell /usr/local/bin/

# Setup Timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
    echo $TZ > /etc/timezone

# Set volume permissions
RUN chown -R 1001:1001 /bitnami

USER 1001
